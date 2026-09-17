<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drop the single redundant 'status' index because it is fully covered by composite index ['status', 'created_at'].
     */
    public function up(): void
    {
        Schema::table('system_error_logs', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_error_logs', function (Blueprint $table) {
            $table->index('status');
        });
    }
};

