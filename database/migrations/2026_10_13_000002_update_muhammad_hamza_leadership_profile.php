<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('company_leadership_profiles')
            ->where('name', 'Muhammad Hamza')
            ->update([
                'role' => 'Head of Technology & Operations',
                'bio' => 'Leads Fast X Horizon’s technology and digital operations, overseeing platform development, technical systems, and the technology that supports the company’s day-to-day marketplace operations.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('company_leadership_profiles')
            ->where('name', 'Muhammad Hamza')
            ->update([
                'role' => 'Director & Manager',
                'bio' => 'Supports Fast X Horizon’s management, technology direction, and digital operations, helping shape the platform’s growth and long-term product vision.',
                'updated_at' => now(),
            ]);
    }
};
