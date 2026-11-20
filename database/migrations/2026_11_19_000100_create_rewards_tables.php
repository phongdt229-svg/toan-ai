<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Điểm thưởng + huy hiệu. Điểm chỉ để mở huy hiệu và xếp hạng tuần — KHÔNG đổi ra tiền/mã giảm giá (chốt 19/11).
 *
 * reward_points là SỔ CÁI: mỗi lần cộng một dòng, unique (user, lý do, nguồn) để sự kiện bắn lại
 * (chấm tay tự luận bắn lại ExamAttemptFinished) không cộng hai lần. Tổng điểm = SUM, không có cột đếm sẵn.
 * Số điểm mỗi hoạt động nằm ở reward_rules (Quản trị → Điểm thưởng), không viết cứng trong code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_rules', function (Blueprint $table) {
            $table->id();
            $table->string('reason', 40)->unique();
            $table->string('label', 120);
            $table->unsignedSmallInteger('points');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('reward_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('points');
            $table->string('reason', 40);
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'reason', 'source_type', 'source_id'], 'reward_points_once');
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('badge_key', 40);
            $table->dateTime('earned_at');

            $table->unique(['user_id', 'badge_key']);
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            // Bảng xếp hạng lớp hiện tên thật; học sinh không muốn thì tự ẩn trong Cài đặt.
            $table->boolean('hide_from_leaderboard')->default(false)->after('interests');
        });

        $now = now();
        DB::table('reward_rules')->insert([
            ['reason' => 'lesson_completed', 'label' => 'Học xong một bài học', 'points' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['reason' => 'exam_finished', 'label' => 'Nộp một đề kiểm tra (lần đầu mỗi đề)', 'points' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['reason' => 'exam_high_score', 'label' => 'Đạt từ 80% ở một đề kiểm tra', 'points' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['reason' => 'path_session_done', 'label' => 'Hoàn thành một buổi trong lộ trình', 'points' => 15, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['reason' => 'streak_day', 'label' => 'Một ngày học đủ thời gian (giữ chuỗi)', 'points' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('student_profiles', fn (Blueprint $table) => $table->dropColumn('hide_from_leaderboard'));
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('reward_points');
        Schema::dropIfExists('reward_rules');
    }
};
