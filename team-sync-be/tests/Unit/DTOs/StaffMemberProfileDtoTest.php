<?php

namespace Tests\Unit\DTOs;

use App\DTOs\StaffMemberProfileDto;
use App\Models\BankInformation;
use App\Models\StaffMemberProfile;
use App\Models\JobInformation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffMemberProfileDtoTest extends TestCase
{
    use RefreshDatabase;

    public function test_from_array_maps_required_and_optional_fields(): void
    {
        $dto = StaffMemberProfileDto::fromArray($this->payload());

        $this->assertSame('EMP-001', $dto->code);
        $this->assertSame('full_time', $dto->employment_type);
        $this->assertSame(12500000.0, $dto->monthly_salary);
        $this->assertSame('islam', $dto->religion);
        $this->assertSame([
            ['name' => 'Alice', 'relationship' => 'Sibling'],
        ], $dto->emergency_contacts);
    }

    public function test_to_array_preserves_serializable_payload_shape(): void
    {
        $dto = StaffMemberProfileDto::fromArray($this->payload());

        $this->assertSame($this->payload(), $dto->toArray());
    }

    public function test_from_array_for_update_falls_back_to_existing_profile_values(): void
    {
        $user = User::factory()->create(['profile_photo' => 'users/existing.jpg']);
        $profile = StaffMemberProfile::factory()->forUser($user)->create([
            'code' => 'EMP-OLD',
            'identity_number' => '321',
            'phone' => '0812',
            'religion' => 'kristen',
        ]);

        $profile->jobInformation()->create([
            'employee_id' => $profile->id,
            'job_title' => 'Engineer',
            'team_id' => null,
            'status' => 'active',
            'employment_type' => 'contract',
            'work_location' => 'remote',
            'start_date' => '2024-01-10',
            'monthly_salary' => 9000000,
        ]);

        $profile->bankInformation()->create([
            'employee_id' => $profile->id,
            'bank_name' => 'BCA',
            'account_number' => '12345',
            'account_holder_name' => 'Old Holder',
        ]);

        $profile->setRelation('user', $user);
        $profile->setRelation('jobInformation', $profile->jobInformation);
        $profile->setRelation('bankInformation', $profile->bankInformation);

        $dto = StaffMemberProfileDto::fromArrayForUpdate([
            'phone' => '0899',
            'monthly_salary' => 10000000,
        ], $profile);

        $this->assertSame('EMP-OLD', $dto->code);
        $this->assertSame('0899', $dto->phone);
        $this->assertSame('Engineer', $dto->job_title);
        $this->assertSame('contract', $dto->employment_type);
        $this->assertSame(10000000.0, $dto->monthly_salary);
        $this->assertSame('users/existing.jpg', $dto->profile_photo);
        $this->assertSame('BCA', $dto->bank_name);
    }

    private function payload(): array
    {
        return [
            'user_id' => '1',
            'code' => 'EMP-001',
            'identity_number' => '1234567890',
            'npwp' => '12.345.678.9-012.345',
            'bpjs_ketenagakerjaan' => '987654321',
            'bpjs_kesehatan' => '123123123',
            'ptkp_status' => 'TK/0',
            'phone' => '08123456789',
            'date_of_birth' => '1995-05-05',
            'gender' => 'male',
            'religion' => 'islam',
            'marital_status' => 'single',
            'blood_type' => 'O',
            'place_of_birth' => 'Jakarta',
            'address' => 'Jl. Mawar',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'profile_photo' => 'users/photo.jpg',
            'job_title' => 'Senior Engineer',
            'team_id' => 2,
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2024-01-01',
            'monthly_salary' => 12500000.0,
            'bank_name' => 'Mandiri',
            'account_number' => '123456789',
            'account_holder_name' => 'John Doe',
            'emergency_contacts' => [
                ['name' => 'Alice', 'relationship' => 'Sibling'],
            ],
        ];
    }
}
