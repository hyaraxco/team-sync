<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ResponseHelper;
use Tests\TestCase;

class ResponseHelperTest extends TestCase
{
    public function test_json_response_wraps_payload_with_expected_shape(): void
    {
        $response = ResponseHelper::jsonResponse(true, 'All good', ['foo' => 'bar'], 201);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame([
            'success' => true,
            'message' => 'All good',
            'data' => ['foo' => 'bar'],
        ], $response->getData(true));
    }

    public function test_json_response_supports_null_data(): void
    {
        $response = ResponseHelper::jsonResponse(false, 'No data', null, 404);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'message' => 'No data',
            'data' => null,
        ], $response->getData(true));
    }
}
