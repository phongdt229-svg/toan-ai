<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateReferralSettingsRequest;
use App\Models\Referral;
use App\Models\ReferralSetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Quản trị → Giới thiệu bạn bè: mức thưởng + danh sách ai giới thiệu ai. */
class ReferralController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.referrals.index', [
            'settings' => ReferralSetting::current(),
            'referrals' => Referral::with('referrer:id,name,email', 'referred:id,name,email', 'rewardVoucher:id,code')
                ->latest('id')->paginate(30),
            'stats' => [
                'total' => Referral::count(),
                'rewarded' => Referral::where('status', Referral::STATUS_REWARDED)->count(),
                'void' => Referral::where('status', Referral::STATUS_VOID)->count(),
            ],
        ]);
    }

    public function update(UpdateReferralSettingsRequest $request): RedirectResponse
    {
        $settings = ReferralSetting::query()->firstOrNew();
        $old = $settings->exists ? $settings->only($settings->getFillable()) : null;

        $settings->fill([...$request->validated(), 'is_enabled' => $request->boolean('is_enabled')])->save();

        $this->audit->log('referral_settings.updated', $settings, $old, $settings->only($settings->getFillable()));

        return back()->with('status', 'Đã lưu cài đặt giới thiệu bạn bè. Mức mới áp dụng cho mã tặng từ giờ.');
    }
}
