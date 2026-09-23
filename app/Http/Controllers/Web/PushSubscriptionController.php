<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Trình duyệt bật/tắt nhận thông báo đẩy. Gọi bằng fetch từ trang Cài đặt → Thông báo. */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(WebPushChannel::configured(), 404);

        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:191'],
            'keys.auth' => ['required', 'string', 'max:191'],
        ]);

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
