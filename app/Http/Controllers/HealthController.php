<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $db = $this->periksa(fn () => DB::select('select 1'));
        $redis = $this->periksa(fn () => Redis::connection()->ping());

        return response()->json([
            'app' => config('app.name'),
            'versi' => config('app.version'),
            'db' => $db ? 'ok' : 'gagal',
            'redis' => $redis ? 'ok' : 'gagal',
        ], $db && $redis ? 200 : 503);
    }

    private function periksa(callable $cek): bool
    {
        try {
            $cek();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
