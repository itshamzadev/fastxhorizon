<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courier_provider_settings', function (Blueprint $table) {
            $table->string('owner_type', 20)->default('platform')->after('id');
            $table->unsignedBigInteger('owner_id')->default(0)->after('owner_type');
        });

        Schema::table('courier_provider_settings', function (Blueprint $table) {
            $table->dropUnique('courier_provider_settings_provider_unique');
            $table->unique(['owner_type', 'owner_id', 'provider'], 'courier_provider_settings_owner_provider_unique');
            $table->index(['owner_type', 'owner_id'], 'courier_provider_settings_owner_index');
        });
    }

    public function down(): void
    {
        DB::table('courier_provider_settings')->where('owner_type', '!=', 'platform')->delete();

        Schema::table('courier_provider_settings', function (Blueprint $table) {
            $table->dropUnique('courier_provider_settings_owner_provider_unique');
            $table->dropIndex('courier_provider_settings_owner_index');
            $table->unique('provider', 'courier_provider_settings_provider_unique');
        });

        Schema::table('courier_provider_settings', function (Blueprint $table) {
            $table->dropColumn(['owner_type', 'owner_id']);
        });
    }
};
