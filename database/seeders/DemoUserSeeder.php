<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Tài khoản demo cho môi trường local. Mật khẩu mặc định đổi bằng env DEMO_PASSWORD.
 * KHÔNG chạy seeder này trên production.
 */
class DemoUserSeeder extends Seeder
{
    /** Email → vai trò. Màn hình đăng nhập ở local đọc danh sách này để hiện nút đăng nhập nhanh. */
    public const ACCOUNTS = [
        'admin@gmail.com' => 'Quản trị',
        'teacher@gmail.com' => 'Giáo viên',
        'teacher-pending@gmail.com' => 'Giáo viên chờ duyệt',
        'student@gmail.com' => 'Học sinh',
        'parent@gmail.com' => 'Phụ huynh',
    ];

    public function run(): void
    {
        $password = Hash::make(config('app.demo_password'));

        $admin = User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Quản trị viên',
                'password' => $password,
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ],
        );
        $admin->assignRole(Role::ADMIN);

        $teacher = User::updateOrCreate(
            ['email' => 'teacher@gmail.com'],
            [
                'name' => 'Nguyễn Văn Giáo',
                'phone' => '0900000001',
                'password' => $password,
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ],
        );
        $teacher->assignRole(Role::TEACHER);
        TeacherProfile::updateOrCreate(
            ['user_id' => $teacher->id],
            [
                'school' => 'THCS Demo',
                'subject' => 'Toán',
                'approved_at' => now(),
                'approved_by' => $admin->id,
            ],
        );

        // Giáo viên chờ duyệt — để thử luồng duyệt của admin
        $pendingTeacher = User::updateOrCreate(
            ['email' => 'teacher-pending@gmail.com'],
            [
                'name' => 'Trần Thị Chờ Duyệt',
                'phone' => '0900000002',
                'password' => $password,
                'status' => User::STATUS_PENDING,
            ],
        );
        $pendingTeacher->assignRole(Role::TEACHER);
        TeacherProfile::updateOrCreate(
            ['user_id' => $pendingTeacher->id],
            ['school' => 'THPT Demo', 'subject' => 'Toán'],
        );

        $grade6 = Grade::where('level', 6)->first();

        $student = User::updateOrCreate(
            ['email' => 'student@gmail.com'],
            [
                'name' => 'Lê Minh Học',
                'password' => $password,
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ],
        );
        $student->assignRole(Role::STUDENT);

        $existingProfile = StudentProfile::where('user_id', $student->id)->first();
        StudentProfile::updateOrCreate(
            ['user_id' => $student->id],
            [
                'grade_id' => $grade6?->id,
                'birth_date' => now()->subYears(12)->startOfYear()->addMonths(4),
                'address' => 'Quận 1, TP. Hồ Chí Minh',
                'school' => 'THCS Demo',
                'self_assessed_level' => 'good',
                'math_average_score' => 7.50,
                'tutor_persona' => 'co',
                'favorite_color' => '#2563eb',
                'interests' => ['bóng đá', 'game'],
                'link_code' => $existingProfile?->link_code ?? StudentProfile::generateLinkCode(),
            ],
        );

        $parent = User::updateOrCreate(
            ['email' => 'parent@gmail.com'],
            [
                'name' => 'Lê Văn Phụ Huynh',
                'phone' => '0900000003',
                'password' => $password,
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ],
        );
        $parent->assignRole(Role::PARENT);
        ParentProfile::firstOrCreate(['user_id' => $parent->id], ['weekly_report_enabled' => true]);
        $parent->children()->syncWithoutDetaching([
            $student->id => ['status' => 'linked', 'linked_at' => now()],
        ]);
    }
}
