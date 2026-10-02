<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Maps every exception raised under /api to the standard error envelope.
 * Returns null for non-API requests so Laravel's default handling applies.
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! ($request->is('api/*') || $request->expectsJson())) {
            return null;
        }

        return match (true) {
            $e instanceof BusinessRuleException => ApiResponse::error($e->getMessage(), $e->errorCode(), $e->status(), $e->details()),
            $e instanceof ValidationException => ApiResponse::error('Validation failed.', 'VALIDATION_ERROR', $e->status, $e->errors()),
            $e instanceof AuthenticationException => ApiResponse::error('Unauthenticated.', 'UNAUTHENTICATED', 401),
            // Laravel has already converted ModelNotFound -> 404 and AuthorizationException -> 403 here
            $e instanceof HttpExceptionInterface => $this->fromHttpException($e),
            default => $this->serverError($e),
        };
    }

    private function fromHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        [$code, $message] = match (true) {
            $status === 401 => ['UNAUTHENTICATED', 'Unauthenticated.'],
            $status === 403 => ['FORBIDDEN', 'This action is unauthorized.'],
            $status === 404 => ['NOT_FOUND', 'Resource not found.'],
            $status === 405 => ['METHOD_NOT_ALLOWED', 'Method not allowed.'],
            $status === 429 => ['RATE_LIMITED', 'Too many requests.'],
            $status >= 500 => ['INTERNAL_ERROR', 'Server error.'],
            default => ['HTTP_ERROR', 'Request could not be processed.'],
        };

        return ApiResponse::error($message, $code, $status, [], $e->getHeaders());
    }

    private function serverError(Throwable $e): JsonResponse
    {
        // Internals are only exposed when APP_DEBUG=true (never in production)
        $details = config('app.debug')
            ? ['exception' => $e::class, 'message' => $e->getMessage()]
            : [];

        return ApiResponse::error('Server error.', 'INTERNAL_ERROR', 500, $details);
    }
}
