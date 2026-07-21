<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referral_id')->nullable()->constrained('affiliate_referrals')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending');
            $table->timestamp('payable_at')->nullable();
            $table->string('voided_reason')->nullable();
            $table->timestamps();

            // One commission per order per affiliate (SPEC §17.3); an order
            // is attributed to at most one affiliate, so this is effectively
            // one commission per order — also the webhook idempotency guard.
            $table->unique(['order_id', 'affiliate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
    }
};
