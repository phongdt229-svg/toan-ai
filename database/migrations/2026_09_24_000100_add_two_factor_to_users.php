<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Xác thực 2 bước TOTP (RFC 6238). Secret + mã dự phòng lưu mã hoá; mã dự phòng lưu băm. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            // Chưa xác nhận mã đầu tiên thì chưa tính là đã bật — tránh tự khoá mình khi quét QR hỏng.
            $table->dateTime('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            // Bước thời gian TOTP đã dùng gần nhất — chặn dùng lại cùng một mã (replay).
            $table->unsignedBigInteger('two_factor_last_step')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step']);
        });
    }
};
