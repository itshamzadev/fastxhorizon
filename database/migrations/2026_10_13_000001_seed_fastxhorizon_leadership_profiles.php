<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('company_leadership_profiles') && !Schema::hasColumn('company_leadership_profiles', 'portfolio_url')) {
            Schema::table('company_leadership_profiles', function (Blueprint $table): void {
                $table->string('portfolio_url')->nullable()->after('bio');
            });
        }

        if (!Schema::hasTable('company_leadership_profiles')) return;

        $profiles = [
            ['name' => 'Muhammad Younas', 'role' => 'CEO & Director', 'bio' => 'Leads Fast X Horizon’s overall business direction, marketplace growth, and company operations with a focus on building a trusted and scalable commerce platform.', 'portfolio_url' => null, 'photo' => 'public/assets/front-end/img/leadership/muhammad-younas-demo.webp', 'display_order' => 1],
            ['name' => 'Muhammad Hamza', 'role' => 'Director & Manager', 'bio' => 'Supports Fast X Horizon’s management, technology direction, and digital operations, helping shape the platform’s growth and long-term product vision.', 'portfolio_url' => 'https://itshamzadev.com', 'photo' => 'public/assets/front-end/img/leadership/muhammad-hamza-demo.webp', 'display_order' => 2],
        ];

        foreach ($profiles as $profile) {
            DB::table('company_leadership_profiles')->updateOrInsert(
                ['name' => $profile['name']],
                array_merge($profile, ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]),
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('company_leadership_profiles')) return;
        DB::table('company_leadership_profiles')->whereIn('name', ['Muhammad Younas', 'Muhammad Hamza'])->delete();
        if (Schema::hasColumn('company_leadership_profiles', 'portfolio_url')) {
            Schema::table('company_leadership_profiles', fn (Blueprint $table) => $table->dropColumn('portfolio_url'));
        }
    }
};
