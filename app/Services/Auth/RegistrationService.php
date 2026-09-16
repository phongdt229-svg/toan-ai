<?php

namespace App\Services\Auth;

use App\Models\ParentChild;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    /**
     * Hồ sơ học sinh thu đủ dữ liệu cá nhân hóa ngay lúc đăng ký (§33) —
     * AI dùng ngay các trường này để chọn giọng, độ khó và ngữ cảnh ví dụ.
     *
     * @param  array<string, mixed>  $data
     */
    public function registerStudent(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'status' => User::STATUS_ACTIVE,
            ]);

            $user->assignRole(Role::STUDENT);

            $score = isset($data['math_average_score']) && $data['math_average_score'] !== null
                ? (float) $data['math_average_score']
                : null;

            StudentProfile::create([
                'user_id' => $user->id,
                'grade_id' => $data['grade_id'],
                'birth_date' => $data['birth_date'] ?? null,
                'address' => $data['address'] ?? null,
                'school' => $data['school'] ?? null,
                // Không có tự đánh giá thì suy ra từ điểm trung bình (§34).
                'self_assessed_level' => $data['self_assessed_level'] ?? StudentProfile::classifyLevel($score),
                'math_average_score' => $score,
                'tutor_persona' => $data['tutor_persona'] ?? 'co',
                'favorite_color' => $data['favorite_color'] ?? null,
                'interests' => $this->parseInterests($data['interests'] ?? null),
                'link_code' => StudentProfile::generateLinkCode(),
            ]);

            return $user;
        });
    }

    /** "bóng đá, game" → ['bóng đá', 'game'] */
    private function parseInterests(?string $raw): ?array
    {
        if (! $raw) {
            return null;
        }

        $items = collect(explode(',', $raw))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->take(10)
            ->values();

        return $items->isEmpty() ? null : $items->all();
    }

    /**
     * Giáo viên đăng ký xong ở trạng thái `pending`, admin duyệt mới vào được portal (§5).
     *
     * @param  array{name: string, email: string, phone: string, school: string, password: string}  $data
     */
    public function registerTeacher(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'status' => User::STATUS_PENDING,
            ]);

            $user->assignRole(Role::TEACHER);

            TeacherProfile::create([
                'user_id' => $user->id,
                'school' => $data['school'],
                'subject' => 'Toán',
            ]);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, phone: string, password: string, link_code: ?string}  $data
     */
    public function registerParent(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'status' => User::STATUS_ACTIVE,
            ]);

            $user->assignRole(Role::PARENT);

            if (! empty($data['link_code'])) {
                $this->linkChildByCode($user, $data['link_code']);
            }

            return $user;
        });
    }

    /** Liên kết phụ huynh với học sinh bằng mã trên hồ sơ học sinh (§5). */
    public function linkChildByCode(User $parent, string $code): User
    {
        $profile = StudentProfile::with('user')
            ->where('link_code', strtoupper(trim($code)))
            ->first();

        if (! $profile || ! $profile->user) {
            throw ValidationException::withMessages([
                'link_code' => 'Mã liên kết không tồn tại.',
            ]);
        }

        $parent->children()->syncWithoutDetaching([
            $profile->user_id => [
                'status' => ParentChild::STATUS_LINKED,
                'linked_at' => now(),
            ],
        ]);

        return $profile->user;
    }
}
