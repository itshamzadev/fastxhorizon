<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class PrepareBuilderBundle extends Command
{
    protected $signature = 'prepare:builder-bundle {output=build.zip}';

    protected $description = 'Package the current public/build (manifest.json + assets/) into the Builder addon bundle zip (build.zip, /build/* base).';

    public function handle(): int
    {
        $buildDir = public_path('build');
        if (!File::exists($buildDir . '/manifest.json')) {
            $this->error('public/build/manifest.json not found. Run `vite build` before packaging.');
            return self::FAILURE;
        }

        $output = basename((string) $this->argument('output'));
        if (!str_ends_with($output, '.zip')) {
            $output .= '.zip';
        }

        $distDir = base_path('Modules/Builder/resources/dist');
        if (!File::isDirectory($distDir)) {
            File::makeDirectory($distDir, 0755, true);
        }

        $zipPath = $distDir . '/' . $output;
        if (File::exists($zipPath)) {
            File::delete($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Cannot create {$zipPath}");
            return self::FAILURE;
        }

        // Add public/build's contents at the archive root (manifest.json + assets/)
        // so it extracts straight back over public/build on activation.
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($buildDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($buildDir) + 1);
            if ($item->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($item->getPathname(), $relativePath);
            }
        }
        $zip->close();

        $sizeMb = round(filesize($zipPath) / 1048576, 2);
        $this->info("Builder bundle packaged: {$zipPath} ({$sizeMb} MB)");
        return self::SUCCESS;
    }
}
