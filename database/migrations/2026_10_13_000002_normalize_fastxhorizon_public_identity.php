<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('business_settings')->updateOrInsert(
            ['type' => 'company_copyright_text'],
            ['value' => '© FastXHorizon. All rights reserved.', 'updated_at' => now(), 'created_at' => now()]
        );

        DB::table('shops')
            ->where('author_type', 'admin')
            ->where('name', 'Fast X Horizon')
            ->update(['name' => 'FastXHorizon', 'updated_at' => now()]);

        if (DB::getSchemaBuilder()->hasTable('flash_deals')) {
            DB::statement("UPDATE flash_deals SET title = REPLACE(title, 'Fast X Horizon', 'FastXHorizon'), updated_at = ? WHERE title LIKE ?", [now(), '%Fast X Horizon%']);
        }

        if (DB::getSchemaBuilder()->hasTable('email_templates')) {
            foreach (['title', 'body', 'footer_text', 'copyright_text'] as $column) {
                DB::statement("UPDATE email_templates SET {$column} = REPLACE({$column}, 'Fast X Horizon', 'FastXHorizon'), updated_at = ? WHERE {$column} LIKE ?", [now(), '%Fast X Horizon%']);
            }
        }
    }

    public function down(): void
    {
        // Public identity normalization is intentionally not reversed.
    }
};
