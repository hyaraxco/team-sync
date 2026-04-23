<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_format()
    {
        $this->seed(\Database\Seeders\MinimalPayrollE2ESeeder::class);
        $user = User::where('email', 'tasyia@teamsync.com')->first();
        $this->actingAs($user);
        $response = $this->get('/api/v1/staff-members');
        dump($response->json());
        $this->assertTrue(true);
    }
}
