@extends('layouts.app', ['portal' => 'admin'])

@php
    use App\Models\Package;
    use App\Models\PackageFeature;
    $isNew = ! $package->exists;
    $displayLines = $features->filter(fn ($f, $key) => str_starts_with($key, PackageFeature::DISPLAY_PREFIX))->pluck('label')->implode("\n");
@endphp

@section('title', ($isNew ? 'Thêm gói' : 'Sửa ' . $package->name) . ' — Quản trị TOÁN AI')
@section('page_title', $isNew ? 'Thêm gói' : 'Sửa gói')

@section('content')
    <a href="{{ route('admin.packages.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Gói học</a>

    <form method="POST" action="{{ $isNew ? route('admin.packages.store') : route('admin.packages.update', $package) }}" class="mt-2" style="max-width:860px">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <div class="card border mb-3">
            <div class="card-body row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="name">Tên gói</label>
                    <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $package->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="slug">Slug <span class="text-secondary small">(để trống = tự tạo)</span></label>
                    <input id="slug" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $package->slug) }}">
                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="tier">Hạng</label>
                    <select id="tier" name="tier" class="form-select">
                        @foreach (Package::TIER_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(old('tier', $package->tier) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="price">Giá (₫)</label>
                    <input id="price" name="price" type="number" min="0" step="1000" class="form-control @error('price') is-invalid @enderror"
                           value="{{ old('price', $package->price !== null ? (int) $package->price : '') }}" required>
                    @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="duration_days">Số ngày</label>
                    <input id="duration_days" name="duration_days" type="number" min="1" class="form-control @error('duration_days') is-invalid @enderror"
                           value="{{ old('duration_days', $package->duration_days) }}">
                    @error('duration_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="sort_order">Thứ tự</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" class="form-control" value="{{ old('sort_order', $package->sort_order ?? 0) }}">
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Mô tả ngắn</label>
                    <input id="description" name="description" class="form-control" maxlength="500" value="{{ old('description', $package->description) }}">
                </div>
                <div class="col-12 d-flex flex-wrap gap-4">
                    @foreach (['is_active' => 'Đang bán', 'is_highlighted' => 'Nổi bật trên bảng giá', 'is_default' => 'Gói mặc định (Free)'] as $field => $label)
                        <div class="form-check">
                            <input type="hidden" name="{{ $field }}" value="0">
                            <input class="form-check-input @error($field) is-invalid @enderror" type="checkbox" id="{{ $field }}" name="{{ $field }}" value="1"
                                   @checked(old($field, $package->{$field}))>
                            <label class="form-check-label" for="{{ $field }}">{{ $label }}</label>
                            @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card border mb-3">
            <div class="card-body">
                <h3 class="h6 fw-bold">Quyền lợi hệ thống kiểm tra</h3>
                <p class="small text-secondary">Giới hạn để trống = không giới hạn. Nhãn là chữ hiện trên bảng giá.</p>

                @foreach (PackageFeature::KEYS as $key => $description)
                    @php
                        $field = str_replace('.', '_', $key);
                        $f = $features->get($key);
                        $isLimit = PackageFeature::KEY_TYPES[$key] === 'limit';
                    @endphp
                    <div class="row g-2 align-items-center border-top py-2">
                        <div class="col-12 col-md-4 small">
                            <div class="fw-semibold">{{ $description }}</div>
                            <code class="text-secondary">{{ $key }}</code>
                        </div>
                        <div class="col-12 col-md-4">
                            <input name="features[{{ $field }}][label]" class="form-control form-control-sm" placeholder="Nhãn hiển thị"
                                   value="{{ old("features.$field.label", $f?->label) }}">
                        </div>
                        <div class="col-6 col-md-2">
                            @if ($isLimit)
                                <input name="features[{{ $field }}][limit]" type="number" min="0" class="form-control form-control-sm" placeholder="∞"
                                       value="{{ old("features.$field.limit", $f?->limit_value) }}">
                            @else
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="f-{{ $field }}-enabled" name="features[{{ $field }}][enabled]"
                                           @checked(old("features.$field.enabled", $f?->isEnabled()))>
                                    <label class="form-check-label small" for="f-{{ $field }}-enabled">Bật</label>
                                </div>
                            @endif
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="f-{{ $field }}-show" name="features[{{ $field }}][show]"
                                       @checked(old("features.$field.show", $f?->show_on_pricing ?? true))>
                                <label class="form-check-label small" for="f-{{ $field }}-show">Hiện</label>
                            </div>
                        </div>
                    </div>
                @endforeach

                <label class="form-label fw-semibold mt-3" for="display_lines">Dòng mô tả thêm trên bảng giá</label>
                <textarea id="display_lines" name="display_lines" rows="3" class="form-control" placeholder="Mỗi dòng một ý">{{ old('display_lines', $displayLines) }}</textarea>
            </div>
        </div>

        <button class="btn btn-primary">{{ $isNew ? 'Tạo gói' : 'Lưu thay đổi' }}</button>
    </form>
@endsection
