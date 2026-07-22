<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('blog_category', function (Blueprint $table) {
            $table->foreignId('blog_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blog_category_id')->constrained()->cascadeOnDelete();

            $table->primary(['blog_id', 'blog_category_id']);
        });

        Schema::create('blog_tag', function (Blueprint $table) {
            $table->foreignId('blog_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blog_tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['blog_id', 'blog_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_tag');
        Schema::dropIfExists('blog_category');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('blog_categories');
    }
};
