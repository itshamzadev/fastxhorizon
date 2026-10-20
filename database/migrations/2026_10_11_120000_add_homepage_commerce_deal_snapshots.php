<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('flash_deal_products') && !Schema::hasColumn('flash_deal_products', 'funding_type')) {
            Schema::table('flash_deal_products', fn (Blueprint $table) => $table->string('funding_type', 20)->default('seller')->after('discount_type'));
        }

        if (Schema::hasTable('carts')) {
            Schema::table('carts', function (Blueprint $table) {
                if (!Schema::hasColumn('carts', 'flash_deal_id')) $table->unsignedBigInteger('flash_deal_id')->nullable()->after('discount');
                if (!Schema::hasColumn('carts', 'platform_discount')) $table->decimal('platform_discount', 18, 12)->default(0)->after('flash_deal_id');
                if (!Schema::hasColumn('carts', 'flash_deal_funding')) $table->string('flash_deal_funding', 20)->nullable()->after('platform_discount');
            });
        }

        if (Schema::hasTable('order_details')) {
            Schema::table('order_details', function (Blueprint $table) {
                if (!Schema::hasColumn('order_details', 'flash_deal_id')) $table->unsignedBigInteger('flash_deal_id')->nullable()->after('discount');
                if (!Schema::hasColumn('order_details', 'platform_discount')) $table->decimal('platform_discount', 18, 12)->default(0)->after('flash_deal_id');
                if (!Schema::hasColumn('order_details', 'seller_discount')) $table->decimal('seller_discount', 18, 12)->default(0)->after('platform_discount');
                if (!Schema::hasColumn('order_details', 'flash_deal_funding')) $table->string('flash_deal_funding', 20)->nullable()->after('seller_discount');
            });
        }
    }

    public function down(): void
    {
        foreach (['carts', 'order_details', 'flash_deal_products'] as $tableName) {
            if (!Schema::hasTable($tableName)) continue;
            $columns = $tableName === 'flash_deal_products'
                ? ['funding_type']
                : ($tableName === 'carts'
                    ? ['flash_deal_id', 'platform_discount', 'flash_deal_funding']
                    : ['flash_deal_id', 'platform_discount', 'seller_discount', 'flash_deal_funding']);
            $drop = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($tableName, $column)));
            if ($drop) Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($drop));
        }
    }
};
