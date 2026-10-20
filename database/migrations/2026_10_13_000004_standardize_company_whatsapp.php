<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('business_settings')->updateOrInsert(
            ['type' => 'company_phone'],
            ['value' => '+923265680129', 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('business_settings')->updateOrInsert(
            ['type' => 'company_public_phone'],
            ['value' => '+923265680129', 'updated_at' => now(), 'created_at' => now()],
        );

        $whatsapp = DB::table('business_settings')->where('type', 'whatsapp')->value('value');
        $config = json_decode((string) $whatsapp, true);
        if (!is_array($config)) $config = ['status' => 1];
        $config['phone'] = '923265680129';
        DB::table('business_settings')->updateOrInsert(
            ['type' => 'whatsapp'],
            ['value' => json_encode($config), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        // The previous company number is installation-specific and is not restored automatically.
    }
};
