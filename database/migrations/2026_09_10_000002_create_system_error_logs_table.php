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
        Schema::create('system_error_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('error_type', 100);
            $table->integer('status_code')->nullable()->index();
            $table->string('severity', 20)->default('error')->index();
            $table->string('feature', 50)->nullable()->index();
            $table->text('message');
            $table->string('exception_class')->nullable();
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->text('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->longText('request_payload')->nullable();
            $table->longText('stack_trace')->nullable();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->string('user_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('status', 20)->default('unresolved')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUlid('resolved_by')->nullable()->index();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'severity']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_error_logs');
    }
};
