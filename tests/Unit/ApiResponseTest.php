<?php

namespace Tests\Unit;

use App\Enums\ResponseCode\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_internal_server_error_response_does_not_expose_exception_details(): void
    {
        $response = TestResponse::fromBaseResponse(ApiResponse::error(
            'SQLSTATE[HY000] connection refused',
            ['trace' => 'sensitive stack trace'],
            HttpStatusCode::INTERNAL_SERVER_ERROR
        ));

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => __('crud.server_error'),
            'errors' => [],
        ]);
    }
}
