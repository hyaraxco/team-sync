<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\StaffMemberProfileStoreRequest;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StaffMemberProfileStoreRequestTest extends TestCase
{
    use RefreshDatabase;

    private array $validData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->validData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'roles' => ['staff'],
            'identity_number' => '1234567890',
            'phone' => '081234567890',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'place_of_birth' => 'Jakarta',
            'address' => 'Jl. Sudirman No. 1',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'job_title' => 'Software Engineer',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2024-01-01',
            'monthly_salary' => 10000000,
            'bank_name' => 'bca',
            'account_number' => '1234567890',
            'account_holder_name' => 'John Doe',
            'emergency_contacts' => [
                [
                    'full_name' => 'Jane Doe',
                    'relationship' => 'Spouse',
                    'phone' => '081234567891',
                ],
            ],
        ];
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = StaffMemberProfileStoreRequest::create('/api/v1/staff-members', 'POST', $data);
        $rules = (new StaffMemberProfileStoreRequest)->rules();

        return Validator::make($data, $rules);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Required Fields
    // ─────────────────────────────────────────────────────────────────────────

    public function test_valid_data_passes(): void
    {
        $validator = $this->validate($this->validData);
        $this->assertTrue($validator->passes());
    }

    public function test_name_is_required(): void
    {
        unset($this->validData['name']);
        $validator = $this->validate($this->validData);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_email_is_required(): void
    {
        unset($this->validData['email']);
        $validator = $this->validate($this->validData);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_password_is_required(): void
    {
        unset($this->validData['password']);
        $validator = $this->validate($this->validData);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_identity_number_is_required(): void
    {
        unset($this->validData['identity_number']);
        $validator = $this->validate($this->validData);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('identity_number', $validator->errors()->toArray());
    }

    public function test_job_title_is_required(): void
    {
        unset($this->validData['job_title']);
        $validator = $this->validate($this->validData);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('job_title', $validator->errors()->toArray());
    }

    public function test_emergency_contacts_is_required(): void
    {
        unset($this->validData['emergency_contacts']);
        $validator = $this->validate($this->validData);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('emergency_contacts', $validator->errors()->toArray());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Type Constraints
    // ─────────────────────────────────────────────────────────────────────────

    public function test_email_must_be_valid(): void
    {
        $this->validData['email'] = 'not-an-email';
        $validator = $this->validate($this->validData);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_password_must_be_at_least_8_characters(): void
    {
        $this->validData['password'] = 'short';
        $validator = $this->validate($this->validData);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_gender_must_be_valid_enum(): void
    {
        $this->validData['gender'] = 'invalid';
        $validator = $this->validate($this->validData);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('gender', $validator->errors()->toArray());
    }

    public function test_employment_type_must_be_valid_enum(): void
    {
        $this->validData['employment_type'] = 'invalid';
        $validator = $this->validate($this->validData);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('employment_type', $validator->errors()->toArray());
    }

    public function test_monthly_salary_must_be_numeric(): void
    {
        $this->validData['monthly_salary'] = 'not-a-number';
        $validator = $this->validate($this->validData);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('monthly_salary', $validator->errors()->toArray());
    }

    public function test_date_of_birth_must_be_before_today(): void
    {
        $this->validData['date_of_birth'] = now()->addDays(10)->format('Y-m-d');
        $validator = $this->validate($this->validData);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('date_of_birth', $validator->errors()->toArray());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Optional Fields
    // ─────────────────────────────────────────────────────────────────────────

    public function test_optional_fields_can_be_null(): void
    {
        $this->validData['npwp'] = null;
        $this->validData['religion'] = null;
        $this->validData['marital_status'] = null;
        $this->validData['blood_type'] = null;
        $this->validData['team_id'] = null;

        $validator = $this->validate($this->validData);
        $this->assertTrue($validator->passes());
    }
}
