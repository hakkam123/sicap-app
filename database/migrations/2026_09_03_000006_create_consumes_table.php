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
        Schema::create('consumes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('part_number_id')->constrained('part_numbers')->noActionOnDelete();
            $table->foreignUlid('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignUlid('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->integer('quantity'); // Signed integer, can be negative, no check constraint
            $table->decimal('amount', 18, 2)->nullable();
            $table->dateTime('consumed_at');
            $table->string('source', 20);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('consumed_at');
            $table->index('area_id');
            $table->index('machine_id');
            $table->index('part_number_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumes');
    }
};
