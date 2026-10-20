<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_searches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('keyword');
            $table->json('tag_ids')->nullable(); // tag ids the keyword resolved to (literal + AI-bridged)
            $table->unsignedInteger('search_count')->default(1);
            $table->timestamp('searched_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'keyword']);
            $table->index(['customer_id', 'searched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_searches');
    }
};
