<?php

declare(strict_types=1);

use App\Exceptions\ApiExceptionRenderer;
use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API only: always answer JSON, whatever the client sends in Accept
        $middleware->api(prepend: [ForceJsonResponse::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport(BusinessRuleException::class);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e): bool => $request->is('api/*') || $request->expectsJson()
        );

        // One envelope for every error: {success:false, message, error:{code, details?}}
        $exceptions->render(
            fn (Throwable $e, Request $request) => (new ApiExceptionRenderer())($e, $request)
        );
    })->create();
