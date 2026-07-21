<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docs_pages', function (Blueprint $table) {
            $table->id();
            $table->string('section');
            $table->string('page');
            $table->string('title');
            $table->string('description')->default('');
            // Markdown body reduced to plain text — the searchable payload
            // and the snippet source (SPEC §13.2).
            $table->text('body');
            $table->timestamps();

            $table->unique(['section', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docs_pages');
    }
};
