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
        Schema::table('urls', function (Blueprint $table) {
            // Add critical indexes for performance
            $table->index('device_id'); // For user's URL lists
            $table->index(['device_id', 'created_at']); // For paginated lists ordered by creation
            $table->index('created_at'); // For general time-based queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('urls', function (Blueprint $table) {
            $table->dropIndex(['device_id']);
            $table->dropIndex(['device_id', 'created_at']);
            $table->dropIndex(['created_at']);
        });
    }
};
