@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Gói học — Quản trị TOÁN AI')
@section('page_title', 'Gói học')

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h2 class="h5 fw-bold mb-0">Gói học & giá</h2>
        <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-sm btn-outline-secondary ms-auto">Đăng ký</a>
        <a href="{{ route('admin.packages.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Thêm gói</a>
    </div>

    <p class="small text-secondary">Giá hiển thị trên bảng giá và dùng khi thanh toán đều lấy từ đây. Đổi giá không ảnh hưởng gói đã bán.</p>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr><th>Gói</th><th>Hạng</th><th class="text-end">Giá</th><th>Thời hạn</th><th>Trạng thái</th><th class="text-end">Đang dùng</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($packages as $package)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $package->name }}</div>
                            <div class="small text-secondary">{{ $package->slug }}</div>
                        </td>
                        <td>{{ $package->tierLabel() }}</td>
                        <td class="text-end">{{ $package->priceLabel() }}</td>
                        <td>{{ $package->durationLabel() ?? '—' }}</td>
                        <td>
                            <span class="badge text-bg-{{ $package->is_active ? 'success' : 'secondary' }}">{{ $package->is_active ? 'Đang bán' : 'Tắt' }}</span>
                            @if ($package->is_default)<span class="badge text-bg-info">Mặc định</span>@endif
                            @if ($package->is_highlighted)<span class="badge text-bg-primary">Nổi bật</span>@endif
                        </td>
                        <td class="text-end">{{ $package->active_count }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.packages.edit', $package) }}" class="btn btn-sm btn-outline-primary">Sửa</a>
                            @unless ($package->is_default)
                                <form method="POST" action="{{ route('admin.packages.destroy', $package) }}" class="d-inline"
                                      onsubmit="return confirm('Xoá gói {{ $package->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Xoá</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
