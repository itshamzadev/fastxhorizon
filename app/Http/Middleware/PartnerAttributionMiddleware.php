<?php

namespace App\Http\Middleware;

use App\Models\PartnerProfile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;

class PartnerAttributionMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (!Schema::hasTable('partner_profiles')) return $next($request);
        foreach ([['reseller', 'reseller'], ['ref', 'affiliate']] as [$parameter, $program]) {
            $code = trim((string) $request->query($parameter, ''));
            if ($code === '') continue;
            $profile = PartnerProfile::query()->where('program', $program)->where('code', $code)->where('status', 'approved')->first();
            if (!$profile) continue;
            session()->put("fastxhorizon_partner_{$program}", ['profile_id' => $profile->id, 'code' => $profile->code, 'captured_at' => now()->toIso8601String()]);
            Cookie::queue(cookie("fastxhorizon_{$program}", $profile->code, (int) config("fastxhorizon.partner_programs.{$program}.attribution_days", 30) * 24 * 60));
            break;
        }
        return $next($request);
    }
}
