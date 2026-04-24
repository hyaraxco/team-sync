<?php

namespace Tests\Feature\Feature\Performance;

use Tests\TestCase;

class GenerateReviewsFeatureTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
