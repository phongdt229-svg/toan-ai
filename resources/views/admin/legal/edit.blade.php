@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Sửa '.$meta['title'].' — Quản trị Math AI')
@section('page_title', $meta['title'])

@push('head')
    {{-- Dùng lại bộ soạn thảo của bài học / bài viết, không viết editor mới. --}}
    @vite('resources/js/lesson-editor.js')
@endpush

@section('content')
    <a href="{{ route('admin.legal.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Chính sách
    </a>

    <div class="mt-2" style="max-width:820px">
        <div class="alert {{ $custom ? 'alert-primary' : 'alert-light border' }} small">
            @if ($custom)
                Trang đang hiện <strong>bản bạn soạn</strong>, lưu lần cuối {{ $custom->updated_at->format('d/m/Y H:i') }}.
                Bản này là văn bản cố định: đổi email, hotline, địa chỉ hay bật thêm dịch vụ thì phải sửa lại ở đây.
            @else
                Trang đang hiện <strong>bản mặc định</strong>. Ô dưới đã nạp sẵn bản đó để bạn sửa tiếp —
                bấm Lưu thì trang công khai chuyển sang bản của bạn.
            @endif
        </div>

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

        <form method="POST" action="{{ route('admin.legal.update', $slug) }}">
            @csrf
            @method('PUT')

            <div class="card border mb-3">
                <div class="card-body">
                    <label class="form-label" for="content">Nội dung <span class="text-danger">*</span></label>
                    <textarea id="content" name="content" rows="20" required data-rich-editor
                              class="form-control @error('content') is-invalid @enderror">{{ old('content', $content) }}</textarea>
                    @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">
                        Tiêu đề trang, ngày cập nhật và dòng "Xem thêm" tự thêm — không cần gõ lại.
                        Dùng H2 cho từng mục (1., 2., …) để trang dễ đọc.
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Lưu</button>
                <a href="{{ route($meta['route']) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Xem trang
                </a>
            </div>
        </form>

        @if ($custom)
            <form method="POST" action="{{ route('admin.legal.destroy', $slug) }}" class="mt-3"
                  data-confirm="Bỏ bản bạn đã soạn và dùng lại bản mặc định?" data-confirm-ok="Khôi phục">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Khôi phục bản mặc định
                </button>
            </form>
        @endif
    </div>
@endsection
