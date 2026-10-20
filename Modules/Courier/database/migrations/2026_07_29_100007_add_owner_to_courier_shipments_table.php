<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courier_shipments', function (Blueprint $table) {
            $table->string('owner_type', 20)->default('platform')->after('id');
            $table->unsignedBigInteger('owner_id')->default(0)->after('owner_type');
            $table->index(['owner_type', 'owner_id'], 'courier_shipments_owner_index');
        });
    }

    public function down(): void
    {
        Schema::table('courier_shipments', function (Blueprint $table) {
            $table->dropIndex('courier_shipments_owner_index');
            $table->dropColumn(['owner_type', 'owner_id']);
        });
    }
};
