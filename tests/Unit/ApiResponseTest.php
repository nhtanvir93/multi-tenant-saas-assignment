<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ApiResponse;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_envelope_omits_meta_when_empty(): void
    {
        $response = ApiResponse::success(['id' => 1], 'Fetched');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['success' => true, 'message' => 'Fetched', 'data' => ['id' => 1]],
            $response->getData(true)
        );
    }

    public function test_success_envelope_includes_meta_when_given(): void
    {
        $body = ApiResponse::success([], 'OK', 200, ['page' => 1])->getData(true);

        $this->assertSame(['page' => 1], $body['meta']);
    }

    public function test_created_uses_201(): void
    {
        $this->assertSame(201, ApiResponse::created(['id' => 5])->getStatusCode());
    }

    public function test_no_content_uses_204_and_empty_body(): void
    {
        $response = ApiResponse::noContent();

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function test_error_envelope_omits_details_when_empty(): void
    {
        $response = ApiResponse::error('Nope.', 'FORBIDDEN', 403);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(
            ['success' => false, 'message' => 'Nope.', 'error' => ['code' => 'FORBIDDEN']],
            $response->getData(true)
        );
    }

    public function test_error_envelope_includes_details_and_headers(): void
    {
        $response = ApiResponse::error('Slow down.', 'RATE_LIMITED', 429, ['retry_after' => 30], ['Retry-After' => '30']);

        $this->assertSame(['retry_after' => 30], $response->getData(true)['error']['details']);
        $this->assertSame('30', $response->headers->get('Retry-After'));
    }
}
