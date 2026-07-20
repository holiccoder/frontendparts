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
        Schema::create('sequence_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sequence');
            $table->string('step');
            $table->timestamp('sent_at');

            // Hard idempotency for the lifecycle engine (SPEC §16.2): the
            // unique key guarantees one send per user per sequence step even
            // if the daily command runs twice or races itself.
            $table->unique(['user_id', 'sequence', 'step']);
            $table->index(['sequence', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequence_sends');
    }
};
