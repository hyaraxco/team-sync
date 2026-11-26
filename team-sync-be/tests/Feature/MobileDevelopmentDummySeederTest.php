<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MobileDevelopmentDummySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MobileDevelopmentDummySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_lp3es_seeded_users_are_employees_with_only_the_staff_role(): void
    {
        config()->set('scout.driver', null);

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seed(MobileDevelopmentDummySeeder::class);

        $users = User::query()
            ->whereIn('email', [
                'hilmy.denta@teamsync.com',
                'reza.yudhistira@teamsync.com',
                'agung.ramadhan@teamsync.com',
                'arkhab.agdana@teamsync.com',
                'ronny@teamsync.com',
                'hogie.britama@teamsync.com',
                'daris@teamsync.com',
                'febriansyah@teamsync.com',
                'didi.majdi@teamsync.com',
                'wiku@teamsync.com',
                'aufar@teamsync.com',
            ])
            ->with('roles')
            ->get();

        $this->assertCount(11, $users);

        foreach ($users as $user) {
            $this->assertNotNull($user->staffMemberProfile);
            $this->assertSame(['staff'], $user->getRoleNames()->sort()->values()->all());
        }
    }
}
