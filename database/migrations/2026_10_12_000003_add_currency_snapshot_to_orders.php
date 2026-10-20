<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'base_currency_code')) Schema::table('orders', fn (Blueprint $table) => $table->string('base_currency_code', 3)->nullable()->after('order_amount'));
        if (!Schema::hasColumn('orders', 'display_currency_code')) Schema::table('orders', fn (Blueprint $table) => $table->string('display_currency_code', 3)->nullable()->after('base_currency_code'));
        if (!Schema::hasColumn('orders', 'currency_exchange_rate')) Schema::table('orders', fn (Blueprint $table) => $table->decimal('currency_exchange_rate', 24, 12)->nullable()->after('display_currency_code'));
        if (!Schema::hasColumn('orders', 'display_order_amount')) Schema::table('orders', fn (Blueprint $table) => $table->decimal('display_order_amount', 40, 20)->nullable()->after('currency_exchange_rate'));
    }

    public function down(): void
    {
        foreach (['base_currency_code', 'display_currency_code', 'currency_exchange_rate', 'display_order_amount'] as $column) {
            if (Schema::hasColumn('orders', $column)) Schema::table('orders', fn (Blueprint $table) => $table->dropColumn($column));
        }
    }
};
