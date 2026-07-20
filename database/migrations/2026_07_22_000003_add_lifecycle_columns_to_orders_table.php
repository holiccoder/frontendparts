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
        Schema::table('orders', function (Blueprint $table) {
            // Dunning anchor (SPEC §16.2 B6): stamped by OrderObserver every
            // time an order enters PastDue, so the 5-touch schedule measures
            // from the failure — not from any later incidental order update.
            $table->timestamp('past_due_at')->nullable()->after('cancelled_at')->index();

            // Cancel-flow exit survey (SPEC §16.2 B7): the one required
            // answer collected before a user-initiated cancellation.
            $table->string('cancellation_reason')->nullable()->after('past_due_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['past_due_at']);
            $table->dropColumn(['past_due_at', 'cancellation_reason']);
        });
    }
};
