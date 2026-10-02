<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->probe(fn () => DB::select('select 1')),
            'redis' => $this->probe(fn () => Redis::connection()->ping()),
        ];

        if (in_array('down', $checks, true)) {
            return ApiResponse::error('Service degraded.', 'SERVICE_UNAVAILABLE', 503, ['checks' => $checks]);
        }

        return ApiResponse::success(['status' => 'ok', 'checks' => $checks], 'Healthy');
    }

    private function probe(Closure $check): string
    {
        try {
            $check();

            return 'up';
        } catch (Throwable) {
            return 'down';
        }
    }
}
