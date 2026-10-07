@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'SEO — Quản trị TOÁN AI')
@section('page_title', 'SEO')

@section('content')
    <p class="text-secondary small mb-4">
        Tiêu đề, mô tả và từ khoá hiện trên kết quả tìm kiếm Google cho các trang tĩnh công khai.
        Để trống một ô = dùng lại giá trị gốc viết sẵn trong code, không phải là xoá mất.
        Bài viết và bài hướng dẫn có tiêu đề/mô tả riêng theo từng bài, không sửa ở đây.
    </p>

    <div class="d-flex flex-column gap-3" style="max-width:720px">
        @foreach ($pages as $page)
            <div class="card border">
                <div class="card-body">
                    <h3 class="h6 fw-bold mb-3">{{ $page['label'] }}</h3>
                    <form method="POST" action="{{ route('admin.seo.update') }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="route_name" value="{{ $page['route_name'] }}">

                        <div class="mb-2">
                            <label class="form-label small text-secondary mb-1">Tiêu đề (title)</label>
                            <input type="text" name="title" maxlength="70"
                                   class="form-control form-control-sm @error('title') is-invalid @enderror"
                                   placeholder="Để trống — dùng tiêu đề gốc trong code"
                                   value="{{ old('title', $page['title']) }}">
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-2">
                            <label class="form-label small text-secondary mb-1">Mô tả (meta description)</label>
                            <textarea name="meta_description" maxlength="300" rows="2"
                                      class="form-control form-control-sm @error('meta_description') is-invalid @enderror"
                                      placeholder="Để trống — dùng mô tả gốc trong code">{{ old('meta_description', $page['meta_description']) }}</textarea>
                            @error('meta_description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-2">
                            <label class="form-label small text-secondary mb-1">Từ khoá (meta keywords)</label>
                            <input type="text" name="meta_keywords" maxlength="255"
                                   class="form-control form-control-sm @error('meta_keywords') is-invalid @enderror"
                                   placeholder="Cách nhau bằng dấu phẩy — vd: học toán online, toán lớp 6"
                                   value="{{ old('meta_keywords', $page['meta_keywords']) }}">
                            @error('meta_keywords')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-end mt-2">
                            <button class="btn btn-sm btn-outline-secondary">Lưu</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endsection
