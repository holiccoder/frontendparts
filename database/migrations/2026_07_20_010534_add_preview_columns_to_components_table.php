<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->json('preview_paths')->nullable()->after('source_hash');
            $table->timestamp('preview_built_at')->nullable()->after('preview_paths');
        });
    }

    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->dropColumn(['preview_paths', 'preview_built_at']);
        });
    }
};
