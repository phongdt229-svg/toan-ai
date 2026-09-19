<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Support\NotificationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/** Thông báo trong app — dùng chung cho cả 4 portal (§10, §14, §25). */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(config('site.per_page')),
        ]);
    }

    /** Đánh dấu đã đọc rồi chuyển tới đích — một lượt bấm, không cần JS. */
    public function open(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable_id === $request->user()->id
            && $notification->notifiable_type === $request->user()->getMorphClass(), 404);

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return $notification->data['url'] ?? null
            ? redirect($notification->data['url'])
            : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /** Lưu danh sách loại thông báo còn BẬT — phần còn lại (không gửi lên) bị coi là tắt. */
    public function updatePreferences(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $enabled = $request->validated('enabled', []);
        $muted = array_values(array_diff(array_keys(NotificationType::LABELS), $enabled));

        $request->user()->update(['notification_preferences' => $muted]);

        return back()->with('status', 'Đã lưu cài đặt thông báo.');
    }
}
