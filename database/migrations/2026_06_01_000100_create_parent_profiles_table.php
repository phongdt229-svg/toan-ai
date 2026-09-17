<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hồ sơ phụ huynh — cùng khuôn với student_profiles / teacher_profiles.
 * Hiện chỉ giữ cài đặt báo cáo tuần; Phase 8 sẽ thêm thông tin gói học cho con.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('weekly_report_enabled')->default(true);
            // Chống gửi trùng khi lệnh chạy lại hoặc queue retry.
            $table->timestamp('last_weekly_report_at')->nullable();
            $table->timestamps();
        });

        // Phụ huynh đăng ký trước migration này cũng cần hồ sơ.
        $parentIds = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', 'parent')
            ->pluck('role_user.user_id');

        foreach ($parentIds as $id) {
            DB::table('parent_profiles')->insertOrIgnore([
                'user_id' => $id,
                'weekly_report_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_profiles');
    }
};
