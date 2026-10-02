<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The single place that defines the API response envelope.
 *
 * success: {"success": true,  "message": "...", "data": ..., "meta": {...}?}
 * error:   {"success": false, "message": "...", "error": {"code": "...", "details": {...}?}}
 */
final class ApiResponse
{
    /** @param array<string, mixed> $meta */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200, array $meta = []): JsonResponse
    {
        $body = ['success' => true, 'message' => $message, 'data' => $data];

        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    public static function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function noContent(): Response
    {
        return response()->noContent();
    }

    /**
     * @param array<string, mixed> $details
     * @param array<string, string> $headers
     */
    public static function error(string $message, string $code, int $status, array $details = [], array $headers = []): JsonResponse
    {
        $error = ['code' => $code];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return response()->json(
            ['success' => false, 'message' => $message, 'error' => $error],
            $status,
            $headers
        );
    }
}
