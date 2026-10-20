<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('frontend_checkout_idempotencies')) {
            return;
        }

        Schema::create('frontend_checkout_idempotencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('idempotency_key', 191);
            $table->string('request_hash', 64);
            $table->string('status', 24)->default('processing');
            $table->text('order_ids')->nullable();
            $table->longText('response_json')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'idempotency_key'], 'frontend_checkout_customer_key_unique');
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_checkout_idempotencies');
    }
};
