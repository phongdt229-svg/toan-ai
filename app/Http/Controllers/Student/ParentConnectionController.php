<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Parenting\ChildLinkService;
use App\Support\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Học sinh chia sẻ mã / link / QR cho phụ huynh, và kiểm soát ai đang xem kết quả học của mình.
 */
class ParentConnectionController extends Controller
{
    public function __construct(private readonly ChildLinkService $links) {}

    public function index(Request $request): View
    {
        $student = $request->user()->load('studentProfile');
        $shareUrl = $this->links->shareUrl($student);

        return view('student.parents.index', [
            'code' => $student->studentProfile?->link_code,
            'shareUrl' => $shareUrl,
            'qrSvg' => QrCode::svg($shareUrl),
            'linkDays' => ChildLinkService::SHARE_LINK_DAYS,
            'parents' => $student->linkedParents()->orderBy('parent_children.linked_at')->get(),
        ]);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $this->links->regenerateCode($request->user());

        return back()->with('status', 'Đã đổi mã. Mã, link và QR cũ không dùng được nữa.');
    }

    public function revoke(Request $request, User $parent): RedirectResponse
    {
        abort_unless($request->user()->linkedParents()->where('users.id', $parent->id)->exists(), 404);

        $this->links->revokeByStudent($request->user(), $parent);

        return back()->with('status', "Đã ngừng chia sẻ với {$parent->name}. Mã liên kết đã được đổi.");
    }
}
