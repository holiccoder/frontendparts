<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('level');
            $table->foreignId('usage_category_id')->constrained('categories')->restrictOnDelete();
            $table->string('access_level');
            $table->string('status')->default('draft');
            $table->string('version')->default('1.0.0');
            $table->string('source_name')->nullable();
            $table->string('source_url')->nullable();
            $table->json('deps')->nullable();
            $table->timestamps();

            $table->index(['status', 'access_level']);
            $table->index('usage_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('components');
    }
};
