<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add index to 'created_by' foreign key in 'consumes' table for optimized user filtering & joins.
     */
    public function up(): void
    {
        Schema::table('consumes', function (Blueprint $table) {
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumes', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
        });
    }
};

