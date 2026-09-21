<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\UserAdminService;
use App\Services\Auth\AccountDeletionService;
use App\Services\Learning\StudentReportService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;

/** Quản lý người dùng (§25): tra cứu, xem hồ sơ theo vai trò, khoá / mở khoá. */
class UserController extends Controller
{
    public const STATUS_LABELS = [
        User::STATUS_ACTIVE => 'Hoạt động',
        User::STATUS_PENDING => 'Chờ duyệt',
        User::STATUS_SUSPENDED => 'Đã khoá',
        User::STATUS_REJECTED => 'Bị từ chối',
    ];

    public const ROLE_LABELS = [
        Role::STUDENT => 'Học sinh',
        Role::TEACHER => 'Giáo viên',
        Role::PARENT => 'Phụ huynh',
        Role::ADMIN => 'Quản trị',
    ];

    /** Giá trị riêng cho bộ lọc trạng thái: tài khoản đã yêu cầu xoá (soft delete). */
    public const FILTER_DELETED = 'deleted';

    public function __construct(
        private readonly UserAdminService $users,
        private readonly AccountDeletionService $deletion,
    ) {}

    public function index(Request $request): View
    {
        $role = $request->string('role')->toString();
        $status = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());

        return view('admin.users.index', [
            'users' => User::query()
                ->when($status === self::FILTER_DELETED, fn ($q) => $q->onlyTrashed())
                ->with('roles:id,name', 'studentProfile.grade')
                ->when(array_key_exists($role, self::ROLE_LABELS), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $role)))
                ->when(array_key_exists($status, self::STATUS_LABELS), fn ($q) => $q->where('status', $status))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")))
                ->latest('id')
                ->paginate(config('site.per_page'))
                ->withQueryString(),
            'role' => $role,
            'status' => $status,
            'search' => $search,
            // Tổng quan toàn hệ thống — cố ý không theo bộ lọc đang chọn, để luôn thấy được bức tranh chung.
            'roleChart' => [
                ['label' => self::ROLE_LABELS[Role::STUDENT], 'count' => $this->countByRole(Role::STUDENT), 'color' => '#3b82f6'],
                ['label' => self::ROLE_LABELS[Role::TEACHER], 'count' => $this->countByRole(Role::TEACHER), 'color' => '#16a34a'],
                ['label' => self::ROLE_LABELS[Role::PARENT], 'count' => $this->countByRole(Role::PARENT), 'color' => '#f97316'],
                ['label' => self::ROLE_LABELS[Role::ADMIN], 'count' => $this->countByRole(Role::ADMIN), 'color' => '#0f172a'],
            ],
            'signupsDaily' => $this->signupsDaily(),
        ]);
    }

    private function countByRole(string $role): int
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', $role))->count();
    }

    /**
     * Chuỗi 14 ngày cho biểu đồ. Ngày không có ai đăng ký vẫn có mặt (giá trị 0) để trục thời gian liền mạch.
     *
     * @return list<array{label: string, count: int}>
     */
    private function signupsDaily(): array
    {
        $since = today()->subDays(13);

        $rows = User::query()
            ->whereDate('created_at', '>=', $since)
            ->groupByRaw('DATE(created_at)')
            ->selectRaw('DATE(created_at) d, COUNT(*) c')
            ->pluck('c', 'd');

        return collect(range(0, 13))->map(function (int $i) use ($since, $rows) {
            $day = $since->copy()->addDays($i)->toDateString();

            return ['label' => Carbon::parse($day)->format('d/m'), 'count' => (int) ($rows[$day] ?? 0)];
        })->all();
    }

    public function show(User $user, SubscriptionService $subscriptions, StudentReportService $reports): View
    {
        $user->load('roles:id,name', 'studentProfile.grade', 'teacherProfile', 'parentProfile');

        $student = $user->isStudent() ? [
            'current' => $subscriptions->effective($user),
            'subscriptions' => $subscriptions->history($user)->take(10),
            'parents' => $user->linkedParents()->get(['users.id', 'users.name', 'users.email']),
            'classes' => $user->joinedClasses()->get(['classes.id', 'classes.name']),
            'curriculum_percent' => $reports->curriculumPercent($user),
            'average_score' => $reports->averageScoreOutOf10($user),
        ] : null;

        return view('admin.users.show', [
            'user' => $user,
            'student' => $student,
            'children' => $user->isParent() ? $user->linkedChildren()->with('studentProfile.grade')->get() : collect(),
            'teachingClasses' => $user->isTeacher() ? $user->teachingClasses()->withCount('activeStudents')->get() : collect(),
            'payments' => Payment::where('user_id', $user->id)->with('package')->latest('id')->limit(10)->get(),
            // Việc người này làm + việc người khác làm trên tài khoản này.
            'auditLogs' => AuditLog::query()
                ->with('user:id,name')
                ->where(fn ($q) => $q->where('user_id', $user->id)
                    ->orWhere(fn ($s) => $s->where('auditable_type', User::class)->where('auditable_id', $user->id)))
                ->latest('id')
                ->limit(15)
                ->get(),
        ]);
    }

    /** Khôi phục tài khoản trong thời gian chờ xoá. */
    public function restore(Request $request, User $user): RedirectResponse
    {
        try {
            $this->deletion->restore($user, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Đã khôi phục tài khoản {$user->name}.");
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:191']], [], ['reason' => 'lý do']);

        try {
            $this->users->suspend($request->user(), $user, $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Đã khoá tài khoản {$user->name}.");
    }

    public function reactivate(User $user): RedirectResponse
    {
        try {
            $this->users->reactivate($user);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Đã mở khoá tài khoản {$user->name}.");
    }
}
