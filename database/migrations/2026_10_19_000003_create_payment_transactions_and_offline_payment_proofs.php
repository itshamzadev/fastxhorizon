<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_transactions')) {
            Schema::create('payment_transactions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->index();
                $table->string('provider', 64)->index();
                $table->string('provider_reference', 191)->nullable();
                $table->string('provider_transaction_id', 191)->nullable();
                $table->decimal('amount', 20, 4);
                $table->string('currency', 12);
                $table->string('status', 32)->index();
                $table->string('idempotency_key', 191)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'provider_reference'], 'payment_provider_reference_unique');
                $table->unique(['provider', 'provider_transaction_id'], 'payment_provider_transaction_unique');
                $table->index(['order_id', 'provider', 'status'], 'payment_order_provider_status_index');
            });
        }

        if (!Schema::hasTable('offline_payment_proofs')) {
            Schema::create('offline_payment_proofs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('offline_method_id')->nullable()->index();
                $table->string('transaction_reference', 191);
                $table->decimal('amount_claimed', 20, 4);
                $table->string('currency', 12);
                $table->string('proof_disk', 64)->default('local');
                $table->string('proof_path', 512);
                $table->string('proof_mime', 128);
                $table->unsignedBigInteger('proof_size')->default(0);
                $table->string('file_hash', 64);
                $table->text('note')->nullable();
                $table->string('status', 32)->default('pending')->index();
                $table->unsignedBigInteger('reviewed_by')->nullable()->index();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
                $table->unique(['order_id', 'file_hash'], 'offline_proof_order_file_unique');
                $table->index(['order_id', 'status'], 'offline_proof_order_status_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_payment_proofs');
        Schema::dropIfExists('payment_transactions');
    }
};
