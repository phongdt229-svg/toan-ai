<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Notifications\TeacherAccountApproved;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeacherApprovalController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $teachers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::TEACHER))
            ->where('status', User::STATUS_PENDING)
            ->with('teacherProfile')
            ->latest()
            ->paginate(config('site.per_page'));

        return view('admin.teachers.pending', ['teachers' => $teachers]);
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isTeacher() && $user->isPending(), 404);
        $user->load('teacherProfile');

        DB::transaction(function () use ($request, $user) {
            $user->update(['status' => User::STATUS_ACTIVE]);
            $user->teacherProfile?->update([
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
                'reject_reason' => null,
            ]);

            $this->audit->log('teacher.approved', $user, ['status' => User::STATUS_PENDING], ['status' => User::STATUS_ACTIVE]);
        });

        $user->notify(new TeacherAccountApproved);

        return back()->with('status', "Đã duyệt giáo viên {$user->name}.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isTeacher() && $user->isPending(), 404);
        $user->load('teacherProfile');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:191'],
        ], [], ['reason' => 'lý do']);

        DB::transaction(function () use ($validated, $user) {
            $user->update(['status' => User::STATUS_REJECTED]);
            $user->teacherProfile?->update(['reject_reason' => $validated['reason']]);

            $this->audit->log('teacher.rejected', $user, null, ['reason' => $validated['reason']]);
        });

        return back()->with('status', "Đã từ chối giáo viên {$user->name}.");
    }
}
