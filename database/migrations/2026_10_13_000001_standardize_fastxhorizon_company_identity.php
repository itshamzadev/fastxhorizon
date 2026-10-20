<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            'company_name' => 'FastXHorizon',
            'brand_name' => 'FastXHorizon',
            'legal_company_name' => 'FASTXHORIZON (PRIVATE) LIMITED',
            'legal_name_short' => 'FASTXHORIZON (Pvt.) Ltd.',
            'company_status' => 'Reserved',
        ];

        foreach ($settings as $type => $value) {
            DB::table('business_settings')->updateOrInsert(
                ['type' => $type],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('business_settings')->whereIn('type', ['brand_name', 'legal_name_short', 'company_status'])->delete();
    }
};
