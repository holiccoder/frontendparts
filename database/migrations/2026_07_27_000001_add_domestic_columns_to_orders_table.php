<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Payment backend (SPEC §7.5): paddle (international, default —
            // every pre-existing order is Paddle) or domestic (Alipay /
            // WeChat Pay, CNY). Mirrors plan_prices.provider.
            $table->string('provider')->default('paddle')->after('paddle_subscription_id')->index();

            // Domestic rail used for the pre-order, set when the QR page
            // first creates it (alipay | wechat); null for Paddle orders.
            $table->string('domestic_channel')->nullable()->after('provider');

            // Our pre-order reference sent to Alipay/WeChat (their
            // out_trade_no) — the notify endpoints resolve the order by it.
            $table->string('out_trade_no')->nullable()->after('domestic_channel')->index();

            // The provider's transaction id (Alipay trade_no / WeChat
            // transaction_id), stamped by the paid notify.
            $table->string('domestic_transaction_id')->nullable()->after('out_trade_no')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['provider']);
            $table->dropIndex(['out_trade_no']);
            $table->dropIndex(['domestic_transaction_id']);
            $table->dropColumn([
                'provider',
                'domestic_channel',
                'out_trade_no',
                'domestic_transaction_id',
            ]);
        });
    }
};
