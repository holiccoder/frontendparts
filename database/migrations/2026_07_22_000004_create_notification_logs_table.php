<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('notifiable');
            $table->string('notification');
            $table->string('channel');
            // The sent notification, PHP-serialized and base64-encoded so the
            // admin resend action (SPEC §16.3) can rehydrate the exact
            // instance for any notification class. Queued notifications
            // serialize Eloquent models as identifiers, which resolve again
            // on unserialize.
            $table->json('payload');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['notification', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
