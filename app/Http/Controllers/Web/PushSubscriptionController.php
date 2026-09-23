<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\PushSubscriptionRequest;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Trình duyệt bật/tắt nhận thông báo đẩy. Gọi bằng fetch từ trang Cài đặt → Thông báo. */
class PushSubscriptionController extends Controller
{
    public function store(PushSubscriptionRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Cùng một trình duyệt đăng ký lại thì ghi đè, không đẻ thêm dòng.
        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $request->user()->id,
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'user_agent' => Str::limit((string) $request->userAgent(), 190, ''),
            ],
        );

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $endpoint = $request->string('endpoint')->toString();

        // Chỉ xoá đăng ký của chính mình — endpoint người khác gửi lên không được đụng tới.
        PushSubscription::where('user_id', $request->user()->id)
            ->when($endpoint !== '', fn ($q) => $q->where('endpoint', $endpoint))
            ->delete();

        return response()->json(['success' => true]);
    }
}
