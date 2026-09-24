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
        Schema::table('part_numbers', function (Blueprint $table) {
            $table->string('part_number_code', 100)->nullable()->after('pn_baan')->index();
            $table->string('addressing', 255)->nullable()->after('description')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('part_numbers', function (Blueprint $table) {
            $table->dropIndex(['part_number_code']);
            $table->dropIndex(['addressing']);
            $table->dropColumn(['part_number_code', 'addressing']);
        });
    }
};

