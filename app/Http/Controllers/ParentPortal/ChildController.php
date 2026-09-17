<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Learning\StudentReportService;
use App\Services\Parenting\ChildLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChildController extends Controller
{
    public function __construct(
        private readonly ChildLinkService $links,
        private readonly StudentReportService $reports,
    ) {}

    public function linkForm(): View
    {
        return view('parent.children.link');
    }

    public function link(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('child.link'), 403);

        $data = $request->validate(
            ['link_code' => ['required', 'string', 'max:16']],
            [],
            ['link_code' => 'mã liên kết'],
        );

        $child = $this->links->linkByCode($request->user(), $data['link_code']);

        return redirect()->route('parent.children.show', $child)
            ->with('status', "Đã liên kết với {$child->name}.");
    }

    /**
     * Mở từ link / QR con chia sẻ. Route có middleware `signed` nên mã trên URL không bị sửa,
     * và link tự hết hạn. Vẫn hỏi xác nhận để phụ huynh biết mình đang liên kết với ai.
     */
    public function accept(Request $request): View|RedirectResponse
    {
        $student = $this->links->studentForCode((string) $request->query('code'));

        if (! $student) {
            return redirect()->route('parent.children.link')
                ->with('error', 'Link liên kết không còn hiệu lực — có thể con đã đổi mã. Hãy xin mã mới.');
        }

        if ($request->user()->isParentOf($student)) {
            return redirect()->route('parent.children.show', $student)
                ->with('status', "Bạn đã liên kết với {$student->name} từ trước.");
        }

        return view('parent.children.accept', [
            'student' => $student->load('studentProfile.grade'),
            'code' => $request->query('code'),
        ]);
    }

    public function show(Request $request, User $student): View
    {
        abort_unless($request->user()->isParentOf($student), 403);

        return view('parent.children.show', [
            'student' => $student,
            'report' => $this->reports->summary($student),
        ]);
    }

    public function unlink(Request $request, User $student): RedirectResponse
    {
        abort_unless($request->user()->isParentOf($student), 403);

        $this->links->unlink($request->user(), $student);

        return redirect()->route('parent.dashboard')
            ->with('status', "Đã huỷ liên kết với {$student->name}.");
    }
}
