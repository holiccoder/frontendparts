<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Domestic notify idempotency (SPEC §7.5): mirrors paddle_events —
        // one table for both rails, event ids namespaced by channel so an
        // Alipay notify_id and a WeChat notification id can never collide.
        Schema::create('domestic_events', function (Blueprint $table) {
            $table->id();
            $table->string('channel');
            $table->string('event_id');
            $table->string('event_type');
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->unique(['channel', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domestic_events');
    }
};
