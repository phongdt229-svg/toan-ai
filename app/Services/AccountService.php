<?php

namespace App\Services;

use App\Models\StudentProfile;
use App\Models\User;

/** Hồ sơ + mật khẩu của chính người dùng đang đăng nhập — dùng chung cho cả 4 portal. */
class AccountService
{
    /** @param  array{name: string, phone: ?string}  $data */
    public function updateProfile(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
        ]);

        return $user;
    }

    /**
     * Thầy/cô, màu yêu thích, sở thích của học sinh — giọng AI Tutor và màu nhấn portal đọc lại từ đây.
     *
     * @param  array{tutor_persona: string, favorite_color: ?string, interests: ?string}  $data
     */
    public function updateStudentPersonalization(User $user, array $data): StudentProfile
    {
        $profile = $user->studentProfile;
        $profile->update([
            'tutor_persona' => $data['tutor_persona'],
            'favorite_color' => $data['favorite_color'] ?? null,
            'interests' => StudentProfile::parseInterests($data['interests'] ?? null),
        ]);

        return $profile;
    }

    /** Mật khẩu hiện tại đã được UpdatePasswordRequest xác nhận (rule current_password). */
    public function updatePassword(User $user, string $newPassword): void
    {
        // Cast 'password' => 'hashed' trên model tự băm khi gán — không tự gọi Hash::make ở đây.
        $user->update(['password' => $newPassword]);
    }
}
