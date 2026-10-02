<?php

declare(strict_types=1);

use App\Exceptions\TenantContextMissingException;
use App\Support\Tenancy\TenantContext;

test('id fails closed when no tenant is active', function () {
    (new TenantContext)->id();
})->throws(TenantContextMissingException::class);

test('an empty context reports no tenant', function () {
    $context = new TenantContext;

    expect($context->has())->toBeFalse()
        ->and($context->idOrNull())->toBeNull();
});

test('runAs exposes the tenant only inside the callback and returns its value', function () {
    $context = new TenantContext;

    $inside = $context->runAs(7, fn () => $context->id());

    expect($inside)->toBe(7)
        ->and($context->has())->toBeFalse();
});

test('runAs restores the previous tenant when nested', function () {
    $context = new TenantContext;

    $seen = $context->runAs(1, function () use ($context) {
        $inner = $context->runAs(2, fn () => $context->id());

        return [$inner, $context->id()];
    });

    expect($seen)->toBe([2, 1]);
});

test('runAs restores the context when the callback throws', function () {
    $context = new TenantContext;

    try {
        $context->runAs(7, fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
        // expected
    }

    expect($context->has())->toBeFalse();
});
