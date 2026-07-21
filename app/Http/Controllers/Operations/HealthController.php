<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        $cacheSeconds = max(1, (int) config('security.health.cache_seconds', 10));
        $ready = Cache::remember('operations:health-ready', $cacheSeconds, fn (): bool => $this->checksPass());

        return response()->json(['status' => $ready ? 'ok' : 'unavailable'], $ready ? 200 : 503);
    }

    private function checksPass(): bool
    {
        try {
            DB::connection()->getPdo();

            Cache::put('operations:readiness-cache-probe', 'ok', now()->addMinute());

            $privateDisk = Storage::disk((string) config('storefront-media.private_disk', 'private'));
            $publicDisk = Storage::disk((string) config('storefront-media.public_disk', 'public'));

            $privateDisk->exists('.');
            $publicDisk->exists('.');

            if (! $this->heartbeatsPass()) {
                return false;
            }

            return ! app()->isDownForMaintenance();
        } catch (\Throwable) {
            return false;
        }
    }

    private function heartbeatsPass(): bool
    {
        if (! app()->isProduction() || ! (bool) config('security.health.require_heartbeats_in_production', true)) {
            return true;
        }

        return $this->heartbeatIsFresh(
            key: 'operations:queue-heartbeat',
            staleSeconds: (int) config('security.health.queue_heartbeat_stale_seconds', 180),
        ) && $this->heartbeatIsFresh(
            key: 'operations:scheduler-heartbeat',
            staleSeconds: (int) config('security.health.scheduler_heartbeat_stale_seconds', 180),
        );
    }

    private function heartbeatIsFresh(string $key, int $staleSeconds): bool
    {
        $value = Cache::get($key);

        if (! is_string($value) || $value === '') {
            return false;
        }

        try {
            return Carbon::parse($value)->greaterThan(now()->subSeconds($staleSeconds));
        } catch (\Throwable) {
            return false;
        }
    }
}
