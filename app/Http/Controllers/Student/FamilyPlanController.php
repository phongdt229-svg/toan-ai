<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\JoinFamilyPlanRequest;
use App\Http\Requests\Student\RemoveFamilyMemberRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\FamilyPlanService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/** Vào gói gia đình bằng mã / chủ gói gỡ thành viên / thành viên tự rời. */
class FamilyPlanController extends Controller
{
    public function __construct(private readonly FamilyPlanService $family) {}

    public function join(JoinFamilyPlanRequest $request): RedirectResponse
    {
        try {
            $subscription = $this->family->join($request->user(), $request->validated('join_code'));
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['join_code' => $e->getMessage()]);
        }

        return redirect()->route('student.subscription.index')
            ->with('status', 'Em đã vào gói gia đình '.$subscription->package->name.'.');
    }

    public function remove(RemoveFamilyMemberRequest $request, Subscription $subscription, User $member): RedirectResponse
    {
        try {
            $this->family->remove($subscription, $member, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $request->user()->is($member) ? 'Em đã rời gói gia đình.' : 'Đã gỡ '.$member->name.' khỏi gói.');
    }
}
