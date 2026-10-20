<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes that make the personalized recommendation scoring queries cheap. Without these,
 * the per-customer activity lookups full-scan carts/order_details/reviews/wishlists/orders
 * on every homepage load. Each index is added only if missing so the migration is re-runnable.
 */
return new class extends Migration {
    public function up(): void
    {
        $this->addIndexIfMissing('wishlists', ['customer_id', 'product_id'], 'idx_wishlists_customer_product');
        $this->addIndexIfMissing('carts', ['customer_id', 'product_id'], 'idx_carts_customer_product');
        $this->addIndexIfMissing('reviews', ['customer_id', 'product_id'], 'idx_reviews_customer_product');
        $this->addIndexIfMissing('order_details', ['product_id'], 'idx_order_details_product');
        $this->addIndexIfMissing('order_details', ['order_id'], 'idx_order_details_order');
        $this->addIndexIfMissing('orders', ['customer_id'], 'idx_orders_customer');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('wishlists', 'idx_wishlists_customer_product');
        $this->dropIndexIfExists('carts', 'idx_carts_customer_product');
        $this->dropIndexIfExists('reviews', 'idx_reviews_customer_product');
        $this->dropIndexIfExists('order_details', 'idx_order_details_product');
        $this->dropIndexIfExists('order_details', 'idx_order_details_order');
        $this->dropIndexIfExists('orders', 'idx_orders_customer');
    }

    private function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if (Schema::hasTable($table) && $this->indexExists($table, $name)) {
            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropIndex($name);
            });
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return !empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]));
    }
};
