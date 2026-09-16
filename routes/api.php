<?php

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
