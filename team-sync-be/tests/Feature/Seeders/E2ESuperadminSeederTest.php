<?php

namespace Tests\Feature\Seeders;

use App\Models\StaffMemberProfile;
use App\Models\User;
use Database\Seeders\E2E\E2ESuperadminSeeder;
use Database\Seeders\MinimalPayrollE2ESeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class E2ESuperadminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scout.driver', null);

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_it_creates_one_employee_with_profile_job_information_and_only_the_superadmin_role(): void
    {
        $this->seed(E2ESuperadminSeeder::class);
        $this->seed(E2ESuperadminSeeder::class);

        $this->assertSame(1, User::where('email', 'superadmin@teamsync.com')->count());

        $user = User::where('email', 'superadmin@teamsync.com')->firstOrFail();
        $profile = StaffMemberProfile::where('code', 'SUP001')->firstOrFail();

        $this->assertSame($user->id, $profile->user_id);
        $this->assertSame('System Administrator', $profile->jobInformation->job_title);
        $this->assertSame('active', $profile->jobInformation->status);
        $this->assertSame(['superadmin'], $user->getRoleNames()->all());
        $this->assertTrue($user->hasPermissionTo('settings-system-manage', 'sanctum'));
    }

    public function test_it_keeps_existing_role_accounts_separate(): void
    {
        $this->seed(MinimalPayrollE2ESeeder::class);

        $roles = User::whereIn('email', [
            'superadmin@teamsync.com',
            'yudhis@teamsync.com',
            'tasyia@teamsync.com',
            'dwimeta@teamsync.com',
            'agung@teamsync.com',
        ])->with('roles')->get()->mapWithKeys(
            fn (User $user) => [$user->email => $user->getRoleNames()->all()]
        )->sortKeys()->all();

        $this->assertSame([
            'agung@teamsync.com' => ['staff'],
            'dwimeta@teamsync.com' => ['finance'],
            'superadmin@teamsync.com' => ['superadmin'],
            'tasyia@teamsync.com' => ['hr'],
            'yudhis@teamsync.com' => ['manager'],
        ], $roles);
    }

    public function test_it_refuses_to_run_outside_local_or_testing(): void
    {
        $original = app()->environment();

        try {
            app()->detectEnvironment(fn () => 'production');

            $this->expectException(LogicException::class);

            $this->seed(E2ESuperadminSeeder::class);
        } finally {
            app()->detectEnvironment(fn () => $original);
        }
    }
}
