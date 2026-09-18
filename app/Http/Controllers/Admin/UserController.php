<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\UserAdminService;
use App\Services\Learning\StudentReportService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function __construct(private readonly UserAdminService $users) {}

    public function index(Request $request): View
    {
        $role = $request->string('role')->toString();
        $status = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());

        return view('admin.users.index', [
            'users' => User::query()
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
        ]);
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
