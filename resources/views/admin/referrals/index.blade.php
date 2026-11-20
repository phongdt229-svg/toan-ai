@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Giới thiệu bạn bè — Quản trị Math AI')
@section('page_title', 'Giới thiệu bạn bè')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-4"><div class="card border"><div class="card-body">
            <div class="small text-secondary">Lượt giới thiệu</div><div class="h4 fw-bold mb-0">{{ $stats['total'] }}</div>
        </div></div></div>
        <div class="col-4"><div class="card border"><div class="card-body">
            <div class="small text-secondary">Đã mua gói</div><div class="h4 fw-bold mb-0">{{ $stats['rewarded'] }}</div>
        </div></div></div>
        <div class="col-4"><div class="card border"><div class="card-body">
            <div class="small text-secondary">Huỷ do hoàn tiền</div><div class="h4 fw-bold mb-0">{{ $stats['void'] }}</div>
        </div></div></div>
    </div>

    <form method="POST" action="{{ route('admin.referrals.update') }}" class="card border mb-4">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="form-check form-switch mb-3">
                <input type="hidden" name="is_enabled" value="0">
                <input class="form-check-input" type="checkbox" role="switch" id="ref-enabled" name="is_enabled" value="1" @checked(old('is_enabled', $settings->is_enabled))>
                <label class="form-check-label" for="ref-enabled">Bật chương trình (tắt: link không còn tác dụng, mã đã tặng vẫn dùng được tới hạn)</label>
            </div>
            <div class="row g-3">
                @foreach ([
                    'new_user_percent' => ['Bạn mới được giảm (%)', 0, 50],
                    'referrer_percent' => ['Người giới thiệu được giảm (%)', 0, 50],
                    'voucher_valid_days' => ['Hạn dùng mã (ngày)', 7, 365],
                    'max_rewards_per_referrer' => ['Số lần thưởng tối đa / người', 0, 1000],
                ] as $field => [$label, $min, $max])
                    <div class="col-6 col-lg-3">
                        <label for="ref-{{ $field }}" class="form-label small">{{ $label }}</label>
                        <input type="number" id="ref-{{ $field }}" name="{{ $field }}" min="{{ $min }}" max="{{ $max }}" required
                               value="{{ old($field, $settings->{$field}) }}" class="form-control @error($field) is-invalid @enderror">
                        @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>
            <p class="form-text mb-0 mt-2">
                Người giới thiệu chỉ được thưởng khi bạn mới trả tiền thật (không tính mã giảm 100%). Hoàn đủ tiền đơn đó thì mã thưởng
                chưa dùng tự tắt. Mã quà là mã riêng — chỉ tài khoản được tặng dùng được, không hiện ở trang Gói học.
            </p>
            <button class="btn btn-primary btn-touch mt-3">Lưu</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm small align-middle">
            <thead><tr><th>Người giới thiệu</th><th>Bạn mới</th><th>Trạng thái</th><th>Mã thưởng</th><th>Ngày</th></tr></thead>
            <tbody>
                @forelse ($referrals as $referral)
                    <tr>
                        <td>{{ $referral->referrer?->name }}<div class="text-secondary">{{ $referral->referrer?->email }}</div></td>
                        <td>{{ $referral->referred?->name }}<div class="text-secondary">{{ $referral->referred?->email }}</div></td>
                        <td><span class="badge text-bg-{{ ['pending' => 'secondary', 'rewarded' => 'success', 'void' => 'danger'][$referral->status] }}">{{ \App\Models\Referral::STATUS_LABELS[$referral->status] }}</span></td>
                        <td class="font-monospace">{{ $referral->rewardVoucher?->code ?? '—' }}</td>
                        <td>{{ $referral->created_at->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-secondary">Chưa có ai đăng ký qua link giới thiệu.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $referrals->links() }}
@endsection
