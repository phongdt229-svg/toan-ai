<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\VoucherRedemption;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Số liệu báo cáo mã giảm giá cho trang Quản trị. Chỉ đếm lượt đã trả tiền xong
 * (`redeemed_at`) — lượt giữ chỗ hoặc đã trả về kho chưa phải tiền thật bị giảm.
 */
class VoucherReportService
{
    private const DAYS = 30;

    /** @return array{discount_total: int, redemptions: int, revenue_after: int, discount_rate: float} */
    public function summary(): array
    {
        $redeemed = VoucherRedemption::query()->whereNotNull('redeemed_at');

        $discount = (int) (clone $redeemed)->sum('discount_amount');
        $redemptions = (clone $redeemed)->count();

        // Doanh thu của các đơn có dùng mã — để so tỉ lệ giảm với số tiền thực thu.
        $revenue = (int) Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->whereNotNull('voucher_id')
            ->sum(DB::raw('amount - refunded_amount'));

        $gross = $revenue + $discount;

        return [
            'discount_total' => $discount,
            'redemptions' => $redemptions,
            'revenue_after' => $revenue,
            'discount_rate' => $gross > 0 ? round($discount / $gross * 100, 1) : 0.0,
        ];
    }

    /**
     * 30 ngày gần đây: số lượt dùng + tiền đã giảm. Ngày trống vẫn có mặt (0) để trục liền mạch.
     *
     * @return list<array{label: string, count: int, discount: int}>
     */
    public function daily(): array
    {
        $since = today()->subDays(self::DAYS - 1);

        $rows = VoucherRedemption::query()
            ->whereNotNull('redeemed_at')
            ->whereDate('redeemed_at', '>=', $since)
            ->groupByRaw('DATE(redeemed_at)')
            ->selectRaw('DATE(redeemed_at) d, COUNT(*) c, SUM(discount_amount) s')
            ->get()
            ->keyBy('d');

        return collect(range(0, self::DAYS - 1))->map(function (int $i) use ($since, $rows) {
            $day = $since->copy()->addDays($i)->toDateString();
            $row = $rows->get($day);

            return [
                'label' => Carbon::parse($day)->format('d/m'),
                'count' => (int) ($row->c ?? 0),
                'discount' => (int) ($row->s ?? 0),
            ];
        })->all();
    }

    /**
     * Top mã theo số lượt dùng (đã trả). Query thẳng bảng lượt dùng rồi nối mã —
     * tránh N+1 và tránh lazy loading khi shouldBeStrict bật.
     *
     * @return list<array{label: string, count: int, discount: int}>
     */
    public function topVouchers(int $limit = 6): array
    {
        return VoucherRedemption::query()
            ->join('vouchers', 'vouchers.id', '=', 'voucher_redemptions.voucher_id')
            ->whereNotNull('voucher_redemptions.redeemed_at')
            ->groupBy('vouchers.id', 'vouchers.code')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->get(['vouchers.code', DB::raw('COUNT(*) as c'), DB::raw('SUM(voucher_redemptions.discount_amount) as s')])
            ->map(fn ($r) => ['label' => $r->code, 'count' => (int) $r->c, 'discount' => (int) $r->s])
            ->all();
    }
}
