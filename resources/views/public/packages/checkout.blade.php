@extends('layouts.public')

@section('title', 'Mua ' . $package->name . ' — TOÁN AI')

@section('body')
    @include('public.partials.header')

    <main class="section">
        <div class="container" style="max-width:720px">
            @include('components.flash')

            <a href="{{ route('packages.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Bảng giá</a>
            <h1 class="h3 fw-bold mt-2 mb-4">Xác nhận mua gói</h1>

            @if ($payer->isParent() && $children->isEmpty())
                <div class="card border-warning">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-person-plus fs-2 text-warning"></i>
                        <p class="mt-2 mb-3">Bạn cần liên kết với tài khoản của con trước khi mua gói.</p>
                        <a href="{{ route('parent.children.link') }}" class="btn btn-primary">Liên kết con</a>
                    </div>
                </div>
            @else
                <div class="card border mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-bold fs-5">{{ $package->name }}</div>
                                <div class="text-secondary small">{{ $package->description }}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold fs-4 text-primary">{{ $package->priceLabel() }}</div>
                                <div class="small text-secondary">{{ $package->durationLabel() }}</div>
                            </div>
                        </div>

                        <ul class="list-unstyled small d-grid gap-1 mt-3 mb-0">
                            @foreach ($package->features->where('show_on_pricing', true)->where('value', '!=', '0') as $feature)
                                <li><i class="bi bi-check-lg text-success me-1"></i>{{ $feature->label }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                @if ($payer->isParent())
                    <form method="GET" class="card border mb-3">
                        <div class="card-body">
                            <label class="form-label fw-semibold" for="con">Mua cho con</label>
                            <div class="d-flex gap-2">
                                <select name="con" id="con" class="form-select" onchange="this.form.submit()">
                                    <option value="">— Chọn con —</option>
                                    @foreach ($children as $child)
                                        <option value="{{ $child->id }}" @selected($beneficiary?->id == $child->id)>
                                            {{ $child->name }} · {{ $child->studentProfile?->grade?->name ?? 'chưa chọn lớp' }}
                                        </option>
                                    @endforeach
                                </select>
                                <noscript><button class="btn btn-outline-primary">Chọn</button></noscript>
                            </div>
                        </div>
                    </form>
                @endif

                @if ($beneficiary)
                    {{-- Mã giảm giá (§8b). Chỉ gửi chuỗi mã; số tiền do server tính lại lúc tạo đơn. --}}
                    <div class="card border mb-3">
                        <div class="card-body">
                            @if ($quote)
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <div>
                                        <span class="badge text-bg-success">{{ $quote->voucher->code }}</span>
                                        <span class="small text-secondary ms-1">
                                            giảm {{ number_format($quote->discount, 0, ',', '.') }}₫
                                            @if ($quote->voucher->description) · {{ $quote->voucher->description }} @endif
                                        </span>
                                    </div>
                                    <form method="POST" action="{{ route('packages.voucher.remove', $package) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-link text-secondary text-decoration-none p-0">Gỡ mã</button>
                                    </form>
                                </div>
                            @else
                                @if ($voucherCode)
                                    <p class="small text-warning-emphasis mb-2">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Mã <strong>{{ $voucherCode }}</strong>
                                        không dùng được cho gói này.
                                    </p>
                                @endif
                                <form method="POST" action="{{ route('packages.voucher.apply', $package) }}"
                                      class="d-flex gap-2 align-items-start">
                                    @csrf
                                    <div class="flex-grow-1">
                                        <label class="form-label small mb-1" for="code">Mã giảm giá</label>
                                        <input id="code" name="code" maxlength="32" autocomplete="off"
                                               class="form-control text-uppercase @error('code') is-invalid @enderror"
                                               value="{{ old('code') }}" placeholder="VD: TOANAI50">
                                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <button class="btn btn-outline-primary mt-4">Áp dụng</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="card border mb-3">
                        <div class="card-body small d-grid gap-2">
                            <div class="d-flex justify-content-between"><span class="text-secondary">Người dùng gói</span><strong>{{ $beneficiary->name }}</strong></div>
                            <div class="d-flex justify-content-between">
                                <span class="text-secondary">Gói hiện tại</span>
                                <span>{{ $current ? $current->package->name . ' (đến ' . $current->ends_at->format('d/m/Y') . ')' : 'Free' }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-secondary">Hiệu lực</span>
                                <span>
                                    {{ $startsAt->isFuture() ? 'từ ' . $startsAt->format('d/m/Y') . ' (nối tiếp gói cũ)' : 'ngay khi thanh toán xong' }}
                                    → {{ $startsAt->copy()->addDays($package->duration_days)->format('d/m/Y') }}
                                </span>
                            </div>
                            <hr class="my-1">
                            @if ($quote)
                                <div class="d-flex justify-content-between">
                                    <span class="text-secondary">Giá gói</span>
                                    <span>{{ $package->priceLabel() }}</span>
                                </div>
                                <div class="d-flex justify-content-between text-success">
                                    <span>Giảm giá ({{ $quote->voucher->code }})</span>
                                    <span>− {{ number_format($quote->discount, 0, ',', '.') }}₫</span>
                                </div>
                            @endif
                            <div class="d-flex justify-content-between fs-6">
                                <span>Tổng thanh toán</span>
                                <strong class="text-primary">
                                    {{ $quote ? number_format($quote->payable, 0, ',', '.') . '₫' : $package->priceLabel() }}
                                </strong>
                            </div>
                        </div>
                    </div>

                    {{-- Chỉ gửi gói + con; số tiền server lấy từ DB. --}}
                    <form method="POST" action="{{ route('packages.pay', $package) }}">
                        @csrf
                        @if ($payer->isParent())
                            <input type="hidden" name="con" value="{{ $beneficiary->id }}">
                        @endif
                        @if ($quote?->isFree())
                            <button class="btn btn-lg w-100 btn-success">
                                <i class="bi bi-gift me-1"></i>Nhận gói miễn phí
                            </button>
                        @else
                            <button class="btn btn-lg w-100 text-white" style="background:#a50064">
                                <i class="bi bi-wallet2 me-1"></i>Thanh toán bằng MoMo
                            </button>
                        @endif
                    </form>
                    <p class="small text-secondary text-center mt-2 mb-0">
                        {{ $quote?->isFree()
                            ? 'Mã giảm 100% — gói kích hoạt ngay, không cần thanh toán.'
                            : 'Gói được kích hoạt ngay khi MoMo xác nhận thanh toán.' }}
                    </p>
                @endif
            @endif
        </div>
    </main>

    @include('public.partials.footer')
@endsection
