<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_exports', function (Blueprint $table) {
            // pack (SPEC §6.2) vs scaffold (SPEC §6.3) — one table, one
            // queued-build → poll → stream flow for both export flavors.
            $table->string('kind')->default('pack')->after('framework');
        });
    }

    public function down(): void
    {
        Schema::table('project_exports', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
