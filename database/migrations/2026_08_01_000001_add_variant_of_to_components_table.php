<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('components', function (Blueprint $table) {
            // AI-generated variants (task 5.4) link back to the component
            // they were derived from; deleting the original detaches the link.
            $table->foreignId('variant_of')
                ->nullable()
                ->after('source_url')
                ->constrained('components')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variant_of');
        });
    }
};
