<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Preview-modal pane layout {side: 'left'|'right', split: 20-80}
            // (SPEC §5.4 editable layout). Null = never customized; guests
            // keep theirs in localStorage instead.
            $table->json('preview_layout')->nullable()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preview_layout');
        });
    }
};
