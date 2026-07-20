<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preview_build_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->string('framework');
            $table->text('error');
            $table->timestamp('created_at');

            $table->unique(['component_id', 'framework']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preview_build_failures');
    }
};
