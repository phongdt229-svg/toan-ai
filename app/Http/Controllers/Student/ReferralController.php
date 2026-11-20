<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Referral\ReferralService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referrals) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $enabled = $this->referrals->enabled();

        return view('student.referrals.index', [
            'enabled' => $enabled,
            'settings' => $this->referrals->settings(),
            // Chương trình tắt thì không sinh mã mới cho ai.
            'link' => $enabled ? route('referral.link', $this->referrals->codeFor($user)) : null,
            'summary' => $this->referrals->summaryFor($user),
        ]);
    }
}
