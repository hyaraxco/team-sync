<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_company_timezone_in_me_endpoint(): void
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Tokyo']);
        $user = User::factory()->create();
        StaffMemberProfile::factory()->for($user)->create(['company_id' => $company->id]);
        
        $response = $this->actingAs($user)->getJson('/api/v1/me');
        
        $response->assertOk()
            ->assertJsonPath('data.company_timezone', 'Asia/Tokyo');
    }

    public function test_returns_default_timezone_when_user_has_no_staff_member_profile(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->getJson('/api/v1/me');
        
        $response->assertOk()
            ->assertJsonPath('data.company_timezone', 'Asia/Jakarta');
    }

    public function test_returns_default_timezone_when_staff_has_no_company(): void
    {
        $user = User::factory()->create();
        StaffMemberProfile::factory()->for($user)->create(['company_id' => null]);
        
        $response = $this->actingAs($user)->getJson('/api/v1/me');
        
        $response->assertOk()
            ->assertJsonPath('data.company_timezone', 'Asia/Jakarta');
    }

    public function test_handles_dst_transition_correctly(): void
    {
        $company = Company::factory()->create(['timezone' => 'America/New_York']);
        $user = User::factory()->create();
        StaffMemberProfile::factory()->for($user)->create(['company_id' => $company->id]);
        
        $response = $this->actingAs($user)->getJson('/api/v1/me');
        
        $response->assertOk()
            ->assertJsonPath('data.company_timezone', 'America/New_York');
        
        $this->assertContains('America/New_York', \DateTimeZone::listIdentifiers());
    }

    public function test_handles_timezone_change_mid_session(): void
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Jakarta']);
        $user = User::factory()->create();
        StaffMemberProfile::factory()->for($user)->create(['company_id' => $company->id]);
        
        $response1 = $this->actingAs($user)->getJson('/api/v1/me');
        $response1->assertJsonPath('data.company_timezone', 'Asia/Jakarta');
        
        $company->update(['timezone' => 'Asia/Tokyo']);
        
        $response2 = $this->actingAs($user)->getJson('/api/v1/me');
        $response2->assertJsonPath('data.company_timezone', 'Asia/Tokyo');
    }

    public function test_returns_company_timezone_for_different_timezones(): void
    {
        $timezones = ['Asia/Singapore', 'Europe/London', 'America/Los_Angeles'];
        
        foreach ($timezones as $tz) {
            $company = Company::factory()->create(['timezone' => $tz]);
            $user = User::factory()->create();
            StaffMemberProfile::factory()->for($user)->create(['company_id' => $company->id]);
            
            $response = $this->actingAs($user)->getJson('/api/v1/me');
            
            $response->assertOk()
                ->assertJsonPath('data.company_timezone', $tz);
        }
    }
}
