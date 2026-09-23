@extends('layouts.app', ['portal' => 'admin'])

@php use App\Models\Voucher; @endphp

@section('title', ($voucher->exists ? 'Sửa mã' : 'Tạo mã') . ' — Quản trị TOÁN AI')
@section('page_title', $voucher->exists ? 'Sửa mã ' . $voucher->code : 'Tạo mã giảm giá')

@section('content')
    <form method="POST" action="{{ $voucher->exists ? route('admin.vouchers.update', $voucher) : route('admin.vouchers.store') }}"
          style="max-width:760px">
        @csrf
        @if ($voucher->exists) @method('PUT') @endif

        <div class="card border mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-5">
                        <label class="form-label" for="code">Mã <span class="text-danger">*</span></label>
                        <input id="code" name="code" maxlength="32" required
                               class="form-control text-uppercase font-monospace @error('code') is-invalid @enderror"
                               value="{{ old('code', $voucher->code) }}" placeholder="TOANAI50">
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Chữ, số, gạch ngang. Người dùng gõ chữ thường vẫn nhận.</div>
                    </div>

                    <div class="col-12 col-md-7">
                        <label class="form-label" for="description">Ghi chú (người mua nhìn thấy)</label>
                        <input id="description" name="description" maxlength="191"
                               class="form-control @error('description') is-invalid @enderror"
                               value="{{ old('description', $voucher->description) }}" placeholder="Khai giảng năm học mới">
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="type">Loại giảm <span class="text-danger">*</span></label>
                        <select id="type" name="type" class="form-select @error('type') is-invalid @enderror">
                            @foreach (Voucher::TYPE_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $voucher->type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="value">Giá trị <span class="text-danger">*</span></label>
                        <input id="value" name="value" type="number" step="0.01" min="0.01" required
                               class="form-control @error('value') is-invalid @enderror"
                               value="{{ old('value', $voucher->value) }}">
                        @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">% thì 1–100 · số tiền thì nhập VND.</div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="max_discount">Giảm tối đa (chỉ cho loại %)</label>
                        <input id="max_discount" name="max_discount" type="number" step="1000" min="0"
                               class="form-control @error('max_discount') is-invalid @enderror"
                               value="{{ old('max_discount', $voucher->max_discount) }}" placeholder="100000">
                        @error('max_discount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card border mb-3">
            <div class="card-body">
                <h3 class="h6 fw-bold mb-3">Điều kiện áp dụng</h3>

                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="min_order_amount">Đơn tối thiểu</label>
                        <input id="min_order_amount" name="min_order_amount" type="number" step="1000" min="0"
                               class="form-control @error('min_order_amount') is-invalid @enderror"
                               value="{{ old('min_order_amount', $voucher->min_order_amount) }}">
                        @error('min_order_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-6 col-md-4">
                        <label class="form-label" for="starts_at">Bắt đầu</label>
                        <input id="starts_at" name="starts_at" type="datetime-local"
                               class="form-control @error('starts_at') is-invalid @enderror"
                               value="{{ old('starts_at', $voucher->starts_at?->format('Y-m-d\TH:i')) }}">
                        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-6 col-md-4">
                        <label class="form-label" for="ends_at">Kết thúc</label>
                        <input id="ends_at" name="ends_at" type="datetime-local"
                               class="form-control @error('ends_at') is-invalid @enderror"
                               value="{{ old('ends_at', $voucher->ends_at?->format('Y-m-d\TH:i')) }}">
                        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-6 col-md-4">
                        <label class="form-label" for="max_uses">Tổng lượt</label>
                        <input id="max_uses" name="max_uses" type="number" min="1"
                               class="form-control @error('max_uses') is-invalid @enderror"
                               value="{{ old('max_uses', $voucher->max_uses) }}" placeholder="không giới hạn">
                        @error('max_uses')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-6 col-md-4">
                        <label class="form-label" for="max_uses_per_user">Lượt mỗi người <span class="text-danger">*</span></label>
                        <input id="max_uses_per_user" name="max_uses_per_user" type="number" min="1" required
                               class="form-control @error('max_uses_per_user') is-invalid @enderror"
                               value="{{ old('max_uses_per_user', $voucher->max_uses_per_user ?? 1) }}">
                        @error('max_uses_per_user')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <hr>

                <label class="form-label">Gói áp dụng</label>
                <div class="form-text mb-2">Không chọn gói nào = áp dụng cho mọi gói.</div>
                <div class="d-flex flex-wrap gap-3">
                    @foreach ($packages as $package)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="packages[]" value="{{ $package->id }}"
                                   id="pkg-{{ $package->id }}"
                                   @checked(in_array($package->id, old('packages', $selected)))>
                            <label class="form-check-label" for="pkg-{{ $package->id }}">
                                {{ $package->name }} <span class="text-secondary small">{{ $package->priceLabel() }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                           @checked(old('is_active', $voucher->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Đang bật</label>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary">{{ $voucher->exists ? 'Lưu thay đổi' : 'Tạo mã' }}</button>
            <a href="{{ route('admin.vouchers.index') }}" class="btn btn-outline-secondary">Huỷ</a>
        </div>
    </form>
@endsection
