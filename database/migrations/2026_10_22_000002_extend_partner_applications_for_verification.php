<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            $table->string('reference', 40)->nullable()->unique()->after('id');
            $table->string('country', 100)->nullable()->after('province');
            $table->text('national_id')->nullable()->after('country');
            $table->json('business_data')->nullable()->after('reason');
            $table->json('sales_data')->nullable()->after('business_data');
            $table->timestamp('terms_accepted_at')->nullable()->after('submitted_at');
            $table->string('terms_version', 30)->nullable()->after('terms_accepted_at');
            $table->timestamp('approved_at')->nullable()->after('reviewed_at');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('admin_notes');
        });

        Schema::create('partner_application_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('partner_application_id')->constrained('partner_applications')->cascadeOnDelete();
            $table->string('document_type', 60);
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
            $table->index(['partner_application_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_application_documents');
        Schema::table('partner_applications', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn(['reference', 'country', 'national_id', 'business_data', 'sales_data', 'terms_accepted_at', 'terms_version', 'approved_at', 'rejected_at', 'rejection_reason']);
        });
    }
};
