<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\SubscriptionExpiringSoon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Nhắc gói sắp hết hạn ở các mốc 7 / 3 / 1 ngày.
 *
 * Tách khỏi `subscriptions:expire` (chạy 00:05) và hẹn 08:00: không ai muốn nhận email
 * gia hạn lúc nửa đêm. Cột `expiry_reminded_days` giữ ngưỡng đã nhắc gần nhất nên chạy lại
 * trong ngày cũng không gửi trùng.
 */
class RemindExpiringSubscriptions extends Command
{
    /** Mốc nhắc, từ xa tới gần. */
    public const THRESHOLDS = [7, 3, 1];

    protected $signature = 'subscriptions:remind-expiring';

    protected $description = 'Nhắc người dùng và người trả tiền khi gói sắp hết hạn (7/3/1 ngày)';

    public function handle(): int
    {
        $sent = 0;

        Subscription::query()
            ->effective()
            ->with('user', 'purchaser', 'package')
            ->where('ends_at', '<=', now()->addDays(max(self::THRESHOLDS))->endOfDay())
            ->each(function (Subscription $subscription) use (&$sent) {
                // Gói Free không có gì để gia hạn.
                if ($subscription->package->isFree()) {
                    return;
                }

                $daysLeft = max(1, $subscription->daysLeft());
                $threshold = $this->thresholdFor($daysLeft);

                if ($threshold === null) {
                    return;
                }

                // Đã nhắc ở mốc này hoặc mốc gần hơn rồi thì thôi.
                if ($subscription->expiry_reminded_days !== null && $subscription->expiry_reminded_days <= $threshold) {
                    return;
                }

                // Học sinh dùng gói và phụ huynh trả tiền là hai người khác nhau —
                // người trả tiền mới là người quyết định gia hạn.
                $recipients = collect([$subscription->user, $subscription->purchaser])
                    ->filter()
                    ->unique('id');

                Notification::send($recipients, new SubscriptionExpiringSoon($subscription, $daysLeft));

                $subscription->forceFill(['expiry_reminded_days' => $threshold])->saveQuietly();
                $sent += $recipients->count();
            });

        $this->info("Đã gửi {$sent} lượt nhắc gia hạn.");

        return self::SUCCESS;
    }

    /**
     * Mốc nhắc tương ứng với số ngày còn lại; null nghĩa là chưa tới mốc nào.
     *
     * Lấy mốc NHỎ NHẤT còn ≥ số ngày còn lại: còn 1 ngày phải rơi vào mốc 1, không phải mốc 7,
     * nếu không thì nhắc một lần rồi im luôn cho tới lúc hết hạn.
     */
    private function thresholdFor(int $daysLeft): ?int
    {
        $ascending = self::THRESHOLDS;
        sort($ascending);

        foreach ($ascending as $threshold) {
            if ($daysLeft <= $threshold) {
                return $threshold;
            }
        }

        return null;
    }
}
