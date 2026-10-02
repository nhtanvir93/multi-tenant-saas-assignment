<?php

declare(strict_types=1);

use App\Exceptions\TenantContextMissingException;
use App\Exceptions\TenantMismatchException;
use Tests\Support\InteractsWithTenantProbes;
use Tests\Support\TenantProbe;

uses(InteractsWithTenantProbes::class);

beforeEach(fn () => $this->createProbeTable());

test('queries only return rows of the active tenant', function () {
    $this->probe(1, 'a1');
    $this->probe(1, 'a2');
    $this->probe(2, 'b1');

    $names = $this->asTenant(1, fn () => TenantProbe::orderBy('id')->pluck('name')->all());
    $count = $this->asTenant(2, fn () => TenantProbe::count());

    expect($names)->toBe(['a1', 'a2'])
        ->and($count)->toBe(1);
});

test('find returns null for another tenants id', function () {
    $foreign = $this->probe(2, 'b1');

    expect($this->asTenant(1, fn () => TenantProbe::find($foreign->id)))->toBeNull();
});

test('create fills company_id from the tenant context', function () {
    $row = $this->asTenant(5, fn () => TenantProbe::create(['name' => 'x']));

    expect($row->company_id)->toBe(5)
        ->and(TenantProbe::withoutTenancy()->find($row->id)->company_id)->toBe(5);
});

test('company_id is not mass assignable', function () {
    expect((new TenantProbe)->isFillable('company_id'))->toBeFalse();
});

test('create rejects a company_id of another tenant', function () {
    $this->asTenant(1, fn () => TenantProbe::unguarded(
        fn () => TenantProbe::create(['name' => 'x', 'company_id' => 2])
    ));
})->throws(TenantMismatchException::class);

test('company_id cannot be changed on update', function () {
    $row = $this->probe(1, 'a');

    $this->asTenant(1, function () use ($row) {
        $fresh = TenantProbe::findOrFail($row->id);
        $fresh->company_id = 2;
        $fresh->save();
    });
})->throws(TenantMismatchException::class);

test('queries fail closed without a tenant context', function () {
    TenantProbe::query()->get();
})->throws(TenantContextMissingException::class);

test('creates fail closed without a tenant context', function () {
    TenantProbe::create(['name' => 'x']);
})->throws(TenantContextMissingException::class);

test('withoutTenancy is the explicit opt-out and sees every tenant', function () {
    $this->probe(1, 'a');
    $this->probe(2, 'b');

    expect(TenantProbe::withoutTenancy()->count())->toBe(2);
});
