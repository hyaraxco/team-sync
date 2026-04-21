<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('roles')) {
            DB::table('roles')
                ->where('name', 'employee')
                ->update(['name' => 'staff']);
        }

        if (Schema::hasTable('permissions')) {
            $this->renamePermissionPrefix('employee-', 'staff-member-');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('roles')) {
            DB::table('roles')
                ->where('name', 'staff')
                ->update(['name' => 'employee']);
        }

        if (Schema::hasTable('permissions')) {
            $this->renamePermissionPrefix('staff-member-', 'employee-');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function renamePermissionPrefix(string $fromPrefix, string $toPrefix): void
    {
        $permissions = DB::table('permissions')
            ->where('name', 'like', $fromPrefix.'%')
            ->get(['id', 'name']);

        foreach ($permissions as $permission) {
            DB::table('permissions')
                ->where('id', $permission->id)
                ->update([
                    'name' => str_replace($fromPrefix, $toPrefix, (string) $permission->name),
                ]);
        }
    }
};
