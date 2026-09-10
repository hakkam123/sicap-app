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
        Schema::table('import_logs', function (Blueprint $table) {
            $table->string('feature', 50)->default('consume')->after('user_id');
            $table->integer('success_rows')->default(0)->after('processed_rows');
            $table->integer('failed_rows')->default(0)->after('success_rows');
            $table->text('error_details')->nullable()->after('error_message');
            $table->timestamp('started_at')->nullable()->after('error_details');
            $table->timestamp('finished_at')->nullable()->after('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->dropColumn([
                'feature',
                'success_rows',
                'failed_rows',
                'error_details',
                'started_at',
                'finished_at',
            ]);
        });
    }
};

