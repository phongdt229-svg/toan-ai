<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\AI\AiUsageGuard;
use App\Services\AI\TutorAccessGuard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiTutorController extends Controller
{
    public function __construct(
        private readonly AiUsageGuard $usage,
        private readonly TutorAccessGuard $access,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('student.ai.index', [
            'usage' => $this->usage->status($user),
            'examInProgress' => $this->access->hasExamInProgress($user),
            'conversations' => AiConversation::query()
                ->where('user_id', $user->id)
                ->latest('last_message_at')
                ->limit(30)
                ->get(),
        ]);
    }
}
