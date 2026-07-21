<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('processing');
            // Snapshot of the payout coordinates used for the batch (SPEC
            // §17.3) so later payout-method edits never rewrite history.
            $table->json('method')->nullable();
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_commission_payout', function (Blueprint $table) {
            $table->id();
            // A commission belongs to at most one payout (SPEC §17.3).
            $table->foreignId('affiliate_commission_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_payout_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commission_payout');
        Schema::dropIfExists('affiliate_payouts');
    }
};
