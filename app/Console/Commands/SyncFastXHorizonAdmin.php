<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\AdminRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncFastXHorizonAdmin extends Command
{
    protected $signature = 'fastxhorizon:sync-admin
                            {--apply : Apply the canonical role assignment}
                            {--email= : Optional explicit target email; must match the configured target}';

    protected $description = 'Preview or apply the canonical FastXHorizon Master Admin assignment';

    public function handle(): int
    {
        $configuration = config('fastxhorizon.admin_sync');
        $configurationError = $this->validateConfiguration($configuration);
        if ($configurationError !== null) {
            $this->error($configurationError);
            $this->line('If the source config contains the key, run: php artisan optimize:clear');
            return self::FAILURE;
        }

        if (!Schema::hasTable('admins') || !Schema::hasTable('admin_roles')) {
            $this->error('Admin sync requires the existing admins and admin_roles tables. No changes were made.');
            return self::FAILURE;
        }

        $targetEmail = (string) $configuration['target_email'];
        if ($this->option('email') !== null && strtolower((string) $this->option('email')) !== strtolower($targetEmail)) {
            $this->error('The explicit --email value must match the configured FastXHorizon target admin. No changes were made.');
            return self::FAILURE;
        }

        $roleId = (int) $configuration['canonical_role_id'];
        $roleName = (string) $configuration['canonical_role_name'];
        $admin = Admin::query()->where('email', $targetEmail)->first();
        $role = AdminRole::query()->find($roleId);
        $apply = (bool) $this->option('apply');

        $this->info($apply ? 'Applying canonical FastXHorizon admin role sync.' : 'Dry run only; no database changes will be made.');
        $this->warn('This command does not create admins, change passwords, copy accounts, or touch marketplace/transactional data.');
        $this->line('Target admin: ' . $targetEmail);

        if (!$admin) {
            $this->error('Target admin was not found. No admin account will be created: ' . $targetEmail);
            return self::FAILURE;
        }

        $this->line('Current admin ID: ' . $admin->id);
        $this->line('Current admin role ID: ' . ($admin->admin_role_id ?? 'NULL'));
        $this->line('Current admin status: ' . ((int) $admin->status === 1 ? 'active' : 'inactive'));

        if ($role && (string) $role->name !== $roleName) {
            $this->error("Canonical role ID {$roleId} exists as '{$role->name}', not '{$roleName}'. No changes were made.");
            return self::FAILURE;
        }

        if (!$role) {
            $this->line("Role to create: #{$roleId} {$roleName} (active, module_access=NULL; canonical super-admin path)");
        } else {
            $permissionSummary = $role->module_access === null
                ? 'SUPER ADMIN: module_access=NULL; all existing module checks bypassed by role ID 1'
                : 'module_access entries: ' . count((array) json_decode($role->module_access, true));
            $this->line("Canonical role: #{$role->id} {$role->name}; {$permissionSummary}");
        }

        if ((int) $admin->admin_role_id === $roleId && $role?->status) {
            $this->info('Target admin already has the active canonical Master Admin role. Nothing to change.');
            return self::SUCCESS;
        }

        $this->line(($apply ? 'Assigning ' : 'Would assign ') . "role #{$roleId} ({$roleName}) to admin #{$admin->id}.");

        if (!$apply) {
            $this->comment('Re-run with --apply after reviewing the role assignment.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($admin, $role, $roleId, $roleName): void {
            if (!$role) {
                DB::table('admin_roles')->insert([
                    'id' => $roleId,
                    'name' => $roleName,
                    'module_access' => null,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                AdminRole::query()->whereKey($roleId)->update([
                    'status' => 1,
                    'module_access' => null,
                    'updated_at' => now(),
                ]);
            }

            Admin::query()->whereKey($admin->id)->update([
                'admin_role_id' => $roleId,
                'updated_at' => now(),
            ]);
        });

        $this->info('FastXHorizon canonical admin role sync completed. Password and account identity were preserved.');
        return self::SUCCESS;
    }

    private function validateConfiguration(mixed $configuration): ?string
    {
        if (!is_array($configuration)) {
            return 'Invalid FastXHorizon admin sync configuration: config(fastxhorizon.admin_sync) must be an array.';
        }

        foreach (['target_email', 'canonical_role_id', 'canonical_role_name'] as $key) {
            if (!array_key_exists($key, $configuration)) {
                return "Invalid FastXHorizon admin sync configuration: missing required key '{$key}'.";
            }
        }

        if (!filter_var($configuration['target_email'], FILTER_VALIDATE_EMAIL)) {
            return 'Invalid FastXHorizon admin sync configuration: target_email is not a valid email address.';
        }

        if ((int) $configuration['canonical_role_id'] !== 1 || (string) $configuration['canonical_role_name'] !== 'Master Admin') {
            return 'Invalid FastXHorizon admin sync configuration: canonical role must be the existing Master Admin role ID 1.';
        }

        return null;
    }
}
