<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->decimal('balance_before', 40, 20)->nullable()->after('balance');
            $table->decimal('balance_after', 40, 20)->nullable()->after('balance_before');
            $table->string('currency_code', 12)->nullable()->after('payment_method');
            $table->string('source', 64)->nullable()->after('currency_code');
            $table->string('status', 32)->default('completed')->after('source');
            $table->string('idempotency_key', 191)->nullable()->after('status');
            $table->unsignedBigInteger('related_order_id')->nullable()->after('idempotency_key');
            $table->unsignedBigInteger('related_refund_id')->nullable()->after('related_order_id');
            $table->unsignedBigInteger('created_by')->nullable()->after('related_refund_id');
            $table->text('description')->nullable()->after('created_by');
            $table->index(['user_id', 'idempotency_key'], 'wallet_transactions_user_idempotency_index');
            $table->index(['related_order_id', 'related_refund_id'], 'wallet_transactions_related_records_index');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->dropIndex('wallet_transactions_user_idempotency_index');
            $table->dropIndex('wallet_transactions_related_records_index');
            $table->dropColumn([
                'balance_before',
                'balance_after',
                'currency_code',
                'source',
                'status',
                'idempotency_key',
                'related_order_id',
                'related_refund_id',
                'created_by',
                'description',
            ]);
        });
    }
};
