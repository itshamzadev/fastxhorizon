<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courier_shipments', function (Blueprint $table) {
            $table->dropUnique(['provider', 'consignment_id']);
            $table->index(['provider', 'consignment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('courier_shipments', function (Blueprint $table) {
            $table->dropIndex(['provider', 'consignment_id']);
            $table->unique(['provider', 'consignment_id']);
        });
    }
};
