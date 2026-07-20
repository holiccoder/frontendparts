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
            // Marketing email preferences {product_updates: bool, blog: bool,
            // digest_frequency: 'weekly'|'monthly'|'off'} (SPEC §16.3). Null =
            // defaults (all opted in, weekly digest); transactional mail is
            // mandatory and never stored here. Resolved exclusively through
            // App\Services\Notifications\NotificationPreferences.
            $table->json('notification_preferences')->nullable()->after('preview_layout');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
