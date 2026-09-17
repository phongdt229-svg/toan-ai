<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\AI\AiUsageGuard;
use App\Services\Learning\PracticeService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "Gói của tôi": gói đang dùng, lượt còn lại hôm nay, lịch sử gói. */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly AiUsageGuard $aiUsage,
        private readonly PracticeService $practice,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $current = $this->subscriptions->effective($user);

        return view('student.subscription.index', [
            'current' => $current,
            'package' => $current?->package ?? $this->subscriptions->defaultPackage(),
            'aiUsage' => $this->aiUsage->status($user),
            'practiceUsage' => $this->practice->dailyStatus($user),
            'history' => $this->subscriptions->history($user),
        ]);
    }
}
