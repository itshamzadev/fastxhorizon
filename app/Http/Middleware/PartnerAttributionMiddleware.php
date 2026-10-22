<?php

namespace App\Http\Middleware;

use App\Models\PartnerProfile;
use App\Models\PartnerAttribution;
use App\Models\BusinessSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;

class PartnerAttributionMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (!Schema::hasTable('partner_profiles') || !Schema::hasTable('partner_attributions')) return $next($request);
        foreach ([['reseller', 'reseller'], ['ref', 'affiliate']] as [$parameter, $program]) {
            $code = trim((string) $request->query($parameter, ''));
            if ($code === '') continue;
            $profile = PartnerProfile::query()->where('program', $program)->where('code', $code)->where('status', 'approved')->first();
            if (!$profile) continue;
            $days = (int) (BusinessSetting::where('type', $program . '_attribution_days')->value('value')
                ?: config("fastxhorizon.partner_programs.{$program}.attribution_days", 30));
            session()->put("fastxhorizon_partner_{$program}", ['profile_id' => $profile->id, 'code' => $profile->code, 'captured_at' => now()->toIso8601String()]);
            $clickKey = "fastxhorizon_partner_click_{$program}_{$profile->id}";
            if (!session()->has($clickKey)) {
                PartnerAttribution::create(['partner_profile_id' => $profile->id, 'user_id' => auth('customer')->id(), 'program' => $program, 'code' => $profile->code, 'landing_url' => $request->fullUrl(), 'visitor_token_hash' => hash('sha256', session()->getId()), 'first_at' => now(), 'last_at' => now(), 'expires_at' => now()->addDays($days)]);
                session()->put($clickKey, true);
            }
            Cookie::queue(cookie("fastxhorizon_{$program}", $profile->code, $days * 24 * 60));
            break;
        }
        return $next($request);
    }
}
