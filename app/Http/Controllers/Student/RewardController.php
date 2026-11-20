<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Rewards\BadgeService;
use App\Services\Rewards\RewardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Thành tích: điểm thưởng, huy hiệu, bảng xếp hạng tuần trong các lớp em đang học. */
class RewardController extends Controller
{
    public function __construct(
        private readonly RewardService $rewards,
        private readonly BadgeService $badges,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user()->load('studentProfile', 'joinedClasses');

        return view('student.rewards.index', [
            'total' => $this->rewards->total($user),
            'week' => $this->rewards->sinceWeekStart($user),
            'history' => $this->rewards->history($user),
            'rules' => $this->rewards->rules(),
            'badges' => $this->badges->catalogFor($user),
            'boards' => $user->joinedClasses->map(fn ($class) => [
                'class' => $class,
                'rows' => $this->rewards->leaderboard($class, $user),
            ]),
            'hidden' => (bool) $user->studentProfile?->hide_from_leaderboard,
        ]);
    }
}
