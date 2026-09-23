<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Database\Seeder;

/**
 * Mã giảm giá demo + lượt dùng gắn vào các đơn mẫu của SampleReportsSeeder — để trang
 * Quản trị → Mã giảm giá có biểu đồ với số liệu thật thay vì trống.
 *
 * Không đụng gói/kích hoạt: chỉ gắn mã lên đơn đã trả rồi trừ `amount` (số thực trả),
 * đúng cách PaymentService lưu đơn có mã. Chạy lại không nhân đôi (guard theo bảng vouchers).
 *
 * Chỉ chạy ở local/testing. Chạy riêng: php artisan db:seed --class=SampleVouchersSeeder
 */
class SampleVouchersSeeder extends Seeder
{
    public function run(): void
    {
        if (Voucher::count() > 0) {
            return;
        }

        mt_srand(20260923);

        $admin = User::where('email', 'admin@gmail.com')->first();
        $vouchers = $this->createVouchers($admin?->id);

        $paid = Payment::where('status', Payment::STATUS_PAID)
            ->whereNull('voucher_id')
            ->where('order_code', 'like', 'SEEDPAY%')
            ->with('package')
            ->orderBy('id')
            ->get();

        if ($paid->isEmpty()) {
            return;
        }

        // Khoảng 1/3 đơn đã trả có dùng mã; mã hot được chọn nhiều hơn để biểu đồ "top mã" không phẳng.
        $weights = ['CHAOHE' => 6, 'HOCSINHMOI' => 5, 'GIAM50K' => 4, 'BANBE20' => 3, 'PREMIUM15' => 2];
        $bag = collect($weights)->flatMap(fn ($w, $code) => array_fill(0, $w, $code))->all();
        $orderSeq = 1;

        foreach ($paid as $index => $payment) {
            if ($index % 3 !== 0) {
                continue;
            }

            $voucher = $this->pickVoucher($vouchers, $bag, $payment);

            if (! $voucher) {
                continue;
            }

            $price = (float) $payment->amount;
            $discount = $voucher->discountOn($price);

            $payment->update(['voucher_id' => $voucher->id, 'discount_amount' => $discount, 'amount' => $price - $discount]);

            VoucherRedemption::create([
                'voucher_id' => $voucher->id,
                'user_id' => $payment->user_id,
                'payment_id' => $payment->id,
                'discount_amount' => $discount,
                'redeemed_at' => $payment->paid_at,
                'created_at' => $payment->paid_at,
                'updated_at' => $payment->paid_at,
            ]);
        }

        // Vài đơn bỏ dở: lượt đã trả về kho (released_at) nên không tính vào "đã dùng".
        foreach ($paid->take(6) as $payment) {
            $voucher = $vouchers['GIAM50K'];
            $createdAt = now()->subDays(mt_rand(1, 25))->setTime(mt_rand(8, 21), mt_rand(0, 59));
            $price = (float) $payment->package->price;
            $discount = $voucher->discountOn($price);

            $failed = Payment::create([
                'order_code' => sprintf('SEEDVCH%06d', $orderSeq++),
                'user_id' => $payment->user_id,
                'package_id' => $payment->package_id,
                'voucher_id' => $voucher->id,
                'amount' => $price - $discount,
                'discount_amount' => $discount,
                'currency' => 'VND',
                'method' => 'momo',
                'status' => Payment::STATUS_CANCELLED,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            VoucherRedemption::create([
                'voucher_id' => $voucher->id,
                'user_id' => $payment->user_id,
                'payment_id' => $failed->id,
                'discount_amount' => $discount,
                'released_at' => $createdAt->copy()->addMinutes(30),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    /** @return array<string, Voucher> theo mã */
    private function createVouchers(?int $adminId): array
    {
        $rows = [
            ['code' => 'CHAOHE', 'description' => 'Chào hè — giảm 20%', 'type' => 'percent', 'value' => 20, 'max_discount' => 50000,
                'starts_at' => now()->subDays(90), 'ends_at' => now()->addDays(30), 'max_uses' => null],
            ['code' => 'HOCSINHMOI', 'description' => 'Học sinh mới — giảm 30k', 'type' => 'fixed', 'value' => 30000,
                'starts_at' => now()->subDays(120), 'ends_at' => null, 'max_uses' => 200],
            ['code' => 'GIAM50K', 'description' => 'Giảm 50k cho gói từ 99k', 'type' => 'fixed', 'value' => 50000, 'min_order_amount' => 99000,
                'starts_at' => now()->subDays(60), 'ends_at' => now()->addDays(60), 'max_uses' => 100],
            ['code' => 'BANBE20', 'description' => 'Rủ bạn — giảm 10%', 'type' => 'percent', 'value' => 10, 'max_discount' => 30000,
                'starts_at' => now()->subDays(45), 'ends_at' => null, 'max_uses' => 50],
            ['code' => 'PREMIUM15', 'description' => 'Ưu đãi gói Premium — giảm 15%', 'type' => 'percent', 'value' => 15,
                'starts_at' => now()->subDays(30), 'ends_at' => now()->addDays(15), 'max_uses' => null, 'only' => ['premium-thang', 'premium-nam']],
            ['code' => 'TET2026', 'description' => 'Tết 2026 — đã hết hạn', 'type' => 'percent', 'value' => 25, 'max_discount' => 100000,
                'starts_at' => now()->subDays(240), 'ends_at' => now()->subDays(200), 'max_uses' => 30],
            ['code' => 'THUBAY', 'description' => 'Tạm tắt', 'type' => 'fixed', 'value' => 20000,
                'starts_at' => null, 'ends_at' => null, 'max_uses' => null, 'is_active' => false],
        ];

        $vouchers = [];

        foreach ($rows as $row) {
            $only = $row['only'] ?? [];
            unset($row['only']);

            $voucher = Voucher::create($row + ['created_by' => $adminId, 'max_uses_per_user' => 2]);

            if ($only) {
                $voucher->packages()->sync(Package::whereIn('slug', $only)->pluck('id'));
            }

            $vouchers[$voucher->code] = $voucher->load('packages:id');
        }

        return $vouchers;
    }

    /** Chọn mã hợp lệ với đơn: đúng gói, đủ mức tối thiểu, và mã đã bắt đầu vào ngày trả. */
    private function pickVoucher(array $vouchers, array $bag, Payment $payment): ?Voucher
    {
        for ($try = 0; $try < 8; $try++) {
            $voucher = $vouchers[$bag[mt_rand(0, count($bag) - 1)]];

            $fits = $voucher->appliesTo($payment->package)
                && (! $voucher->min_order_amount || (float) $payment->amount >= (float) $voucher->min_order_amount)
                && (! $voucher->starts_at || $voucher->starts_at->lte($payment->paid_at))
                && (! $voucher->ends_at || $voucher->ends_at->gte($payment->paid_at));

            if ($fits) {
                return $voucher;
            }
        }

        return null;
    }
}
