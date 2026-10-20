<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('business_pages')) return;
        Schema::table('business_pages', function (Blueprint $table) {
            $columns = Schema::getColumnListing('business_pages');
            if (!in_array('hero_image', $columns, true)) $table->string('hero_image')->nullable();
            if (!in_array('supporting_image_1', $columns, true)) $table->string('supporting_image_1')->nullable();
            if (!in_array('supporting_image_2', $columns, true)) $table->string('supporting_image_2')->nullable();
        });

        $defaults = [
            'about-us' => ['about-hero.webp', 'about-intro.webp', 'about-global.webp'],
            'terms-and-conditions' => ['terms-hero.webp', null, null],
            'privacy-policy' => ['privacy-hero.webp', null, null],
            'refund-policy' => ['refund-hero.webp', null, null],
            'return-policy' => ['return-hero.webp', null, null],
            'cancellation-policy' => ['cancellation-hero.webp', null, null],
        ];
        foreach ($defaults as $slug => $images) {
            DB::table('business_pages')->where('slug', $slug)->update([
                'hero_image' => 'theme:company-policy/' . $images[0],
                'supporting_image_1' => $images[1] ? 'theme:company-policy/' . $images[1] : null,
                'supporting_image_2' => $images[2] ? 'theme:company-policy/' . $images[2] : null,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('business_pages')) return;
        Schema::table('business_pages', function (Blueprint $table) {
            foreach (['hero_image', 'supporting_image_1', 'supporting_image_2'] as $column) {
                if (Schema::hasColumn('business_pages', $column)) $table->dropColumn($column);
            }
        });
    }
};
