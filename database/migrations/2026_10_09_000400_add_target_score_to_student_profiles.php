<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TA-06: mục tiêu điểm Toán học sinh tự đặt — hiện cạnh điểm hiện tại ở trang lộ trình. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->decimal('target_score', 4, 2)->nullable()->after('math_average_score');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn('target_score');
        });
    }
};
