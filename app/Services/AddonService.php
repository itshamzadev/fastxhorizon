<?php

namespace App\Services;

use App\Traits\ActivationClass;
use App\Traits\AddonHelper;
use App\Traits\FileManagerTrait;
use App\Traits\SettingsTrait;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class AddonService
{
    use SettingsTrait;
    use FileManagerTrait;
    use ActivationClass;
    use AddonHelper;

    // Specific reason for the last bundle-extraction failure, surfaced to the
    // admin and logged so "activation failed during bundle extraction" is actionable.
    private string $lastBundleError = '';

    public function getUploadData(object $request): array
    {
        $tempFolderPath = storage_path('app/temp/');
        if (!File::exists($tempFolderPath)) {
            File::makeDirectory($tempFolderPath, 0775, true);
        }

        $file = $request->file('file_upload');
        $filename = $file->getClientOriginalName();
        $tempPath = $file->storeAs('temp', $filename);

        $zip = new ZipArchive();
        if ($zip->open(storage_path('app/' . $tempPath)) === TRUE) {

            $genFolderName = explode('/', $zip->getNameIndex(0))[0];
            // Default to the first entry's top folder. macOS-created zips prepend
            // a `__MACOSX/` metadata folder as the first entry, so in that case
            // scan for the first real (non-__MACOSX) entry and use its folder —
            // otherwise the info.php lookup below points at __MACOSX and the
            // upload fails with "Invalid file!".
            $getAddonFolder = explode('.', $genFolderName)[0];
            if ($genFolderName === "__MACOSX") {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    if (strpos($zip->getNameIndex($i), "__MACOSX") === false) {
                        $getAddonFolder = explode('/', $zip->getNameIndex($i))[0];
                        break;
                    }
                }
            }

            $zip->extractTo(storage_path('app/temp'));
            $infoPath = storage_path('app/temp/' . $getAddonFolder . '/Addon/info.php');

            if (File::exists($infoPath)) {
                $extractPath = base_path('Modules');
                if (!File::exists($extractPath)) {
                    File::makeDirectory($extractPath, 0775, true);
                }
                if (File::exists($extractPath . '/' . $getAddonFolder)) {
                    $message = translate('already_installed');
                    $status = 'error';
                } else {
                    if (!is_writable($extractPath)) {
                        @chmod($extractPath, 0775);
                    }
                    if (!is_writable($extractPath) || !@$zip->extractTo($extractPath)) {
                        $zip->close();
                        return [
                            'status' => 'error',
                            'message' => translate('addon_extraction_failed_modules_directory_is_not_writable') . ': ' . $extractPath,
                        ];
                    }
                    $zip->close();
                    @File::chmod($extractPath . '/' . $getAddonFolder . '/Addon', 0777);
                    $status = 'success';
                    $message = translate('upload_successfully');

                    $this->sanitizeExtractedAddon($extractPath . '/' . $getAddonFolder);
                    $this->syncPublicModuleLinks();

                    // Builder's storefront reads public/build/manifest.json at render
                    // time, so extract its bundle on upload too — not only on publish —
                    // otherwise hitting the storefront between upload and activation
                    // throws "Vite manifest not found". Best-effort: publish still runs
                    // the checked activation (checkBuilderRequirements) and surfaces any
                    // blocking issue.
                    if ($getAddonFolder === 'Builder') {
                        $this->extractBuilderBundle();
                    }
                }
            } else {
                if (File::isDirectory(storage_path('app/temp'))) {
                    File::cleanDirectory(storage_path('app/temp'));
                }
                $status = 'error';
                $message = translate('invalid_file!');
            }
        } else {
            $status = 'error';
            $message = translate('file_upload_fail!');
        }

        if (File::exists(base_path('Modules/__MACOSX'))) {
            File::deleteDirectory(base_path('Modules/__MACOSX'));
        }

        if (File::isDirectory(storage_path('app/temp'))) {
            File::cleanDirectory(storage_path('app/temp'));
        }

        return [
            'status' => $status,
            'message' => $message
        ];
    }

    public function getPublishData(object $request): array
    {
        $fullData = include(base_path($request['path'] . '/Addon/info.php'));
        $path = $request['path'];
        $addonName = $fullData['name'];

        if ($fullData['purchase_code'] == null || $fullData['username'] == null) {
            // Every addon — Gateways included — uses the same activation flow:
            // the new modal (name/email/username/purchase key) posts to
            // addon-activation.activate-from-list → v2/register-domain API.
            return [
                'flag' => 'inactive',
                'view' => view('admin-views.system-setup.addons.partials.addon-activation-modal', compact('fullData', 'path', 'addonName'))->render(),
            ];
        }
        $goingActive = !$fullData['is_published'];
        $fullData['is_published'] = $goingActive ? 1 : 0;
        $str = "<?php return " . var_export($fullData, true) . ";";
        file_put_contents(base_path($request['path'] . '/Addon/info.php'), $str);

        if ($goingActive) {
            $this->runAddonMigrations($path);
        }

        if ($addonName === 'Builder') {
            if ($goingActive) {
                $result = $this->activateBuilderBundle();
                if (!$result['ok']) {
                    // Never leave Builder "activated but no bundle" — roll back the flip.
                    $fullData['is_published'] = 0;
                    file_put_contents(base_path($request['path'] . '/Addon/info.php'), "<?php return " . var_export($fullData, true) . ";");
                    return ['status' => 'error', 'message' => $result['message']];
                }
            } else {
                $this->removeBuilderBundle();
            }
        }

        Artisan::call('optimize:clear');
        Artisan::call('view:clear');

        return [
            'status' => 'success',
            'message' => translate('status_updated_successfully')
        ];
    }

    /**
     * Run a freshly uploaded addon's migrations on activation. Module providers
     * register their migrations via loadMigrationsFrom, so once the module is
     * enabled and booted, a scoped migrate creates its tables (e.g. Builder's
     * builder_setups). No-op for addons that ship no migrations.
     */
    public function runAddonMigrations(string $path): void
    {
        if (File::isDirectory(base_path($path . '/database/migrations'))) {
            Artisan::call('migrate', ['--force' => true]);
        }
    }

    /**
     * Builder ships its compiled Vite storefront bundle inside the addon
     * (Modules/Builder/resources/dist/build.zip) rather than in the core
     * install. On activation we run a server pre-flight, then extract that
     * bundle into public/build so the bundle version always matches the
     * uploaded addon. Returns ['ok' => bool, 'message' => string]; on failure
     * the caller MUST NOT keep is_published = 1.
     */
    public function activateBuilderBundle(): array
    {
        $issues = $this->checkBuilderRequirements();
        if (!empty($issues)) {
            $issue = $issues[0];
            return ['ok' => false, 'message' => $issue['message'] . ' — ' . $issue['fix']];
        }

        if (!$this->extractBuilderBundle()) {
            Log::error('Builder bundle extraction failed: ' . ($this->lastBundleError ?: 'unknown'));
            $message = translate('builder_activation_failed_during_bundle_extraction');
            if ($this->lastBundleError !== '') {
                $message .= ' — ' . $this->lastBundleError;
            }
            return ['ok' => false, 'message' => $message];
        }

        return ['ok' => true, 'message' => ''];
    }

    /**
     * Server pre-flight for Builder activation. Returns a list of blocking
     * issues (each with a human message + fix) so admins get an actionable
     * error before any state is changed, rather than a broken storefront.
     */
    public function checkBuilderRequirements(): array
    {
        $issues = [];

        if (PHP_VERSION_ID < 80100) {
            $issues[] = ['message' => translate('php_8.1_or_higher_is_required') . ' (' . PHP_VERSION . ')', 'fix' => translate('upgrade_php_from_your_hosting_control_panel')];
        }

        foreach (['zip', 'pdo_mysql', 'mbstring', 'fileinfo', 'openssl', 'tokenizer', 'xml', 'ctype', 'bcmath'] as $extension) {
            if (!extension_loaded($extension)) {
                $issues[] = ['message' => translate('php_extension_not_loaded') . ": {$extension}", 'fix' => translate('enable_it_in_php_ini_or_via_your_hosting_panel')];
            }
        }
        if (!extension_loaded('gd') && !extension_loaded('imagick')) {
            $issues[] = ['message' => translate('no_image_extension_gd_or_imagick_is_loaded'), 'fix' => translate('enable_the_gd_extension_in_php_ini')];
        }

        foreach (['symlink', 'copy', 'unlink', 'rmdir', 'glob', 'realpath'] as $function) {
            if (!function_exists($function)) {
                $issues[] = ['message' => translate('php_function_is_disabled') . ": {$function}", 'fix' => translate('remove_it_from_disable_functions_in_php_ini')];
            }
        }

        foreach ([public_path(), base_path('bootstrap/cache'), storage_path(), base_path('Modules/Builder')] as $writablePath) {
            if (is_dir($writablePath) && !is_writable($writablePath)) {
                $label = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $writablePath);
                $issues[] = ['message' => translate('path_is_not_writable') . ": {$label}", 'fix' => translate('grant_write_permission_to_the_web_server_user')];
            }
        }

        if (!file_exists(base_path('Modules/Builder/resources/dist/build.zip'))) {
            $issues[] = ['message' => translate('builder_pre_built_bundle_is_missing'), 'fix' => translate('re_upload_the_addon_zip_the_bundle_appears_incomplete')];
        }

        return $issues;
    }

    /**
     * Extract the Builder addon's pre-built bundle. The single zip holds the
     * compiled assets at its root (manifest.json + assets/) with a /build/* base
     * baked into its lazy-loaded chunks. It always lands in public/build — the
     * framework reads the Vite manifest from public_path('build/manifest.json')
     * server-side in every layout. Under a root docroot the browser fetches
     * /build/* from the project root, so we expose public/build there via a
     * `build` symlink rather than shipping a second base-variant bundle.
     * Rebuilt from scratch each activation.
     */
    public function extractBuilderBundle(): bool
    {
        $this->lastBundleError = '';

        $reason = $this->extractZipTo(base_path('Modules/Builder/resources/dist/build.zip'), public_path('build'));
        if ($reason !== '') {
            $this->lastBundleError = $reason;
            return false;
        }

        if (!$this->linkPublicBuildToProjectRoot()) {
            $this->lastBundleError = 'could not create the "build" entry at the project root ' . base_path()
                . ' (required for this docroot). Grant the web-server user write permission to the project root, or enable symlink().';
            return false;
        }

        return true;
    }

    /**
     * Expose public/build at the project root as `build` so the bundle is present
     * at both public/build and /build. We always attempt it (symlink first, then a
     * real copy for hosts that disable symlink()), on every upload/activation.
     *
     * The project-root entry is REQUIRED only under a root docroot (public/ served
     * as a subfolder), where the storefront's lazy-loaded chunks are fetched from
     * the domain root. Under a public docroot /build already resolves to
     * public/build, so if the project root is not web-writable we still report
     * success — the attempt is best-effort and must never become a 500.
     */
    private function linkPublicBuildToProjectRoot(): bool
    {
        $target = public_path('build');
        if (!File::isDirectory($target)) {
            return false;
        }

        if ($this->rebuildProjectRootBuild($target, base_path('build'))) {
            return true;
        }

        // Couldn't create /build (project root not writable by the web-server user,
        // or symlink() disabled). Record why so the missing /build is diagnosable
        // instead of silent.
        Log::warning('Builder: could not create "build" at the project root ' . base_path()
            . ' — grant the web-server user write permission there (or create the symlink over SSH). public/build was created normally.');

        // This only breaks a root docroot; under a public docroot the storefront
        // still loads from public/build, so don't fail activation for it.
        $rootDocroot = (defined('DOMAIN_POINTED_DIRECTORY') ? DOMAIN_POINTED_DIRECTORY : 'public') !== 'public';
        return !$rootDocroot;
    }

    /**
     * (Re)create the project-root `build` entry pointing at $target. Rebuilt fresh
     * each time so it tracks the current bundle. All filesystem errors (unwritable
     * root, symlink disabled, mkdir denied) are swallowed into a false return so a
     * copy into a locked-down root can never surface as an uncaught 500.
     */
    private function rebuildProjectRootBuild(string $target, string $link): bool
    {
        try {
            if (is_link($link) || is_file($link)) {
                @unlink($link);
            } elseif (File::isDirectory($link)) {
                File::deleteDirectory($link);
            }

            if (@symlink($target, $link)) {
                return true;
            }

            return File::copyDirectory($target, $link);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Replace $targetDir with the contents of $zipPath. Returns '' on success or a
     * specific human-readable reason on failure so the caller can tell the admin
     * why activation broke instead of a generic message.
     *
     * Extraction goes to a sibling temp dir first, then swaps in — so a previous
     * public/build whose files are owned by a different user (e.g. extracted once
     * over CLI, re-activated by the web-server user) can't leave a half-overwritten
     * tree, and the swap is near-atomic.
     */
    private function extractZipTo(string $zipPath, string $targetDir): string
    {
        if (!file_exists($zipPath)) {
            return 'source bundle not found at ' . $zipPath;
        }

        $stagingDir = $targetDir . '_tmp_' . getmypid();
        if (File::isDirectory($stagingDir)) {
            File::deleteDirectory($stagingDir);
        }
        if (!File::makeDirectory($stagingDir, 0755, true, true)) {
            return 'could not create a staging directory next to ' . $targetDir . ' — the parent directory is not writable by the web-server user';
        }

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath);
        if ($opened !== true) {
            File::deleteDirectory($stagingDir);
            return 'ZipArchive could not open the bundle (error code ' . $opened . ') — the zip is likely corrupt or truncated; re-upload the addon';
        }
        if (!$zip->extractTo($stagingDir)) {
            $zip->close();
            File::deleteDirectory($stagingDir);
            return 'extraction failed — likely out of disk space or an open_basedir restriction';
        }
        $zip->close();

        // Swap staging into place. Remove any previous bundle first; if those files
        // are not removable by this user, report it precisely (the usual root cause).
        if (is_link($targetDir)) {
            @unlink($targetDir);
        } elseif (File::isDirectory($targetDir) && !File::deleteDirectory($targetDir)) {
            File::deleteDirectory($stagingDir);
            return 'could not remove the existing ' . $targetDir . ' — its files are owned by a different user than the web server; delete public/build over SSH and retry';
        }

        if (!@rename($stagingDir, $targetDir)) {
            File::deleteDirectory($stagingDir);
            return 'could not move the extracted bundle into ' . $targetDir;
        }

        return '';
    }

    /**
     * Remove the extracted bundle on deactivation so the disk returns to its
     * pre-activation state: unlink the project-root `build` symlink first, then
     * delete the real public/build. Safe because only the Builder storefront
     * loads the build assets; legacy Blade themes use public/assets.
     */
    public function removeBuilderBundle(): void
    {
        $rootLink = base_path('build');
        if (is_link($rootLink)) {
            @unlink($rootLink);
        }

        $bundle = public_path('build');
        if (!is_link($bundle) && File::isDirectory($bundle)) {
            File::deleteDirectory($bundle);
        }
    }

    public function getActivationData(object $request): array
    {
        $url = $this->getCurrentDomain();
        $full_data = include(base_path($request['path'] . '/Addon/info.php'));

        $post = [
            base64_decode('dXNlcm5hbWU=') => $request['username'],
            base64_decode('cHVyY2hhc2Vfa2V5') => $request['purchase_code'],
            base64_decode('c29mdHdhcmVfaWQ=') => $full_data['software_id'],
            base64_decode('ZG9tYWlu') => $url,
        ];

        $response = Http::post(base64_decode('aHR0cHM6Ly9jaGVjay42YW10ZWNoLmNvbS9hcGkvdjEvYWN0aXZhdGlvbi1jaGVjaw=='), $post)->json();
        $status = isset($response['active']) ? base64_decode($response['active']) ?? 1 : 0;

        if ((int)$status) {
            $full_data['is_published'] = 1;
            $full_data['username'] = $request['username'];
            $full_data['purchase_code'] = $request['purchase_code'];
            $str = "<?php return " . var_export($full_data, true) . ";";
            file_put_contents(base_path($request['path'] . '/Addon/info.php'), $str);
        }

        $activationUrl = base64_decode('aHR0cHM6Ly9hY3RpdmF0aW9uLjZhbXRlY2guY29t');
        $activationUrl .= '?username=' . $request['username'];
        $activationUrl .= '&purchase_code=' . $request['purchase_code'];
        $activationUrl .= '&domain=' . url('/') . '&';

        return [
            'status' => (int)$status,
            'activationUrl' => $activationUrl
        ];

    }

    public function deleteAddon(object $request): array
    {
        $path = $request['path'];
        $full_path = base_path($path);
        if (basename($path) === 'Gateways') {
            $old = base_path('app/Traits/Payment.php');
            $new = base_path('app/Traits/Payment.txt');
            copy($new, $old);
        }

        if (File::deleteDirectory($full_path)) {
            $status = 'success';
            $message = translate('file_delete_successfully');
            $this->syncPublicModuleLinks();
        } else {
            $status = 'error';
            $message = translate('file_delete_fail');
        }

        return [
            'status' => $status,
            'message' => $message
        ];
    }

    private function syncPublicModuleLinks(): void
    {
        if (DOMAIN_POINTED_DIRECTORY != 'public') {
            return;
        }

        try {
            $this->doSyncPublicModuleLinks();
        } catch (\Throwable $e) {

        }
    }

    private function doSyncPublicModuleLinks(): void
    {
        $publicModulesPath = public_path('Modules');
        $modulesBasePath = base_path('Modules');

        // Legacy installs may have public/Modules as an umbrella symlink to ../Modules.
        // Replace it with a real directory so each addon can be exposed as its own entry.
        if (is_link($publicModulesPath)) {
            @unlink($publicModulesPath);
        }
        if (!File::isDirectory($publicModulesPath)) {
            File::makeDirectory($publicModulesPath, 0775, true);
        }

        // Cleanup: drop legacy whole-module symlinks and stale module-assets
        // (target addon removed or no longer ships a module-assets dir).
        foreach (scandir($publicModulesPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $publicModulesPath . DIRECTORY_SEPARATOR . $entry;
            $sourceAssets = $modulesBasePath . DIRECTORY_SEPARATOR . $entry . DIRECTORY_SEPARATOR . 'module-assets';

            // macOS zip noise — never expose, regardless of symlink/dir/file.
            if ($entry === '__MACOSX') {
                if (is_link($entryPath) || is_file($entryPath)) {
                    @unlink($entryPath);
                } elseif (File::isDirectory($entryPath)) {
                    File::deleteDirectory($entryPath);
                }
                continue;
            }

            // Legacy whole-module symlink — remove so it can be replaced by a scoped copy.
            if (is_link($entryPath)) {
                @unlink($entryPath);
                continue;
            }

            if (!File::isDirectory($entryPath)) {
                continue;
            }

            $assetsPath = $entryPath . DIRECTORY_SEPARATOR . 'module-assets';
            $sourceMissing = !File::isDirectory($sourceAssets);

            if ($sourceMissing) {
                $this->removePath($assetsPath);
            }

            if ($sourceMissing && File::isDirectory($entryPath) && count(scandir($entryPath)) === 2) {
                @rmdir($entryPath);
            }
        }

        // Copy module-assets into public/Modules/{Name}/module-assets for every addon
        // that ships one. Refreshed each sync so reinstalled/updated addons expose
        // their latest assets without relying on host symlink support.
        foreach (File::directories($modulesBasePath) as $modulePath) {
            $moduleName = basename($modulePath);
            $sourceAssets = $modulePath . DIRECTORY_SEPARATOR . 'module-assets';
            if (!File::isDirectory($sourceAssets)) {
                continue;
            }

            $moduleStubPath = $publicModulesPath . DIRECTORY_SEPARATOR . $moduleName;
            $assetsPath = $moduleStubPath . DIRECTORY_SEPARATOR . 'module-assets';

            if (!File::isDirectory($moduleStubPath)) {
                File::makeDirectory($moduleStubPath, 0775, true);
            }

            $this->removePath($assetsPath);

            if (!File::copyDirectory($sourceAssets, $assetsPath)) {
                $this->copyAddonAssetsBestEffort($sourceAssets, $assetsPath);
            }
        }

        Artisan::call('optimize:clear');
        Artisan::call('view:clear');
    }

    private function copyAddonAssetsBestEffort(string $source, string $destination): void
    {
        $this->removePath($destination);
        File::makeDirectory($destination, 0775, true);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            $target = $destination . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                // Symlink or stray file at this path would cause copies to land
                // outside the intended tree — drop it before recreating.
                if (is_link($target) || (file_exists($target) && !is_dir($target))) {
                    @unlink($target);
                }
                if (!is_dir($target)) {
                    @mkdir($target, 0775, true);
                }
                continue;
            }

            // copy() follows symlinks at the destination, so unlink first to
            // avoid overwriting the link target instead of replacing the link.
            if (is_link($target) || is_dir($target)) {
                $this->removePath($target);
            }

            @copy($item->getPathname(), $target);
        }
    }

    /**
     * Strip packaging noise from a freshly extracted addon so a hand-made zip
     * (macOS Finder, or one that bundled unpacked build output) installs clean:
     * removes every .DS_Store, and any stray unpacked build directories under
     * resources/dist — only the build*.zip bundles belong there, the extracted
     * build/ folders just bloat the module.
     */
    private function sanitizeExtractedAddon(string $moduleDir): void
    {
        if (!File::isDirectory($moduleDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($moduleDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isFile() && $item->getFilename() === '.DS_Store') {
                @unlink($item->getPathname());
            }
        }

        foreach (['build', 'build-public'] as $strayBuildDir) {
            $strayPath = $moduleDir . '/resources/dist/' . $strayBuildDir;
            if (File::isDirectory($strayPath)) {
                File::deleteDirectory($strayPath);
            }
        }
    }

    private function removePath(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
    }

    public function getCurrentDomain(): string
    {
        return str_replace(["http://", "https://", "www."], "", url('/'));
    }

    public function addonActivationProcess(object $request): array
    {
        $response = $this->getRequestConfig(
            username: $request['username'],
            purchaseKey: $request['purchase_key'],
            softwareId: $request['software_id'] ?? SOFTWARE_ID,
            softwareType: $request['software_type'] ?? base64_decode('cHJvZHVjdA=='),
            name: $request['name'],
            identifier: $request['email'],
        );

        $this->updateActivationConfig(app: $request['addon_name'], response: $response);

        $status = $response['active'] ?? 0;
        $message = $response['message'] ?? translate('Activation_failed');

        if ((int)$status) {
            return [
                'status' => (int)$status,
                'activation_status' => 1,
                'username' => $request['username'],
                'purchase_code' => $request['purchase_code'],
            ];
        }

        return [
            'status' => (int)$status,
            'message' => $message
        ];
    }

}
