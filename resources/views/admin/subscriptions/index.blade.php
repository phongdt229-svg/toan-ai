@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Đăng ký gói — Quản trị TOÁN AI')
@section('page_title', 'Đăng ký gói')

@section('content')
    <div class="row g-3 mb-3">
        @foreach ([['Đang hiệu lực', $stats['effective'], 'success'], ['Hết hạn trong 7 ngày', $stats['expiring'], 'warning'], ['Chờ thanh toán', $stats['pending'], 'secondary']] as [$label, $value, $tone])
            <div class="col-4">
                <div class="card border h-100"><div class="card-body py-2">
                    <div class="small text-secondary">{{ $label }}</div>
                    <div class="h4 fw-bold mb-0 text-{{ $tone }}">{{ $value }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card border mb-3">
        <div class="card-body">
            <h2 class="h6 fw-bold">Cấp gói thủ công</h2>
            <form method="POST" action="{{ route('admin.subscriptions.grant') }}" class="row g-2 align-items-start">
                @csrf
                <div class="col-12 col-md-5">
                    <input name="email" type="email" class="form-control @error('email') is-invalid @enderror" placeholder="Email học sinh" value="{{ old('email') }}" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-7 col-md-4">
                    <select name="package_id" class="form-select" required>
                        @foreach ($packages as $package)
                            <option value="{{ $package->id }}" @selected(old('package_id') == $package->id)>{{ $package->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-3 col-md-2">
                    <input name="days" type="number" min="1" max="3650" class="form-control" value="{{ old('days', 30) }}" title="Số ngày" required>
                </div>
                <div class="col-2 col-md-1"><button class="btn btn-primary w-100">Cấp</button></div>
            </form>
            <div class="small text-secondary mt-2">Dùng cho khuyến mãi hoặc bù sự cố thanh toán. Mọi lần cấp đều ghi audit log.</div>
        </div>
    </div>

    <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
        <input name="q" class="form-control" style="max-width:260px" placeholder="Tên hoặc email" value="{{ $search }}">
        <select name="status" class="form-select" style="max-width:200px">
            <option value="">Mọi trạng thái</option>
            <option value="effective" @selected($status === 'effective')>Đang hiệu lực</option>
            @foreach (\App\Models\Subscription::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-outline-primary">Lọc</button>
    </form>

    <div class="table-responsive">
        <table class="table table-sm align-middle small">
            <thead>
                <tr><th>#</th><th>Học sinh</th><th>Gói</th><th>Trạng thái</th><th>Thời hạn</th><th class="text-end">Giá</th><th>Nguồn</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($subscriptions as $sub)
                    <tr>
                        <td>{{ $sub->id }}</td>
                        <td>
                            <div>{{ $sub->user->name }}</div>
                            <div class="text-secondary">{{ $sub->user->email }}</div>
                        </td>
                        <td>{{ $sub->package->name }}</td>
                        <td>
                            <span class="badge text-bg-{{ $sub->isEffective() ? 'success' : 'light border' }}">{{ $sub->statusLabel() }}</span>
                            @if ($sub->cancel_reason)<div class="text-secondary">{{ $sub->cancel_reason }}</div>@endif
                        </td>
                        <td class="text-nowrap">{{ $sub->starts_at ? $sub->starts_at->format('d/m/Y') . ' → ' . $sub->ends_at->format('d/m/Y') : '—' }}</td>
                        <td class="text-end">{{ number_format((float) $sub->price_paid, 0, ',', '.') }}₫</td>
                        <td>{{ $sub->source === 'manual' ? 'Cấp tay' : 'Thanh toán' }}@if ($sub->purchaser && $sub->purchased_by !== $sub->user_id)<div class="text-secondary">bởi {{ $sub->purchaser->name }}</div>@endif</td>
                        <td class="text-end">
                            @if (in_array($sub->status, ['active', 'pending'], true))
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#cancel-{{ $sub->id }}">Huỷ</button>
                            @endif
                        </td>
                    </tr>
                    @if (in_array($sub->status, ['active', 'pending'], true))
                        <tr class="collapse" id="cancel-{{ $sub->id }}">
                            <td colspan="8">
                                <form method="POST" action="{{ route('admin.subscriptions.cancel', $sub) }}" class="d-flex gap-2">
                                    @csrf
                                    <input name="reason" class="form-control form-control-sm" maxlength="191" placeholder="Lý do huỷ (bắt buộc)" required>
                                    <button class="btn btn-sm btn-danger text-nowrap">Xác nhận huỷ</button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-4">Chưa có đăng ký nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $subscriptions->links() }}
@endsection
