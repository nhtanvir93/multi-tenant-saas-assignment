<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_reports_database_and_redis_up(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Healthy',
                'data' => [
                    'status' => 'ok',
                    'checks' => ['database' => 'up', 'redis' => 'up'],
                ],
            ]);
    }

    public function test_health_is_public_and_returns_json_without_accept_header(): void
    {
        $this->get('/api/v1/health')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('data.status', 'ok');
    }
}
