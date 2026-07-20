<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->string('plan');
            $table->string('period');
            $table->string('provider');
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3);
            $table->string('paddle_price_id')->nullable();
            $table->timestamps();

            $table->unique(['plan', 'period', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
