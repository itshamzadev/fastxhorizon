<?php

namespace App\Http\Middleware;

use App\Services\FastXHorizonCurrencyService;
use Closure;
use Illuminate\Http\Request;

class ResolveFastXHorizonCurrency
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->is('admin/*') && !$request->is('seller/*') && !$request->is('vendor/*')) {
            app(FastXHorizonCurrencyService::class)->resolveAndApply();
        }
        return $next($request);
    }
}
