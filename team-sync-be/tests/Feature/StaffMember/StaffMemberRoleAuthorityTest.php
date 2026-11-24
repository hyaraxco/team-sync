<?php

namespace Tests\Feature\StaffMember;

use App\Models\BankInformation;
use App\Models\EmergencyContact;
use App\Models\JobInformation;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffMemberRoleAuthorityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scout.driver' => 'null']);
        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[DataProvider('privilegedRoles')]
    public function test_hr_cannot_create_any_privileged_role(string $role): void
    {
        Sanctum::actingAs($this->profile(['hr'])->user);
        $before = $this->staffTables();

        $response = $this->postJson('/api/v1/staff-members', $this->creationPayload([$role]));

        $this->assertDeniedWithoutLeaks($response);
        $this->assertSame($before, $this->staffTables());
    }

    public function test_hr_cannot_hide_privileged_creation_among_staff_roles(): void
    {
        Sanctum::actingAs($this->profile(['hr'])->user);
        $before = $this->staffTables();

        $response = $this->postJson('/api/v1/staff-members', $this->creationPayload(['staff', 'superadmin', 'staff']));

        $this->assertDeniedWithoutLeaks($response);
        $this->assertSame($before, $this->staffTables());
    }

    public function test_hr_can_create_an_ordinary_staff_member(): void
    {
        Sanctum::actingAs($this->profile(['hr'])->user);

        $this->postJson('/api/v1/staff-members', $this->creationPayload(['staff']))->assertCreated();

        $user = User::where('email', 'candidate@example.test')->firstOrFail();
        $this->assertEqualsCanonicalizing(['staff'], $user->getRoleNames()->all());
        $this->assertTrue(Hash::check('DummyPassword123!', $user->password));
        $profile = $user->staffMemberProfile;
        $this->assertNotNull($profile);
        $this->assertSame('Dummy Engineer', $profile->jobInformation->job_title);
        $this->assertSame('555000111222', $profile->bankInformation->account_number);
        $this->assertSame('Dummy Contact', $profile->emergencyContacts->sole()->full_name);
    }

    #[DataProvider('forbiddenRoleChanges')]
    public function test_hr_cannot_promote_or_strip_roles_even_with_other_valid_changes(
        array $existingRoles,
        array $requestedRoles,
        bool $self,
    ): void {
        $actor = $this->profile(['hr']);
        $target = $self ? $actor : $this->profile($existingRoles);
        Sanctum::actingAs($actor->user);
        $before = $this->staffTables();

        $response = $this->multipartUpdate($target, array_merge($this->maintenancePayload($target), [
            'roles' => $requestedRoles,
        ]));

        $this->assertDeniedWithoutLeaks($response);
        $this->assertSame($before, $this->staffTables());
    }

    public function test_forbidden_role_assignment_is_denied_before_validation_can_disclose_existing_data(): void
    {
        $actor = $this->profile(['hr']);
        $target = $this->profile(['staff']);
        Sanctum::actingAs($actor->user);
        $before = $this->staffTables();

        $this->assertDeniedWithoutLeaks($this->postJson('/api/v1/staff-members', [
            'roles' => ['superadmin'],
            'email' => $target->user->email,
            'identity_number' => $target->identity_number,
        ]));
        $this->assertDeniedWithoutLeaks($this->multipartUpdate($target, [
            'roles' => ['superadmin'],
            'email' => $actor->user->email,
            'identity_number' => $actor->identity_number,
            'password' => 'short',
        ]));

        $this->assertSame($before, $this->staffTables());
    }

    public function test_hr_can_submit_the_same_multirole_set_in_a_different_order_with_duplicates(): void
    {
        $target = $this->profile(['staff', 'manager', 'finance']);
        Sanctum::actingAs($this->profile(['hr'])->user);

        $this->multipartUpdate($target, array_merge($this->maintenancePayload($target), [
            'roles' => ['finance', 'staff', 'manager', 'finance'],
            'email' => $target->user->email,
        ]))->assertOk();

        $this->assertEqualsCanonicalizing(['staff', 'manager', 'finance'], $target->user->fresh()->getRoleNames()->all());
        $this->assertMaintenanceSaved($target);
    }

    #[DataProvider('privilegedCredentialChanges')]
    public function test_hr_cannot_take_over_another_privileged_account(
        string $role,
        string $credential,
        bool $includeRoles,
    ): void {
        $target = $this->profile(['staff', $role]);
        Sanctum::actingAs($this->profile(['hr'])->user);
        $payload = $this->maintenancePayload($target);
        $payload[$credential] = $credential === 'email' ? 'takeover@example.test' : 'TakeoverPassword123!';
        if ($includeRoles) {
            $payload['roles'] = ['staff', $role];
        }
        $before = $this->staffTables();

        $this->assertDeniedWithoutLeaks($this->multipartUpdate($target, $payload));

        $this->assertSame($before, $this->staffTables());
    }

    public function test_hr_cannot_resubmit_the_existing_password_hash_or_an_empty_password_for_a_privileged_target(): void
    {
        $target = $this->profile(['superadmin']);
        Sanctum::actingAs($this->profile(['hr'])->user);
        $before = $this->staffTables();

        foreach ([$target->user->password, ''] as $password) {
            $this->assertDeniedWithoutLeaks($this->multipartUpdate($target, ['password' => $password]));
        }

        $this->assertSame($before, $this->staffTables());
    }

    #[DataProvider('maintenanceTargets')]
    public function test_hr_can_maintain_profiles_without_changing_authority(array $roles, bool $includeRoles): void
    {
        $target = $this->profile($roles);
        Sanctum::actingAs($this->profile(['hr'])->user);
        $payload = $this->maintenancePayload($target);
        $payload['email'] = $target->user->email;
        if ($includeRoles) {
            $payload['roles'] = $roles;
        }

        $this->multipartUpdate($target, $payload)->assertOk();

        $this->assertEqualsCanonicalizing($roles, $target->user->fresh()->getRoleNames()->all());
        $this->assertMaintenanceSaved($target);
    }

    public function test_hr_can_change_an_ordinary_staff_members_credentials(): void
    {
        $target = $this->profile(['staff']);
        Sanctum::actingAs($this->profile(['hr'])->user);

        $this->multipartUpdate($target, [
            'email' => 'staff.updated@example.test',
            'password' => 'UpdatedPassword123!',
            'roles' => ['staff'],
        ])->assertOk();

        $user = $target->user->fresh();
        $this->assertSame('staff.updated@example.test', $user->email);
        $this->assertTrue(Hash::check('UpdatedPassword123!', $user->password));
        $this->assertEqualsCanonicalizing(['staff'], $user->getRoleNames()->all());
    }

    public function test_hr_can_change_own_credentials_without_changing_roles(): void
    {
        $actor = $this->profile(['hr']);
        Sanctum::actingAs($actor->user);

        $this->multipartUpdate($actor, [
            'email' => 'hr.updated@example.test',
            'password' => 'UpdatedPassword123!',
            'roles' => ['hr'],
        ])->assertOk();

        $user = $actor->user->fresh();
        $this->assertSame('hr.updated@example.test', $user->email);
        $this->assertTrue(Hash::check('UpdatedPassword123!', $user->password));
        $this->assertEqualsCanonicalizing(['hr'], $user->getRoleNames()->all());
    }

    #[DataProvider('privilegedRoles')]
    public function test_superadmin_can_create_each_privileged_role(string $role): void
    {
        Sanctum::actingAs($this->profile(['superadmin'])->user);

        $this->postJson('/api/v1/staff-members', $this->creationPayload([$role]))->assertCreated();

        $user = User::where('email', 'candidate@example.test')->firstOrFail();
        $this->assertEqualsCanonicalizing([$role], $user->getRoleNames()->all());
    }

    #[DataProvider('superadminRoleChanges')]
    public function test_superadmin_can_change_roles_and_privileged_credentials(array $existingRoles, array $requestedRoles): void
    {
        $target = $this->profile($existingRoles);
        Sanctum::actingAs($this->profile(['superadmin'])->user);

        $this->multipartUpdate($target, array_merge($this->maintenancePayload($target), [
            'roles' => $requestedRoles,
            'email' => 'admin.updated@example.test',
            'password' => 'UpdatedPassword123!',
        ]))->assertOk();

        $user = $target->user->fresh();
        $this->assertEqualsCanonicalizing($requestedRoles, $user->getRoleNames()->all());
        $this->assertSame('admin.updated@example.test', $user->email);
        $this->assertTrue(Hash::check('UpdatedPassword123!', $user->password));
        $this->assertMaintenanceSaved($target);
    }

    public static function privilegedRoles(): array
    {
        return [
            'HR' => ['hr'],
            'manager' => ['manager'],
            'finance' => ['finance'],
            'superadmin' => ['superadmin'],
        ];
    }

    public static function forbiddenRoleChanges(): iterable
    {
        foreach (['hr', 'manager', 'finance', 'superadmin'] as $role) {
            yield 'promote staff to '.$role => [['staff'], [$role], false];
            yield 'strip '.$role => [[$role], ['staff'], false];
            yield 'strip secondary '.$role => [['staff', $role], ['staff'], false];
        }
        yield 'self promotion' => [['hr'], ['hr', 'superadmin'], true];
        yield 'self role replacement' => [['hr'], ['finance'], true];
        yield 'self demotion' => [['hr'], ['staff'], true];
        yield 'clear roles' => [['hr'], [], false];
    }

    public static function privilegedCredentialChanges(): iterable
    {
        foreach (['hr', 'manager', 'finance', 'superadmin'] as $role) {
            foreach (['email', 'password'] as $credential) {
                foreach ([false, true] as $includeRoles) {
                    yield $role.' '.$credential.($includeRoles ? ' with unchanged roles' : ' without roles') => [
                        $role, $credential, $includeRoles,
                    ];
                }
            }
        }
    }

    public static function maintenanceTargets(): iterable
    {
        foreach (['staff', 'hr', 'manager', 'finance', 'superadmin'] as $role) {
            foreach ([false, true] as $includeRoles) {
                yield $role.($includeRoles ? ' with unchanged roles' : ' without roles') => [[$role], $includeRoles];
            }
        }
    }

    public static function superadminRoleChanges(): array
    {
        return [
            'promotion' => [['staff'], ['superadmin']],
            'demotion' => [['superadmin'], ['staff']],
            'multiple roles' => [['hr', 'finance'], ['manager', 'finance']],
            'unchanged privileged roles' => [['superadmin'], ['superadmin']],
        ];
    }

    private function profile(array $roles): StaffMemberProfile
    {
        $user = User::factory()->create();
        $user->syncRoles(array_map(fn (string $role) => Role::findByName($role, 'sanctum'), $roles));
        $profile = StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        JobInformation::factory()->create([
            'staff_member_id' => $profile->id,
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
        ]);
        BankInformation::factory()->create(['staff_member_id' => $profile->id]);
        EmergencyContact::factory()->create(['staff_member_id' => $profile->id]);

        return $profile->load(['user', 'jobInformation', 'bankInformation', 'emergencyContacts']);
    }

    private function creationPayload(array $roles): array
    {
        return [
            'name' => 'Dummy Candidate',
            'email' => 'candidate@example.test',
            'password' => 'DummyPassword123!',
            'roles' => $roles,
            'identity_number' => '9988776655443322',
            'phone' => '081299887766',
            'date_of_birth' => '1998-10-10',
            'gender' => 'female',
            'place_of_birth' => 'Bandung',
            'address' => 'Dummy Address 10',
            'city' => 'Bandung',
            'postal_code' => '40123',
            'job_title' => 'Dummy Engineer',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2025-01-01',
            'monthly_salary' => 9000000,
            'bank_name' => 'bca',
            'account_number' => '555000111222',
            'account_holder_name' => 'Dummy Candidate',
            'emergency_contacts' => [[
                'full_name' => 'Dummy Contact',
                'relationship' => 'Sibling',
                'phone' => '081233344455',
            ]],
        ];
    }

    private function maintenancePayload(StaffMemberProfile $profile): array
    {
        return [
            'name' => 'Updated Dummy Name',
            'phone' => '081211110000',
            'city' => 'Updated Dummy City',
            'job_title' => 'Updated Dummy Job',
            'bank_name' => 'bca',
            'account_holder_name' => 'Updated Dummy Holder',
            'emergency_contacts' => [[
                'id' => $profile->emergencyContacts->sole()->id,
                'full_name' => 'Updated Dummy Contact',
                'relationship' => 'Sibling',
                'phone' => '081222220000',
            ]],
        ];
    }

    private function multipartUpdate(StaffMemberProfile $profile, array $payload): TestResponse
    {
        return $this->post('/api/v1/staff-members/'.$profile->id, array_merge($payload, [
            '_method' => 'PUT',
        ]), [
            'Accept' => 'application/json',
            'Content-Type' => 'multipart/form-data',
        ]);
    }

    private function assertMaintenanceSaved(StaffMemberProfile $profile): void
    {
        $profile->refresh();
        $this->assertSame('Updated Dummy Name', $profile->user->name);
        $this->assertSame('Updated Dummy City', $profile->city);
        $this->assertSame('081211110000', $profile->phone);
        $this->assertSame('Updated Dummy Job', $profile->jobInformation->job_title);
        $this->assertSame('Updated Dummy Holder', $profile->bankInformation->account_holder_name);
        $this->assertSame('Updated Dummy Contact', $profile->emergencyContacts->sole()->full_name);
    }

    private function staffTables(): array
    {
        $rows = [];
        foreach (['users', 'staff_member_profiles', 'job_information', 'bank_information', 'emergency_contacts', 'model_has_roles'] as $table) {
            $rows[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        return $rows;
    }

    private function assertDeniedWithoutLeaks(TestResponse $response): void
    {
        $response->assertForbidden();
        $this->assertNull($response->json('data'));
        foreach (['password', 'remember_token', 'identity_number', 'npwp', 'bpjs_ketenagakerjaan', 'bpjs_kesehatan', 'account_number', 'account_holder_name', 'monthly_salary', 'emergency_contacts'] as $field) {
            $this->assertStringNotContainsString('"'.$field.'":', $response->getContent());
        }
    }
}
