<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sellers')) {
            $columns = [];
            if (!Schema::hasColumn('sellers', 'is_verified')) {
                $columns[] = 'is_verified';
            }
            if (!Schema::hasColumn('sellers', 'auto_approve_products')) {
                $columns[] = 'auto_approve_products';
            }
            if ($columns !== []) {
                Schema::table('sellers', function (Blueprint $table) use ($columns): void {
                    foreach ($columns as $column) {
                        $table->boolean($column)->default(false);
                    }
                });
            }
        }

        if (!Schema::hasTable('seller_setting_audits')) {
            Schema::create('seller_setting_audits', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('seller_id');
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('setting', 64);
                $table->boolean('old_value')->nullable();
                $table->boolean('new_value');
                $table->timestamps();

                $table->index(['seller_id', 'setting'], 'ssa_seller_setting_idx');
                $table->index('admin_id', 'ssa_admin_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_setting_audits');

        if (Schema::hasTable('sellers')) {
            if (Schema::hasColumn('sellers', 'auto_approve_products')) {
                Schema::table('sellers', fn (Blueprint $table) => $table->dropColumn('auto_approve_products'));
            }
            if (Schema::hasColumn('sellers', 'is_verified')) {
                Schema::table('sellers', fn (Blueprint $table) => $table->dropColumn('is_verified'));
            }
        }
    }
};
