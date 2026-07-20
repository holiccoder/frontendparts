<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('paddle_customer_id')->nullable()->after('cancelled_at');
            $table->string('paddle_transaction_id')->nullable()->after('paddle_customer_id');
            $table->string('paddle_subscription_id')->nullable()->after('paddle_transaction_id');

            $table->index('paddle_transaction_id');
            $table->index('paddle_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['paddle_transaction_id']);
            $table->dropIndex(['paddle_subscription_id']);
            $table->dropColumn([
                'paddle_customer_id',
                'paddle_transaction_id',
                'paddle_subscription_id',
            ]);
        });
    }
};
