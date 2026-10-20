<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courier_provider_addresses', function (Blueprint $table) {
            $table->string('owner_type', 20)->default('platform')->after('id');
            $table->unsignedBigInteger('owner_id')->default(0)->after('owner_type');
        });

        Schema::table('courier_provider_addresses', function (Blueprint $table) {
            if ($this->lookupIndexExists()) {
                $table->dropUnique('courier_provider_addresses_lookup_unique');
            }

            $table->unique(
                ['owner_type', 'owner_id', 'provider', 'environment', 'address_hash'],
                'courier_provider_addresses_lookup_unique',
            );
        });
    }

    public function down(): void
    {
        DB::table('courier_provider_addresses')->where('owner_type', '!=', 'platform')->delete();

        Schema::table('courier_provider_addresses', function (Blueprint $table) {
            $table->dropUnique('courier_provider_addresses_lookup_unique');
            $table->unique(['provider', 'environment', 'address_hash'], 'courier_provider_addresses_lookup_unique');
        });

        Schema::table('courier_provider_addresses', function (Blueprint $table) {
            $table->dropColumn(['owner_type', 'owner_id']);
        });
    }

    private function lookupIndexExists(): bool
    {
        return collect(Schema::getIndexes('courier_provider_addresses'))
            ->contains(fn (array $index): bool => $index['name'] === 'courier_provider_addresses_lookup_unique');
    }
};
