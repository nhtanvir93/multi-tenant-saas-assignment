<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorEnvelopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Test-only routes that trigger each kind of error
        Route::middleware('api')->prefix('api/v1/_test')->group(function (): void {
            Route::get('rule', fn () => throw new BusinessRuleException('Plan limit reached.', 'PLAN_LIMIT_REACHED', 403, ['resource' => 'users']));
            Route::post('validate', fn (Request $request) => $request->validate(['email' => ['required', 'email']]));
            Route::get('boom', fn () => throw new RuntimeException('secret internals'));
            Route::get('protected', fn () => 'ok')->middleware('auth:sanctum');
        });
    }

    public function test_business_rule_exception_is_rendered_with_its_code_status_and_details(): void
    {
        $this->getJson('/api/v1/_test/rule')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Plan limit reached.',
                'error' => ['code' => 'PLAN_LIMIT_REACHED', 'details' => ['resource' => 'users']],
            ]);
    }

    public function test_validation_errors_use_the_envelope_with_field_details(): void
    {
        $this->postJson('/api/v1/_test/validate', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['email']]]);
    }

    public function test_validation_returns_json_even_when_client_sends_no_accept_header(): void
    {
        // Without ForceJsonResponse a browser-style POST would be redirected (302)
        $this->post('/api/v1/_test/validate', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_unauthenticated_request_to_protected_route_returns_401_envelope(): void
    {
        $this->getJson('/api/v1/_test/protected')
            ->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'message' => 'Unauthenticated.',
                'error' => ['code' => 'UNAUTHENTICATED'],
            ]);
    }

    public function test_unknown_api_route_returns_404_envelope(): void
    {
        // plain get(): proves non-JSON clients also get the envelope under /api
        $this->get('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_wrong_http_method_returns_405_envelope(): void
    {
        $this->postJson('/api/v1/health')
            ->assertStatus(405)
            ->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED');
    }

    public function test_unexpected_exceptions_return_generic_500_without_leaking_internals(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/v1/_test/boom');

        $response->assertStatus(500)
            ->assertExactJson([
                'success' => false,
                'message' => 'Server error.',
                'error' => ['code' => 'INTERNAL_ERROR'],
            ]);
        $this->assertStringNotContainsString('secret internals', (string) $response->getContent());
    }
}
