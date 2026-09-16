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
     * @param  array{name: string, email: string, password: string, grade_id: int, birth_year: ?int}  $data
     */
    public function registerStudent(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => User::STATUS_ACTIVE,
            ]);

            $user->assignRole(Role::STUDENT);

            StudentProfile::create([
                'user_id' => $user->id,
                'grade_id' => $data['grade_id'],
                'birth_year' => $data['birth_year'] ?? null,
                'link_code' => StudentProfile::generateLinkCode(),
            ]);

            return $user;
        });
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
