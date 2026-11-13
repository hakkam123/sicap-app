<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jumlah baris yang sudah ada lalu ditimpa data terbaru (bukan baris baru).
     */
    public function up(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->integer('updated_rows')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->dropColumn('updated_rows');
        });
    }
};
