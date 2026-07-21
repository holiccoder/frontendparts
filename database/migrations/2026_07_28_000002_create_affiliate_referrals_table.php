<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            // Null while the click is anonymous; linked to the signing-up
            // user by the Registered listener (SPEC §17.1 step 3).
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('clicked_at');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('landing_url')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['affiliate_id', 'referred_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_referrals');
    }
};
