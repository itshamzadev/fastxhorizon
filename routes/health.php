<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('health', static function () {
    return response()->json(['status' => 'ok']);
});

Route::get('ready', static function () {
    try {
        DB::connection()->getPdo();

        $redisRequired = (bool) config('fastxhorizon.readiness.redis_required')
            || config('cache.default') === 'redis'
            || config('queue.default') === 'redis'
            || config('session.driver') === 'redis';

        if ($redisRequired) {
            Redis::connection()->ping();
        }

        return response()->json(['status' => 'ready']);
    } catch (\Throwable $exception) {
        report($exception);

        return response()->json(['status' => 'not_ready'], 503);
    }
});
