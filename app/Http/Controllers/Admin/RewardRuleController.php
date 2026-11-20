<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRewardRulesRequest;
use App\Models\RewardPoint;
use App\Models\RewardRule;
use App\Models\UserBadge;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Quản trị → Điểm thưởng: số điểm mỗi hoạt động. Đổi điểm chỉ áp dụng cho lần cộng SAU, sổ cũ giữ nguyên. */
class RewardRuleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.rewards.index', [
            'rules' => RewardRule::orderBy('id')->get(),
            'weekPoints' => (int) RewardPoint::where('created_at', '>=', now()->startOfWeek())->sum('points'),
            'earners' => RewardPoint::where('created_at', '>=', now()->startOfWeek())->distinct('user_id')->count('user_id'),
            'badgeCounts' => UserBadge::selectRaw('badge_key, COUNT(*) as n')->groupBy('badge_key')->pluck('n', 'badge_key'),
        ]);
    }

    public function update(UpdateRewardRulesRequest $request): RedirectResponse
    {
        $input = $request->validated('rules');

        DB::transaction(function () use ($input) {
            foreach (RewardRule::whereIn('id', array_keys($input))->get() as $rule) {
                $rule->update([
                    'points' => (int) $input[$rule->id]['points'],
                    'is_active' => (bool) ($input[$rule->id]['is_active'] ?? false),
                ]);
            }
        });

        $this->audit->log('reward_rules.updated', null, null, ['rules' => $input]);

        return back()->with('status', 'Đã lưu điểm thưởng. Mức mới áp dụng từ lần cộng điểm tiếp theo.');
    }
}
