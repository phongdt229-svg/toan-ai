<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\Assignment;
use App\Models\ParentChild;
use App\Models\PlacementTest;
use App\Models\Question;
use App\Models\SchoolClass;
use App\Models\StudySchedule;
use App\Models\StudySession;
use App\Models\User;
use App\Models\UserOnboarding;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Hướng dẫn người mới (OB-01 → OB-03, OB-06).
 *
 * - Danh sách "Bắt đầu": mỗi mục là một điều kiện trên DỮ LIỆU THẬT — làm việc đó ở đâu cũng tự tích,
 *   không có nút "đánh dấu xong" để bấm cho có.
 * - Tour: chỉ MỜI (OB-D2), không tự bật — trẻ nhỏ dễ bấm nhầm khi màn hình bị làm mờ đột ngột.
 *   Đã xem / bỏ qua thì không mời lại; muốn xem lại thì vào menu người dùng → "Hướng dẫn trang này".
 */
class OnboardingService
{
    public const STATUSES = ['completed', 'dismissed', 'visited', 'hidden'];

    /** @return array<string, Closure(User): bool> key mục → điều kiện "đã xong" */
    private function conditions(): array
    {
        $children = fn (User $u) => ParentChild::where('parent_id', $u->id)->where('status', ParentChild::STATUS_LINKED)->pluck('student_id');

        return [
            // Học sinh
            'placement' => fn (User $u) => PlacementTest::where('user_id', $u->id)->where('status', PlacementTest::STATUS_GRADED)->exists(),
            'schedule' => fn (User $u) => StudySchedule::where('student_id', $u->id)->exists(),
            'first_session' => fn (User $u) => StudySession::where('user_id', $u->id)->where('status', StudySession::STATUS_DONE)->exists(),
            'ai_tutor' => fn (User $u) => AiConversation::where('user_id', $u->id)->exists(),
            'link_parent' => fn (User $u) => ParentChild::where('student_id', $u->id)->where('status', ParentChild::STATUS_LINKED)->exists(),
            // Phụ huynh
            'link_child' => fn (User $u) => $children($u)->isNotEmpty(),
            'child_schedule' => fn (User $u) => StudySchedule::whereIn('student_id', $children($u))->exists(),
            'view_child' => fn (User $u) => $this->has($u, 'visit:parent.children.show'),
            'daily_report' => fn (User $u) => (bool) $u->parentProfile?->daily_report_enabled,
            // Giáo viên
            'teacher_profile' => fn (User $u) => filled($u->teacherProfile?->school) && filled($u->teacherProfile?->subject),
            'create_class' => fn (User $u) => SchoolClass::query()->taughtBy($u)->exists(),
            'class_students' => fn (User $u) => DB::table('class_students')
                ->whereIn('class_id', SchoolClass::query()->taughtBy($u)->select('classes.id'))->exists(),
            'questions' => fn (User $u) => Question::where('created_by', $u->id)->exists(),
            'assignment' => fn (User $u) => Assignment::where('teacher_id', $u->id)->exists(),
        ];
    }

    /** Khoá điều kiện đã cài — OnboardingTest dùng để bắt mục khai trong config mà quên viết điều kiện. */
    public function knownKeys(): array
    {
        return array_keys($this->conditions());
    }

    public function roleOf(User $user): ?string
    {
        return match (true) {
            $user->isStudent() => 'student',
            $user->isParent() => 'parent',
            $user->isTeacher() => 'teacher',
            default => null,
        };
    }

    /**
     * @return array{items: array<int, array{key: string, label: string, hint: string, url: string, done: bool}>, done: int, total: int, hidden: bool}|null
     */
    public function checklist(User $user): ?array
    {
        $role = $this->roleOf($user);
        $items = config("onboarding.checklists.{$role}", []);

        if (! $role || $items === []) {
            return null;
        }

        $user->loadMissing('parentProfile', 'teacherProfile');
        $conditions = $this->conditions();

        $items = array_map(fn ($item) => [
            'key' => $item['key'],
            'label' => $item['label'],
            'hint' => $item['hint'],
            'url' => route($item['route']),
            'done' => isset($conditions[$item['key']]) && $conditions[$item['key']]($user),
        ], $items);

        $done = count(array_filter($items, fn ($i) => $i['done']));

        return [
            'items' => $items,
            'done' => $done,
            'total' => count($items),
            'hidden' => $this->has($user, 'checklist:hidden') || $done === count($items),
        ];
    }

    /**
     * Tour của trang hiện tại (theo tên route) + có nên mời không.
     *
     * @return array{key: string, steps: array<int, array{target: string, title: string, text: string}>, invite: bool}|null
     */
    public function tourFor(User $user, ?string $routeName): ?array
    {
        // Tra thẳng mảng: tên route có dấu chấm ("student.dashboard"), config('a.b.c') sẽ hiểu thành khoá lồng.
        $steps = $routeName ? (config('onboarding.tours', [])[$routeName] ?? null) : null;

        if (! $steps) {
            return null;
        }

        return [
            'key' => "tour:{$routeName}",
            'steps' => array_values($steps),
            'invite' => ! $this->has($user, "tour:{$routeName}"),
        ];
    }

    public function record(User $user, string $key, string $status, ?int $step = null): UserOnboarding
    {
        $row = UserOnboarding::firstOrNew(['user_id' => $user->id, 'key' => $key]);

        // Đã xem hết thì giữ "completed" — bấm Bỏ qua ở lần xem lại không làm mất mốc đó.
        if ($row->exists && $row->status === 'completed' && $status === 'dismissed') {
            return $row;
        }

        $row->fill(['status' => $status, 'step' => $step])->save();

        return $row;
    }

    public function markVisited(User $user, string $routeName): void
    {
        UserOnboarding::firstOrCreate(['user_id' => $user->id, 'key' => "visit:{$routeName}"], ['status' => 'visited']);
    }

    /**
     * OB-06: người đăng ký trong 30 ngày qua — bao nhiêu % xong danh sách, tour nào hay bị bỏ ở bước mấy.
     * Chỉ đếm trong DB của mình, không gửi sự kiện tour ra công cụ đo lường bên ngoài.
     *
     * @return array{new_users: int, checklist_done: int, checklist_percent: ?int, tours: array<string, array{completed: int, dismissed: int, top_drop_step: ?int}>}
     */
    public function stats(): array
    {
        $users = User::where('created_at', '>=', now()->subDays(30))
            ->where('status', User::STATUS_ACTIVE)
            ->with('roles', 'parentProfile', 'teacherProfile')
            ->limit(500)
            ->get();

        $eligible = $users->filter(fn ($u) => $this->roleOf($u) !== null);
        $done = $eligible->filter(fn ($u) => ($c = $this->checklist($u)) && $c['done'] === $c['total'])->count();

        $tours = UserOnboarding::where('key', 'like', 'tour:%')
            ->get(['key', 'status', 'step'])
            ->groupBy('key')
            ->map(fn ($rows) => [
                'completed' => $rows->where('status', 'completed')->count(),
                'dismissed' => $rows->where('status', 'dismissed')->count(),
                'top_drop_step' => $rows->where('status', 'dismissed')->whereNotNull('step')->countBy('step')->sortDesc()->keys()->first(),
            ])
            ->all();

        return [
            'new_users' => $eligible->count(),
            'checklist_done' => $done,
            'checklist_percent' => $eligible->isNotEmpty() ? (int) round($done / $eligible->count() * 100) : null,
            'tours' => $tours,
        ];
    }

    private function has(User $user, string $key): bool
    {
        return UserOnboarding::where('user_id', $user->id)->where('key', $key)->exists();
    }
}
