<?php

namespace App\Services\Teaching;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassService
{
    public function __construct(private readonly AssignmentService $assignments) {}

    /** @param  array{name: string, grade_id: int, description?: ?string}  $data */
    public function create(array $data, User $teacher): SchoolClass
    {
        return DB::transaction(function () use ($data, $teacher) {
            $class = SchoolClass::create([
                'name' => $data['name'],
                'grade_id' => $data['grade_id'],
                'description' => $data['description'] ?? null,
                'code' => SchoolClass::generateCode(),
                'owner_teacher_id' => $teacher->id,
                'status' => SchoolClass::STATUS_ACTIVE,
            ]);

            $class->teachers()->attach($teacher->id, ['role' => 'owner']);

            return $class;
        });
    }

    /** Học sinh tự vào lớp bằng mã (§13). */
    public function joinByCode(User $student, string $code): SchoolClass
    {
        $class = SchoolClass::query()
            ->active()
            ->where('code', SchoolClass::normalizeCode($code))
            ->first();

        if (! $class) {
            throw ValidationException::withMessages(['code' => 'Mã lớp không tồn tại hoặc lớp đã đóng.']);
        }

        $this->enroll($class, $student);

        return $class;
    }

    /** Giáo viên thêm học sinh bằng email. */
    public function addStudentByEmail(SchoolClass $class, string $email): User
    {
        $student = User::query()
            ->where('email', $email)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::STUDENT))
            ->first();

        if (! $student) {
            throw ValidationException::withMessages(['email' => 'Không tìm thấy tài khoản học sinh với email này.']);
        }

        $this->enroll($class, $student);

        return $student;
    }

    /**
     * Vào lớp (hoặc quay lại sau khi bị xoá). Học sinh vào sau vẫn nhận các bài
     * giao cho "cả lớp" còn đang mở — nếu không, bảng theo dõi sẽ thiếu người.
     */
    public function enroll(SchoolClass $class, User $student): void
    {
        DB::transaction(function () use ($class, $student) {
            $existing = DB::table('class_students')
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->first();

            if (! $existing) {
                $class->students()->attach($student->id, ['status' => 'active', 'joined_at' => now()]);
            } elseif ($existing->status !== 'active') {
                $class->students()->updateExistingPivot($student->id, ['status' => 'active', 'joined_at' => now()]);
            } else {
                return; // đã ở trong lớp — nhập mã lần nữa không làm gì
            }

            $this->assignments->deliverOpenClassAssignments($class, $student);
        });
    }

    /** Rời lớp nhưng giữ lịch sử bài làm/điểm. */
    public function removeStudent(SchoolClass $class, User $student): void
    {
        $class->students()->updateExistingPivot($student->id, ['status' => 'removed']);
    }

    public function addAssistant(SchoolClass $class, string $email): User
    {
        $teacher = User::query()
            ->where('email', $email)
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::TEACHER))
            ->first();

        if (! $teacher) {
            throw ValidationException::withMessages(['email' => 'Không tìm thấy giáo viên đã được duyệt với email này.']);
        }

        if ($class->hasTeacher($teacher)) {
            throw ValidationException::withMessages(['email' => 'Giáo viên này đã ở trong lớp.']);
        }

        $class->teachers()->attach($teacher->id, ['role' => 'assistant']);

        return $teacher;
    }

    public function regenerateCode(SchoolClass $class): string
    {
        $class->update(['code' => SchoolClass::generateCode()]);

        return $class->code;
    }
}
