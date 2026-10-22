<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('program', 20);
            $table->string('code', 40)->unique();
            $table->string('display_name');
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'program']);
            $table->index(['program', 'status']);
        });

        Schema::create('partner_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('program', 20);
            $table->string('display_name');
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->text('address')->nullable();
            $table->string('sales_channel')->nullable();
            $table->text('reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['program', 'status']);
            $table->index(['user_id', 'program']);
        });

        Schema::create('partner_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_profile_id')->constrained('partner_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('program', 20);
            $table->string('code', 40);
            $table->string('landing_url', 2048)->nullable();
            $table->string('visitor_token_hash', 64)->nullable();
            $table->timestamp('first_at')->nullable();
            $table->timestamp('last_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['program', 'code']);
            $table->index(['user_id', 'program']);
            $table->index(['order_id', 'program']);
        });

        Schema::create('partner_commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('program', 20);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('commission_type', 12)->default('percentage');
            $table->decimal('amount', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
            $table->index(['program', 'is_active', 'priority']);
            $table->index(['product_id', 'category_id']);
        });

        Schema::create('partner_commissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('partner_profile_id')->constrained('partner_profiles')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('program', 20);
            $table->decimal('base_amount', 14, 3)->default(0);
            $table->string('commission_type', 12);
            $table->decimal('rate', 12, 3)->default(0);
            $table->decimal('amount', 14, 3)->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamp('available_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['partner_profile_id', 'order_id', 'program']);
            $table->index(['program', 'status']);
        });

        Schema::create('partner_ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('partner_profile_id')->constrained('partner_profiles')->cascadeOnDelete();
            $table->foreignId('commission_id')->nullable()->constrained('partner_commissions')->nullOnDelete();
            // The payout table is created after the ledger table to keep this
            // migration portable across the supported MySQL versions.
            $table->unsignedBigInteger('payout_request_id')->nullable();
            $table->string('type', 20);
            $table->decimal('amount', 14, 3);
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['partner_profile_id', 'created_at']);
            $table->index('payout_request_id');
        });

        Schema::create('partner_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('partner_profile_id')->constrained('partner_profiles')->cascadeOnDelete();
            $table->string('program', 20);
            $table->decimal('amount', 14, 3);
            $table->string('payout_method', 50);
            $table->text('account_details');
            $table->string('status', 20)->default('pending');
            $table->string('reference')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['program', 'status']);
        });

        Schema::create('partner_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('partner_profile_id')->nullable()->constrained('partner_profiles')->nullOnDelete();
            $table->string('program', 20);
            $table->string('action', 60);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['program', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_audit_events');
        Schema::dropIfExists('partner_ledger_transactions');
        Schema::dropIfExists('partner_payout_requests');
        Schema::dropIfExists('partner_commissions');
        Schema::dropIfExists('partner_attributions');
        Schema::dropIfExists('partner_commission_rules');
        Schema::dropIfExists('partner_applications');
        Schema::dropIfExists('partner_profiles');
    }
};
