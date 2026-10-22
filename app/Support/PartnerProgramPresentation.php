<?php

namespace App\Support;

use App\Models\BusinessSetting;
use App\Models\PartnerProfile;
use App\Models\PartnerApplication;
use Illuminate\Support\Facades\Schema;

final class PartnerProgramPresentation
{
    public static function enabled(string $program): bool
    {
        if (!Schema::hasTable('partner_profiles')) return false;
        $value = BusinessSetting::where('type', $program . '_enabled')->value('value');
        return $value === null
            ? (bool) config("fastxhorizon.partner_programs.{$program}.enabled", false)
            : (bool) (int) $value;
    }

    public static function applicationsOpen(string $program): bool
    {
        if (!self::enabled($program)) return false;
        $value = BusinessSetting::where('type', $program . '_applications_enabled')->value('value');
        return $value === null
            ? (bool) config("fastxhorizon.partner_programs.{$program}.applications_enabled", false)
            : (bool) (int) $value;
    }

    public static function profile(string $program): ?PartnerProfile
    {
        if (!auth('customer')->check() || !Schema::hasTable('partner_profiles')) return null;
        return PartnerProfile::where(['user_id' => auth('customer')->id(), 'program' => $program])->first();
    }

    public static function application(string $program): ?PartnerApplication
    {
        if (!auth('customer')->check() || !Schema::hasTable('partner_applications')) return null;
        return PartnerApplication::where(['user_id' => auth('customer')->id(), 'program' => $program])->latest()->first();
    }
}
