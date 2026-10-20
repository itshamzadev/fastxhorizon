<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('offline_payment_proof_events')) {
            return;
        }

        Schema::create('offline_payment_proof_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proof_id')->index();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_type', 32)->default('customer');
            $table->string('event', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['proof_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_payment_proof_events');
    }
};
