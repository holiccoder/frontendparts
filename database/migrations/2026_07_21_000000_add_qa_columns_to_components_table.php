<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->json('qa_checklist')->nullable()->after('preview_built_at');
            $table->text('review_note')->nullable()->after('qa_checklist');
        });
    }

    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->dropColumn(['qa_checklist', 'review_note']);
        });
    }
};
