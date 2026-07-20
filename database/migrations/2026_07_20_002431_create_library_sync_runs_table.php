<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('scanned')->default(0);
            $table->unsignedInteger('upserted')->default(0);
            $table->json('errors')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_sync_runs');
    }
};
