<?php

use App\Http\Controllers\Api\V1\AiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1  (prefix 'api/v1' được đặt trong bootstrap/app.php)
|--------------------------------------------------------------------------
| Các nhóm endpoint đầy đủ xem PROJECT_PLAN.md §6. Phase 1 mới có /me.
*/

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    $user = $request->user()->load('roles:id,name,display_name', 'studentProfile.grade');

    return response()->json([
        'success' => true,
        'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'roles' => $user->roles->pluck('name'),
            'grade' => $user->studentProfile?->grade?->name,
            // Phase 8: bổ sung 'subscription' => tier hiện tại.
        ],
    ]);
})->name('api.me');

/*
| AI Tutor (§10). Nhóm `web` để widget trên trang gọi bằng session + CSRF, không cần token riêng.
| throttle:ai giới hạn theo phút (chống spam); quota theo ngày do AiUsageGuard kiểm.
*/
Route::middleware(['web', 'auth', 'active', 'throttle:ai'])
    ->prefix('ai')
    ->name('api.ai.')
    ->controller(AiController::class)
    ->group(function () {
        Route::post('chat', 'chat')->name('chat');
        Route::post('hint', 'hint')->name('hint');
        Route::post('explain', 'explain')->name('explain');
        Route::post('check-answer', 'checkAnswer')->name('check-answer');
        Route::post('similar-exercise', 'similarExercise')->name('similar-exercise');
        Route::post('analyze-mistake', 'analyzeMistake')->name('analyze-mistake');

        Route::get('usage', 'usage')->name('usage')->withoutMiddleware('throttle:ai');
        Route::get('conversations', 'conversations')->name('conversations')->withoutMiddleware('throttle:ai');
        Route::get('conversations/{id}', 'conversation')->whereNumber('id')->name('conversation')->withoutMiddleware('throttle:ai');
    });
