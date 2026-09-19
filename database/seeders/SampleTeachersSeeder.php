<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 12 tài khoản giáo viên demo bổ sung — đã duyệt, có hồ sơ đầy đủ (trường, môn dạy).
 * Cho danh sách giáo viên ở trang quản trị có số liệu thật để xem/lọc/phân trang,
 * không chỉ đúng 1 giáo viên duy nhất như DemoUserSeeder.
 * Chỉ chạy ở local/testing (xem DatabaseSeeder). Chạy lại nhiều lần không nhân đôi dữ liệu.
 */
class SampleTeachersSeeder extends Seeder
{
    private const TEACHERS = 12;

    private const NAMES = [
        'Đinh Thị Hồng', 'Phan Văn Long', 'Trịnh Minh Anh', 'Vương Thị Lan', 'Đào Xuân Bách', 'Lâm Thu Trang',
        'Cao Văn Hùng', 'Tô Thị Nga', 'Mai Đức Thắng', 'Chu Thị Hoa', 'Hồ Văn Tâm', 'Kiều Thị Yến',
    ];

    private const SCHOOLS = ['THCS Demo', 'THCS Nguyễn Du', 'THPT Chu Văn An', 'THCS Lê Lợi'];

    public function run(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();
        $password = Hash::make(config('app.demo_password'));

        for ($i = 0; $i < self::TEACHERS; $i++) {
            $email = sprintf('gv%02d@gmail.com', $i + 1);

            $teacher = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => self::NAMES[$i] ?? 'Giáo viên '.($i + 1),
                    'phone' => sprintf('0901%06d', $i + 1),
                    'password' => $password,
                    'status' => User::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                ],
            );

            if ($teacher->wasRecentlyCreated) {
                $teacher->assignRole(Role::TEACHER);
            }

            TeacherProfile::firstOrCreate(
                ['user_id' => $teacher->id],
                [
                    'school' => self::SCHOOLS[$i % count(self::SCHOOLS)],
                    'subject' => 'Toán',
                    'approved_at' => now(),
                    'approved_by' => $admin?->id,
                ],
            );
        }
    }
}
