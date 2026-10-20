<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('mail_delivery_logs')) {
            Schema::create('mail_delivery_logs', function (Blueprint $table) {
                $table->id();
                $table->string('message_id', 100)->nullable()->unique();
                $table->string('template_key')->nullable()->index();
                $table->string('campaign_id')->nullable()->index();
                $table->string('recipient')->index();
                $table->string('subject')->nullable();
                $table->string('type')->default('transactional')->index();
                $table->string('status')->default('queued')->index();
                $table->unsignedInteger('attempts')->default(0);
                $table->text('failure_reason')->nullable();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('mail_campaigns')) {
            Schema::create('mail_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type')->default('newsletter');
                $table->string('subject');
                $table->string('preheader')->nullable();
                $table->longText('html_body')->nullable();
                $table->longText('plain_text_body')->nullable();
                $table->string('cta_text')->nullable();
                $table->string('cta_url')->nullable();
                $table->string('hero_image')->nullable();
                $table->string('status')->default('draft')->index();
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('mail_campaign_recipients')) {
            Schema::create('mail_campaign_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('mail_campaigns')->cascadeOnDelete();
                $table->string('email');
                $table->string('status')->default('queued')->index();
                $table->unsignedBigInteger('delivery_log_id')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->unique(['campaign_id', 'email']);
            });
        }
        if (! Schema::hasTable('mail_unsubscribe_tokens')) {
            Schema::create('mail_unsubscribe_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('email')->index();
                $table->string('token', 100)->unique();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_unsubscribe_tokens');
        Schema::dropIfExists('mail_campaign_recipients');
        Schema::dropIfExists('mail_campaigns');
        Schema::dropIfExists('mail_delivery_logs');
    }
};
