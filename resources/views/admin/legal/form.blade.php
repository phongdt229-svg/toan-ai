@extends('layouts.app', ['portal' => 'admin'])

@section('title', ($page->exists ? 'Sửa '.$page->title : 'Tạo trang chính sách').' — Quản trị Math AI')
@section('page_title', $page->exists ? $page->title : 'Tạo trang chính sách')

@push('head')
    {{-- Dùng lại bộ soạn thảo của bài học / bài viết, không viết editor mới. --}}
    @vite('resources/js/lesson-editor.js')
@endpush

@section('content')
    <a href="{{ route('admin.legal.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Chính sách
    </a>

    <div class="mt-2" style="max-width:820px">
        @if ($page->exists && $usesDefault)
            <div class="alert alert-light border small">
                Trang đang hiện <strong>bản mặc định</strong> (tự cập nhật email, hotline, dịch vụ theo cấu hình). Ô nội dung
                đã nạp sẵn bản đó — bấm Lưu thì trang chuyển sang bản bạn soạn, là văn bản cố định.
            </div>
        @endif

        @if ($services !== [])
            {{-- Bản tự soạn không tự thêm/bớt mục bên thứ ba như bản mặc định — nhắc để không khai thiếu. --}}
            <div class="alert alert-warning small">
                <strong>Dịch vụ bên thứ ba đang bật</strong> — Chính sách bảo mật phải khai đủ (mục chia sẻ với bên thứ ba):
                <ul class="mb-0 mt-1">
                    @foreach ($services as $service)
                        <li>{{ $service }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $page->exists ? route('admin.legal.update', $page) : route('admin.legal.store') }}">
            @csrf
            @if ($page->exists) @method('PUT') @endif

            <div class="card border mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label class="form-label" for="title">Tiêu đề <span class="text-danger">*</span></label>
                            <input id="title" name="title" maxlength="191" required
                                   class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title', $page->title) }}">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label" for="sort_order">Thứ tự</label>
                            <input id="sort_order" name="sort_order" type="number" min="0" max="999"
                                   class="form-control @error('sort_order') is-invalid @enderror"
                                   value="{{ old('sort_order', $page->sort_order) }}">
                            @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Slug chỉ để xem: không có ô nhập, server cũng không nhận trường này. --}}
                        <div class="col-12">
                            <label class="form-label" for="slug-view">Đường dẫn</label>
                            @if ($page->exists)
                                <input id="slug-view" class="form-control bg-light" value="{{ $page->url() }}" readonly>
                                <div class="form-text"><i class="bi bi-lock me-1"></i>Cố định, không đổi được — kể cả khi sửa tiêu đề.</div>
                            @else
                                <input id="slug-view" class="form-control bg-light" value="Tự sinh từ tiêu đề khi tạo (vd: chinh-sach-gia)" readonly>
                                <div class="form-text"><i class="bi bi-lock me-1"></i>Sau khi tạo sẽ không đổi được — đặt tiêu đề cho chuẩn trước khi bấm Tạo.</div>
                            @endif
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="meta_description">Mô tả (hiện trên Google)</label>
                            <textarea id="meta_description" name="meta_description" rows="2" maxlength="300"
                                      class="form-control @error('meta_description') is-invalid @enderror">{{ old('meta_description', $page->meta_description) }}</textarea>
                            @error('meta_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="content">Nội dung @unless ($page->is_system)<span class="text-danger">*</span>@endunless</label>
                            <textarea id="content" name="content" rows="20" @unless ($page->is_system) required @endunless data-rich-editor
                                      class="form-control @error('content') is-invalid @enderror">{{ old('content', $content) }}</textarea>
                            @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                Tiêu đề, ngày cập nhật và danh sách "Các chính sách khác" tự thêm — không cần gõ lại.
                                Dùng H2 cho từng mục (1., 2., …) để trang dễ đọc.
                            </div>
                        </div>

                        <div class="col-12">
                            @if ($page->is_system)
                                {{-- Đăng ký, cookie, footer trỏ tới trang này nên luôn hiện. --}}
                                <input type="hidden" name="is_active" value="1">
                                <div class="small text-secondary"><i class="bi bi-shield-lock me-1"></i>Trang hệ thống: luôn hiển thị, không xoá được.</div>
                            @else
                                <input type="hidden" name="is_active" value="0">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                                           @checked(old('is_active', $page->is_active))>
                                    <label class="form-check-label" for="is_active">Hiển thị trên web (chân trang và danh sách chính sách)</label>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>{{ $page->exists ? 'Lưu' : 'Tạo trang' }}</button>
                @if ($page->exists && $page->is_active)
                    <a href="{{ $page->url() }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Xem trang
                    </a>
                @endif
            </div>
        </form>

        @if ($page->exists && $page->is_system && ! $usesDefault)
            <form method="POST" action="{{ route('admin.legal.reset', $page) }}" class="mt-3"
                  data-confirm="Bỏ bản bạn đã soạn và dùng lại bản mặc định?" data-confirm-ok="Khôi phục">
                @csrf
                @method('PUT')
                <button class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Khôi phục bản mặc định
                </button>
            </form>
        @endif

        @if ($page->exists && ! $page->is_system)
            <form method="POST" action="{{ route('admin.legal.destroy', $page) }}" class="mt-3"
                  data-confirm="Xoá trang «{{ $page->title }}»? Link /{{ $page->slug }} sẽ báo không tìm thấy." data-confirm-ok="Xoá">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash me-1"></i>Xoá trang
                </button>
            </form>
        @endif
    </div>
@endsection
