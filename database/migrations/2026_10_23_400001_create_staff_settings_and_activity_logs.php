<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_role', 20)->nullable()->after('role');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        // Admin yang sudah ada sebelum fitur staf menjadi Owner.
        DB::table('users')->where('role', 'admin')->update(['staff_role' => 'owner']);

        // Pengaturan toko yang bisa diubah dari panel admin (override .env).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Nama disimpan juga supaya log tetap terbaca bila akun staf dihapus.
            $table->string('user_name')->nullable();
            $table->string('action', 100)->index();
            $table->string('description');
            $table->nullableMorphs('subject');
            $table->string('subject_label')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('settings');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['staff_role', 'last_login_at']);
        });
    }
};
