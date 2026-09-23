<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Kênh gửi thông báo đẩy tới trình duyệt (Web Push + VAPID).
 *
 * Notification muốn dùng kênh này thì khai `toPush()` trả về mảng title/body/url.
 * Endpoint chết (404/410) được XOÁ ngay — người dùng gỡ app hoặc xoá dữ liệu trình duyệt
 * là endpoint hỏng vĩnh viễn, giữ lại chỉ tốn lượt gửi cho mọi thông báo sau.
 */
class WebPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! self::configured() || ! method_exists($notification, 'toPush')) {
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $notifiable->getKey())->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode($notification->toPush($notifiable), JSON_UNESCAPED_UNICODE);

        try {
            $webPush = new WebPush(['VAPID' => [
                'subject' => config('push.subject'),
                'publicKey' => config('push.public_key'),
                'privateKey' => config('push.private_key'),
            ]]);

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(self::toSubscription($subscription), $payload);
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    continue;
                }

                // 404/410 = endpoint không còn tồn tại. Lỗi khác (mạng, 5xx) thì giữ lại để lần sau thử.
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                }
            }
        } catch (Throwable $e) {
            // Đẩy hỏng không được làm hỏng cả lượt thông báo — email và chuông trong app vẫn phải tới.
            Log::warning('Gửi push thất bại', ['user' => $notifiable->getKey(), 'error' => $e->getMessage()]);
        }
    }

    public static function configured(): bool
    {
        return (bool) (config('push.public_key') && config('push.private_key'));
    }

    private static function toSubscription(PushSubscription $row): Subscription
    {
        return Subscription::create([
            'endpoint' => $row->endpoint,
            'publicKey' => $row->public_key,
            'authToken' => $row->auth_token,
            'contentEncoding' => 'aesgcm',
        ]);
    }
}
