<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('github_id');
            $table->string('github_login');
            // OAuth access token (SPEC §6.4): encrypted at rest via the
            // model's `encrypted` cast — ciphertext needs TEXT, not VARCHAR.
            $table->text('token');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_connections');
    }
};
