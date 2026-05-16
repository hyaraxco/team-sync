<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

class CheckPermissionSyncCommand extends Command
{
    protected $signature = 'permissions:sync-check {--fail-on-missing : Exit with code 1 if permissions are missing}';

    protected $description = 'Check that all permissions used in routes exist in the database';

    public function handle(): int
    {
        $this->info('Scanning routes for permission middleware...');

        $routesContent = File::get(base_path('routes/api.php'));

        $routePermissions = $this->extractPermissionsFromRoutes($routesContent);

        if (empty($routePermissions)) {
            $this->warn('No permissions found in routes file.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d unique permission(s) in routes.', count($routePermissions)));

        $dbPermissions = Permission::pluck('name')->map(fn ($name) => strtolower($name))->toArray();

        $missing = array_filter(
            $routePermissions,
            fn ($perm) => ! in_array(strtolower($perm), $dbPermissions, true)
        );

        if (empty($missing)) {
            $this->info('✅ All route permissions exist in database. No sync issues found.');

            return self::SUCCESS;
        }

        $this->error(sprintf('❌ Found %d missing permission(s):', count($missing)));

        foreach ($missing as $perm) {
            $this->line("  - {$perm}");
        }

        if ($this->option('fail-on-missing')) {
            return self::FAILURE;
        }

        $this->warn('Run with --fail-on-missing to exit with code 1 when permissions are missing.');

        return self::SUCCESS;
    }

    /**
     * Extract all unique permission names from route file content.
     *
     * Handles both:
     *   PermissionMiddleware::using('single-permission')
     *   PermissionMiddleware::using(['perm1', 'perm2'])
     */
    private function extractPermissionsFromRoutes(string $content): array
    {
        $permissions = [];

        // Match PermissionMiddleware::using('...') — single string
        preg_match_all(
            "/PermissionMiddleware::using\(\s*'([^']+)'\s*\)/",
            $content,
            $singleMatches
        );

        if (! empty($singleMatches[1])) {
            $permissions = array_merge($permissions, $singleMatches[1]);
        }

        // Match PermissionMiddleware::using(['...', '...']) — array of strings
        preg_match_all(
            "/PermissionMiddleware::using\(\s*\[([^\]]+)\]\s*\)/",
            $content,
            $arrayMatches
        );

        foreach ($arrayMatches[1] as $arrayContent) {
            preg_match_all("/'([^']+)'/", $arrayContent, $itemMatches);
            if (! empty($itemMatches[1])) {
                $permissions = array_merge($permissions, $itemMatches[1]);
            }
        }

        return array_values(array_unique($permissions));
    }
}
