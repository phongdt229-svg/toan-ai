@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Chính sách — Quản trị Math AI')
@section('page_title', 'Chính sách')

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3" style="max-width:820px">
        <p class="text-secondary small mb-0" style="max-width:560px">
            Các trang chính sách hiện ở chân trang và cuối mỗi trang chính sách. Đường dẫn (slug) tự sinh từ tiêu đề lúc
            tạo và <strong>không đổi được</strong> — để link đã đăng ký với Bộ Công Thương hay đã chia sẻ không bị gãy.
        </p>
        <a href="{{ route('admin.legal.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Tạo trang mới
        </a>
    </div>

    <div class="card border" style="max-width:820px">
        <div class="list-group list-group-flush">
            @foreach ($pages as $page)
                <div class="list-group-item d-flex flex-wrap align-items-center gap-2 py-3">
                    <div class="flex-grow-1" style="min-width:0">
                        <div class="fw-semibold">{{ $page->title }}</div>
                        <div class="small text-secondary text-break">
                            /{{ $page->slug }} · Cập nhật {{ $service->updatedLabel($page) }}
                            @if ($page->is_system)
                                · <span class="badge text-bg-light border">Trang hệ thống</span>
                            @endif
                            @if ($service->usesDefault($page))
                                <span class="badge text-bg-light border">Bản mặc định</span>
                            @endif
                            @unless ($page->is_active)
                                <span class="badge text-bg-secondary">Đang ẩn</span>
                            @endunless
                        </div>
                    </div>
                    @if ($page->is_active)
                        <a href="{{ $page->url() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-box-arrow-up-right"></i><span class="visually-hidden">Xem trang</span>
                        </a>
                    @endif
                    <a href="{{ route('admin.legal.edit', $page) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i>Sửa
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endsection
