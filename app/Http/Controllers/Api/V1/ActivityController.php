<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\RecordActivityRequest;
use App\Services\Admin\ImpersonationService;
use App\Services\Learning\ActivityService;
use Illuminate\Http\Response;

class ActivityController extends Controller
{
    public function __invoke(RecordActivityRequest $request, ActivityService $activity): Response
    {
        // Người hỗ trợ đang mượn tài khoản thì không được cộng giờ học cho học sinh — phụ huynh sẽ thấy số giả.
        if (! $request->session()->has(ImpersonationService::SESSION_KEY)) {
            $student = $request->user();
            $activity->heartbeat($student, $request->boolean('active'));
            $activity->recordEvents($student, $request->validated('events') ?? []);
        }

        return response()->noContent();
    }
}
