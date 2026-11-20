<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\FamilyPlanService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Phụ huynh xem gói của từng con và mua/gia hạn cho con. */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly FamilyPlanService $families,
    ) {}

    public function index(Request $request): View
    {
        $parent = $request->user();
        $children = $parent->linkedChildren()->with('studentProfile.grade')->orderBy('name')->get();

        return view('parent.subscriptions.index', [
            'children' => $children->map(function ($child) {
                // Bố mẹ mua gói gia đình đứng tên một con thì cần thấy MÃ để đưa cho các con còn lại.
                $family = $this->families->ownedBy($child);

                return [
                    'student' => $child,
                    'current' => $this->subscriptions->effective($child),
                    'family' => $family,
                    'familySeats' => $family ? $this->families->seatsLeft($family) : 0,
                    'memberOf' => $family ? null : $this->families->memberOf($child),
                ];
            }),
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
