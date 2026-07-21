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
            // Affiliate attribution (SPEC §17.1 step 4): the referral code
            // stamped at checkout (Paddle: mirrored into the session's
            // custom_data; domestic: the order meta) so the paid webhook can
            // attribute the order even after the referral cookie is gone.
            $table->string('referral_code')->nullable()->after('domestic_transaction_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['referral_code']);
            $table->dropColumn('referral_code');
        });
    }
};
