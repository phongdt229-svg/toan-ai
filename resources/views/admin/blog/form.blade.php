@extends('layouts.app', ['portal' => 'admin'])

@php use App\Models\BlogPost; @endphp

@section('title', ($post->exists ? 'Sửa bài' : 'Viết bài mới') . ' — Quản trị TOÁN AI')
@section('page_title', $post->exists ? 'Sửa bài viết' : 'Viết bài mới')

@push('head')
    {{-- Trình soạn thảo trực quan (TipTap) — dùng lại đúng bộ soạn bài học, không viết editor mới. --}}
    @vite('resources/js/lesson-editor.js')
@endpush

@section('content')
    <a href="{{ route('admin.blog.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Bài viết
    </a>

    <form method="POST"
          action="{{ $post->exists ? route('admin.blog.update', $post) : route('admin.blog.store') }}"
          enctype="multipart/form-data" class="mt-2" style="max-width:820px">
        @csrf
        @if ($post->exists) @method('PUT') @endif

        <div class="card border mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-8">
                        <label class="form-label" for="title">Tiêu đề <span class="text-danger">*</span></label>
                        <input id="title" name="title" maxlength="191" required
                               class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title', $post->title) }}">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="blog_category_id">Danh mục <span class="text-danger">*</span></label>
                        <select id="blog_category_id" name="blog_category_id"
                                class="form-select @error('blog_category_id') is-invalid @enderror">
                            <option value="">— Chọn —</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('blog_category_id', $post->blog_category_id) === $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('blog_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if ($categories->isEmpty())
                            <div class="form-text text-warning">
                                Chưa có danh mục nào — <a href="{{ route('admin.blog-categories.index') }}">tạo danh mục trước</a>.
                            </div>
                        @endif
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="excerpt">Tóm tắt</label>
                        <textarea id="excerpt" name="excerpt" rows="2" maxlength="300"
                                  class="form-control @error('excerpt') is-invalid @enderror"
                                  placeholder="Hiện ở thẻ danh sách và mô tả khi chia sẻ link">{{ old('excerpt', $post->excerpt) }}</textarea>
                        @error('excerpt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="cover">Ảnh bìa</label>
                        @if ($post->coverUrl())
                            <div class="mb-2">
                                <img src="{{ $post->coverUrl() }}" alt="" class="rounded border" style="max-height:140px">
                            </div>
                        @endif
                        <input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp"
                               class="form-control @error('cover') is-invalid @enderror">
                        @error('cover')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">JPG, PNG hoặc WebP, tối đa 2 MB. Để trống nếu không đổi ảnh.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="content">Nội dung <span class="text-danger">*</span></label>
                        <textarea id="content" name="content" rows="10" required data-rich-editor
                                  class="form-control @error('content') is-invalid @enderror">{{ old('content', $post->content) }}</textarea>
                        @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="status">Trạng thái</label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                            @foreach (BlogPost::STATUS_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $post->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary">{{ $post->exists ? 'Lưu thay đổi' : 'Tạo bài' }}</button>
            @if ($post->exists && $post->isPublished())
                <a href="{{ route('blog.show', $post) }}" target="_blank" class="btn btn-outline-secondary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Xem
                </a>
            @endif
            <a href="{{ route('admin.blog.index') }}" class="btn btn-outline-secondary">Huỷ</a>
        </div>
    </form>
@endsection
