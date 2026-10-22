<?php

namespace App\Http\Middleware;

use App\Services\PartnerProgramService;
use Closure;
use Illuminate\Http\Request;

class PartnerProgramMiddleware
{
    public function handle(Request $request, Closure $next, string $program): mixed
    {
        $profile = app(PartnerProgramService::class)->profileForUser(auth('customer')->user(), $program);
        if (!$profile || $profile->status !== 'approved') return redirect()->route("{$program}.apply")->with('error', 'Your partner account is not approved.');
        $request->attributes->set('partner_profile', $profile);
        return $next($request);
    }
}
