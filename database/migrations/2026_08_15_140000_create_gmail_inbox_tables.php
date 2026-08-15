<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gmail_inbox_messages', function (Blueprint $table) {
            $table->id();
            // RFC Message-ID（重複取込防止）。utf8mb4 の unique 上限に合わせ 255
            $table->string('message_id', 255)->unique();
            $table->unsignedBigInteger('imap_uid')->nullable()->index();
            $table->string('mailbox', 64)->default('INBOX')->index();
            $table->string('subject', 500)->nullable();
            $table->string('from_address', 255)->nullable();
            $table->timestamp('received_at')->nullable()->index();
            $table->boolean('has_deeplink')->default(false)->index();
            $table->text('deeplink_url')->nullable();
            $table->json('deeplink_urls')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['mailbox', 'imap_uid']);
        });

        Schema::create('gmail_imap_cursors', function (Blueprint $table) {
            $table->string('mailbox', 64)->primary();
            $table->unsignedBigInteger('last_uid')->default(0);
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gmail_imap_cursors');
        Schema::dropIfExists('gmail_inbox_messages');
    }
};
