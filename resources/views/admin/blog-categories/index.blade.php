@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Danh mục bài viết — Quản trị TOÁN AI')
@section('page_title', 'Danh mục bài viết')

@section('content')
    <div class="card border mb-3" style="max-width:520px">
        <div class="card-body">
            <h3 class="h6 fw-bold mb-3">Thêm danh mục</h3>
            <form method="POST" action="{{ route('admin.blog-categories.store') }}" class="d-flex gap-2">
                @csrf
                <input name="name" maxlength="100" required placeholder="Ví dụ: Khuyến mãi"
                       class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
                <button class="btn btn-primary flex-shrink-0">Thêm</button>
            </form>
            @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="table-responsive" style="max-width:640px">
        <table class="table table-sm align-middle small">
            <thead>
                <tr><th>Tên</th><th class="text-end">Số bài</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>
                            <form method="POST" action="{{ route('admin.blog-categories.update', $category) }}" class="d-flex gap-2">
                                @csrf
                                @method('PUT')
                                <input name="name" maxlength="100" required value="{{ $category->name }}"
                                       class="form-control form-control-sm">
                                <button class="btn btn-sm btn-outline-secondary flex-shrink-0">Lưu</button>
                            </form>
                        </td>
                        <td class="text-end">{{ $category->posts_count }}</td>
                        <td class="text-end">
                            @if ($category->posts_count === 0)
                                <form method="POST" action="{{ route('admin.blog-categories.destroy', $category) }}"
                                      data-confirm="Xoá danh mục «{{ $category->name }}»?" data-confirm-ok="Xoá">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Xoá</button>
                                </form>
                            @else
                                <span class="text-secondary" title="Còn bài viết — chuyển bài sang danh mục khác trước khi xoá">
                                    <i class="bi bi-lock"></i>
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-secondary py-4">Chưa có danh mục nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
