<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VoucherRequest;
use App\Models\Package;
use App\Models\Voucher;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Quản lý mã giảm giá (§8b).
 *
 * Mọi thay đổi đều ghi audit log: đây là thứ ảnh hưởng trực tiếp tới tiền thu về,
 * cùng nhóm với đổi giá gói.
 */
class VoucherController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        return view('admin.vouchers.index', [
            'vouchers' => Voucher::query()
                ->with('packages:id,name')
                ->withCount([
                    'redemptions as used_count' => fn ($q) => $q->whereNull('released_at'),
                    'redemptions as paid_count' => fn ($q) => $q->whereNotNull('redeemed_at'),
                ])
                ->withSum(
                    ['redemptions as discount_total' => fn ($q) => $q->whereNotNull('redeemed_at')],
                    'discount_amount'
                )
                ->latest('id')
                ->paginate(config('site.per_page'))
                ->withQueryString(),
        ]);
    }

    /** Danh sách lượt dùng của một mã — không có cái này thì không soi được mã bị lạm dụng. */
    public function show(Voucher $voucher): View
    {
        return view('admin.vouchers.show', [
            'voucher' => $voucher->load('packages:id,name', 'creator:id,name'),
            'redemptions' => $voucher->redemptions()
                ->with('user:id,name,email', 'payment:id,order_code,status,amount')
                ->latest('id')
                ->paginate(config('site.per_page')),
        ]);
    }

    public function create(): View
    {
        return view('admin.vouchers.form', [
            'voucher' => new Voucher(['type' => Voucher::TYPE_PERCENT, 'is_active' => true, 'max_uses_per_user' => 1]),
            'packages' => Package::ordered()->get(),
            'selected' => [],
        ]);
    }

    public function store(VoucherRequest $request): RedirectResponse
    {
        $voucher = DB::transaction(function () use ($request) {
            $voucher = Voucher::create($request->safe()->except('packages') + ['created_by' => $request->user()->id]);
            $voucher->packages()->sync($request->input('packages', []));

            return $voucher;
        });

        $this->audit->log('voucher.created', $voucher, null, $this->snapshot($voucher));

        return redirect()->route('admin.vouchers.index')->with('status', "Đã tạo mã {$voucher->code}.");
    }

    public function edit(Voucher $voucher): View
    {
        return view('admin.vouchers.form', [
            'voucher' => $voucher,
            'packages' => Package::ordered()->get(),
            'selected' => $voucher->packages()->pluck('packages.id')->all(),
        ]);
    }

    public function update(VoucherRequest $request, Voucher $voucher): RedirectResponse
    {
        $before = $this->snapshot($voucher);

        DB::transaction(function () use ($request, $voucher) {
            $voucher->update($request->safe()->except('packages'));
            $voucher->packages()->sync($request->input('packages', []));
        });

        $this->audit->log('voucher.updated', $voucher, $before, $this->snapshot($voucher->refresh()));

        return redirect()->route('admin.vouchers.index')->with('status', "Đã lưu mã {$voucher->code}.");
    }

    public function destroy(Voucher $voucher): RedirectResponse
    {
        // Mã đã có người dùng thì chỉ được tắt, không xoá — hoá đơn phải tra ngược được về mã.
        if ($voucher->redemptions()->exists()) {
            return back()->with('error', 'Mã đã có người dùng — hãy tắt thay vì xoá để giữ lịch sử đơn hàng.');
        }

        $snapshot = $this->snapshot($voucher);
        $voucher->delete();
        $this->audit->log('voucher.deleted', null, $snapshot, null);

        return redirect()->route('admin.vouchers.index')->with('status', 'Đã xoá mã.');
    }

    /** @return array<string, mixed> */
    private function snapshot(Voucher $voucher): array
    {
        return $voucher->only([
            'code', 'type', 'value', 'max_discount', 'min_order_amount',
            'starts_at', 'ends_at', 'max_uses', 'max_uses_per_user', 'is_active',
        ]);
    }
}
