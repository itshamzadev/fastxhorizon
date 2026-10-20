<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_provider_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('environment');
            $table->string('address_hash', 32);
            $table->string('remote_address_id');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'environment', 'address_hash'], 'courier_provider_addresses_lookup_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_provider_addresses');
    }
};
