<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('component_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('components')->cascadeOnDelete();
            $table->foreignId('child_id')->constrained('components')->cascadeOnDelete();
            $table->string('slot')->nullable();
            $table->integer('sort_order')->default(0);

            $table->unique(['parent_id', 'child_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_children');
    }
};
