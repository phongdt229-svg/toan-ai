<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordOnboardingRequest;
use App\Services\Admin\ImpersonationService;
use App\Services\OnboardingService;
use Illuminate\Http\Response;

class OnboardingController extends Controller
{
    public function __invoke(RecordOnboardingRequest $request, OnboardingService $onboarding): Response
    {
        // Người hỗ trợ đang đăng nhập hộ xem tour không được làm mất lời mời tour của chính học sinh.
        if (! $request->session()->has(ImpersonationService::SESSION_KEY)) {
            $onboarding->record($request->user(), $request->validated('key'), $request->validated('status'), $request->validated('step'));
        }

        return response()->noContent();
    }
}
