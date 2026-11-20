<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\RecordActivityRequest;
use App\Services\Admin\ImpersonationService;
use App\Services\Learning\ActivityService;
use App\Services\Rewards\RewardService;
use Illuminate\Http\Response;

class ActivityController extends Controller
{
    public function __invoke(RecordActivityRequest $request, ActivityService $activity, RewardService $rewards): Response
    {
        // Người hỗ trợ đang mượn tài khoản thì không được cộng giờ học cho học sinh — phụ huynh sẽ thấy số giả.
        if (! $request->session()->has(ImpersonationService::SESSION_KEY)) {
            $student = $request->user();
            $day = $activity->heartbeat($student, $request->boolean('active'));
            // Vừa học đủ ngưỡng chuỗi ngày hôm nay → điểm "giữ chuỗi" (một lần/ngày, service tự chặn trùng).
            $rewards->forStudyDay($student, $day);
            $activity->recordEvents($student, $request->validated('events') ?? []);
        }

        return response()->noContent();
    }
}
