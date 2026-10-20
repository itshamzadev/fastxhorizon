<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courier_shipments', function (Blueprint $table) {
            $table->timestamp('superseded_at')->nullable()->after('status')->index();
            $table->json('dispatch_details')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('courier_shipments', function (Blueprint $table) {
            $table->dropIndex(['superseded_at']);
            $table->dropColumn(['superseded_at', 'dispatch_details']);
        });
    }
};
