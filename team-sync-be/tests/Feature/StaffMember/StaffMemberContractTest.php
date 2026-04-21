<?php

namespace Tests\Feature\StaffMember;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffMemberContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_members_endpoints_exist(): void
    {
        $this->getJson('/api/v1/staff-members')->assertStatus(401);
        $this->getJson('/api/v1/staff-members/statistics')->assertStatus(401);
    }
}
