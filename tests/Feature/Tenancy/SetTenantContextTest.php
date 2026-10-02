<?php

declare(strict_types=1);

use App\Http\Middleware\SetTenantContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithTenantProbes;
use Tests\Support\TenantProbe;

uses(InteractsWithTenantProbes::class);

beforeEach(function () {
    $this->createProbeTable();

    // Mirrors the real API: the `api` group (which contains SubstituteBindings) wraps
    // auth + tenant context, so these tests also prove the middleware priority.
    Route::middleware('api')->prefix('api/v1/_probe')->group(function () {
        Route::middleware(['auth:sanctum', SetTenantContext::class])->group(function () {
            Route::get('context', fn () => response()->json([
                'company_id' => app(TenantContext::class)->idOrNull(),
            ]));
            Route::get('items', fn () => response()->json(TenantProbe::orderBy('id')->pluck('name')));
            Route::get('items/{probe}', fn (TenantProbe $probe) => response()->json(['name' => $probe->name]));
        });

        Route::get('unprotected', fn () => response()->json(TenantProbe::query()->get()));
    });
});

test('tenant context is a scoped singleton', function () {
    expect(app(TenantContext::class))->toBe(app(TenantContext::class));
});

test('tenant comes from the authenticated user', function () {
    $this->actingAs($this->tenantUser(42), 'sanctum')
        ->getJson('/api/v1/_probe/context')
        ->assertOk()
        ->assertJsonPath('company_id', 42);
});

test('tenant ids sent by the client are ignored', function () {
    $this->actingAs($this->tenantUser(42), 'sanctum')
        ->getJson('/api/v1/_probe/context?company_id=99', ['X-Company-Id' => '99'])
        ->assertOk()
        ->assertJsonPath('company_id', 42);
});

test('context is cleared when the request ends', function () {
    $this->actingAs($this->tenantUser(42), 'sanctum')->getJson('/api/v1/_probe/context')->assertOk();

    expect(app(TenantContext::class)->has())->toBeFalse();
});

test('unauthenticated requests get the 401 envelope', function () {
    $this->getJson('/api/v1/_probe/context')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

test('lists only the authenticated tenants rows', function () {
    $this->probe(1, 'a');
    $this->probe(2, 'b');

    $this->actingAs($this->tenantUser(1), 'sanctum')
        ->getJson('/api/v1/_probe/items')
        ->assertOk()
        ->assertExactJson(['a']);
});

test('route model binding resolves own rows', function () {
    $own = $this->probe(1, 'a');

    $this->actingAs($this->tenantUser(1), 'sanctum')
        ->getJson("/api/v1/_probe/items/{$own->id}")
        ->assertOk()
        ->assertJsonPath('name', 'a');
});

test('route model binding returns 404 for another tenants row', function () {
    $foreign = $this->probe(2, 'b');

    $this->actingAs($this->tenantUser(1), 'sanctum')
        ->getJson("/api/v1/_probe/items/{$foreign->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

test('a tenant query without the middleware fails closed with a generic 500', function () {
    $this->probe(1, 'a');

    $this->getJson('/api/v1/_probe/unprotected')
        ->assertStatus(500)
        ->assertJsonPath('error.code', 'INTERNAL_ERROR');
});
