<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Phụ huynh xem gói của từng con và mua/gia hạn cho con. */
class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): View
    {
        $parent = $request->user();
        $children = $parent->linkedChildren()->with('studentProfile.grade')->orderBy('name')->get();

        return view('parent.subscriptions.index', [
            'children' => $children->map(fn ($child) => [
                'student' => $child,
                'current' => $this->subscriptions->effective($child),
            ]),
            // Gói phụ huynh đã mua (kể cả cho con đã huỷ liên kết) — để đối chiếu chi tiêu.
            'purchases' => Subscription::query()
                ->where('purchased_by', $parent->id)
                ->with('package', 'user')
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }
}
