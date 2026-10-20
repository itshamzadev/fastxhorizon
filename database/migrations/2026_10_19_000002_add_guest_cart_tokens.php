<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('guest_users', 'guest_token')) {
            Schema::table('guest_users', function (Blueprint $table) {
                $table->string('guest_token', 80)->nullable()->unique()->after('id');
                $table->timestamp('expires_at')->nullable()->after('fcm_token');
            });
        }

        DB::table('guest_users')->whereNull('guest_token')->orderBy('id')->eachById(function ($guest) {
            DB::table('guest_users')->where('id', $guest->id)->update([
                'guest_token' => Str::random(64),
                'expires_at' => now()->addDays(90),
            ]);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('guest_users', 'guest_token')) {
            Schema::table('guest_users', function (Blueprint $table) {
                $table->dropUnique(['guest_token']);
                $table->dropColumn(['guest_token', 'expires_at']);
            });
        }
    }
};
