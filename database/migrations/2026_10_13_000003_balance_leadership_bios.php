<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('company_leadership_profiles')->where('name', 'Muhammad Younas')->update([
            'bio' => 'Leads Fast X Horizon’s overall business direction, marketplace growth, strategic planning, and company operations, with a focus on building a trusted and scalable commerce platform.',
            'updated_at' => now(),
        ]);
        DB::table('company_leadership_profiles')->where('name', 'Muhammad Hamza')->update([
            'bio' => 'Leads Fast X Horizon’s technology direction, platform development, digital systems, and technical operations, with a focus on building a reliable and scalable marketplace experience.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('company_leadership_profiles')->where('name', 'Muhammad Younas')->update([
            'bio' => 'Leads Fast X Horizon’s overall business direction, marketplace growth, and company operations with a focus on building a trusted and scalable commerce platform.',
            'updated_at' => now(),
        ]);
        DB::table('company_leadership_profiles')->where('name', 'Muhammad Hamza')->update([
            'bio' => 'Leads Fast X Horizon’s technology and digital operations, overseeing platform development, technical systems, and the technology that supports the company’s day-to-day marketplace operations.',
            'updated_at' => now(),
        ]);
    }
};
