@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Chính sách — Quản trị Math AI')
@section('page_title', 'Chính sách')

@section('content')
    <p class="text-secondary small mb-4" style="max-width:720px">
        Nội dung trang Điều khoản sử dụng và Chính sách bảo mật. Chưa sửa thì trang dùng bản mặc định viết sẵn
        (tự cập nhật email, hotline, dịch vụ bên thứ ba theo cấu hình). Đã sửa thì trang hiện đúng bản bạn lưu,
        và ngày "Cập nhật" ở đầu trang là ngày lưu.
    </p>

    <div class="d-flex flex-column gap-3" style="max-width:720px">
        @foreach ($pages as $page)
            <div class="card border">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <div class="flex-grow-1">
                        <h3 class="h6 fw-bold mb-1">{{ $page['title'] }}</h3>
                        <div class="small text-secondary">
                            @if ($page['custom'])
                                <span class="badge text-bg-primary">Đã tự soạn</span>
                            @else
                                <span class="badge text-bg-light border">Bản mặc định</span>
                            @endif
                            · Cập nhật {{ $page['updated'] }}
                        </div>
                    </div>
                    <a href="{{ route($page['route']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Xem trang
                    </a>
                    <a href="{{ route('admin.legal.edit', $page['slug']) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-pencil me-1"></i>Sửa nội dung
                    </a>
                </div>
            </div>
        @endforeach
    </div>
@endsection
