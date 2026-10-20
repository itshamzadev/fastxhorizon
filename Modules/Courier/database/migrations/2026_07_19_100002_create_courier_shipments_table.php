<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('consignment_id')->nullable()->index();
            $table->string('host_order_reference')->nullable()->index();
            $table->string('tracking_code')->nullable();
            $table->string('status')->default('pending')->index();
            $table->decimal('delivery_fee', 12, 2)->nullable();
            $table->decimal('cod_amount', 12, 2)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'consignment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_shipments');
    }
};
