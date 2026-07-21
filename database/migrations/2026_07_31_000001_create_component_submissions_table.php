<?php

use App\Enums\SubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('component_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('level');
            $table->foreignId('usage_category_id')->constrained('categories')->restrictOnDelete();
            $table->string('framework');
            $table->text('description');
            $table->longText('react_code')->nullable();
            $table->longText('vue_code')->nullable();
            $table->json('sample_data')->nullable();
            $table->string('source_url')->nullable();
            $table->string('status')->default(SubmissionStatus::Pending->value)->index();
            $table->text('review_note')->nullable();
            $table->foreignId('component_id')->nullable()->constrained('components')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_submissions');
    }
};
