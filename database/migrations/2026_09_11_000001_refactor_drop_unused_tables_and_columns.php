<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Refactor database: hapus tabel Spatie Permission, hapus tabel session/cache
     * (pindah ke file driver), hapus kolom yang tidak dipakai.
     */
    public function up(): void
    {
        // 1. Drop Spatie Permission tables (urutan: child -> parent)
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');

        // 2. Drop session & cache tables (pindah ke file driver)
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');

        // 3. Drop password_reset_tokens (fitur reset password dihapus)
        Schema::dropIfExists('password_reset_tokens');

        // 4. Drop kolom price_per_unit dari part_numbers
        Schema::table('part_numbers', function (Blueprint $table) {
            $table->dropColumn('price_per_unit');
        });

        // 5. Drop kolom email_verified_at dari users (tidak dipakai)
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore email_verified_at
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        // Restore price_per_unit
        Schema::table('part_numbers', function (Blueprint $table) {
            $table->decimal('price_per_unit', 18, 2)->nullable()->after('description');
        });

        // Restore password_reset_tokens
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Restore cache tables
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });

        // Restore sessions
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Note: Spatie permission tables harus di-restore via php artisan vendor:publish
        // dan migration package jika dibutuhkan kembali.
    }
};

