<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('business_status')->default('unregistered')->after('legal_name');
            $table->string('license_type')->nullable()->after('business_status');
            $table->text('legal_documents')->nullable()->after('license_type');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name',
                'business_status',
                'license_type',
                'legal_documents',
            ]);
        });
    }
};
