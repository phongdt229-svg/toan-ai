<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Thi thử vào lớp 10 / tốt nghiệp THPT: loại đề `mock` + kỳ thi (`mock_track`).
 * Làm bài đi nguyên luồng ExamService (giờ do server bấm); phần riêng là quy về thang 10,
 * xếp hạng giữa các thí sinh và phân tích chủ đề yếu (MockExamService).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE exams MODIFY type ENUM('practice','quiz','test','mock') NOT NULL DEFAULT 'test'");

        Schema::table('exams', function (Blueprint $table) {
            $table->string('mock_track', 20)->nullable()->after('type'); // grade10 | thpt
            $table->index(['type', 'mock_track', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex(['type', 'mock_track', 'status']);
            $table->dropColumn('mock_track');
        });
        DB::table('exams')->where('type', 'mock')->update(['type' => 'test']);
        DB::statement("ALTER TABLE exams MODIFY type ENUM('practice','quiz','test') NOT NULL DEFAULT 'test'");
    }
};
