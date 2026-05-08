<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * New permissions for PRD: Role Dashboard, Sidebar, Settings Alignment.
     *
     * Adds audience-scoped dashboard, analytics, and settings permissions
     * to enforce strict least-privilege per role.
     */
    private array $permissions = [
        'dashboard-self-view',
        'dashboard-team-view',
        'dashboard-finance-view',
        'dashboard-system-view',
        'analytics-team-view',
        'settings-hr-manage',
        'settings-finance-manage',
        'settings-system-manage',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum',
            ]);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permissions)->delete();
    }
};
