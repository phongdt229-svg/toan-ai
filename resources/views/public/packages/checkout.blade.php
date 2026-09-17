@extends('layouts.base')

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
                                        <option value="{{ $child->id }}" @selected($beneficiary?->id === $child->id)>
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
                            <div class="d-flex justify-content-between fs-6"><span>Tổng thanh toán</span><strong class="text-primary">{{ $package->priceLabel() }}</strong></div>
                        </div>
                    </div>

                    {{-- Phase 9 nối nút này với MoMo. --}}
                    <button class="btn btn-secondary btn-lg w-100" disabled>Thanh toán MoMo — sắp mở</button>
                @endif
            @endif
        </div>
    </main>

    @include('public.partials.footer')
@endsection
