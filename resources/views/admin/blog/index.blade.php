@extends('layouts.app', ['portal' => 'admin'])

@push('head')
    @vite('resources/js/charts.js')
@endpush

@section('title', 'Bài viết — Quản trị TOÁN AI')
@section('page_title', 'Bài viết')

@section('content')
    <div class="row g-3 mb-3">
        @foreach ([
            ['Tổng số bài', $summary['total'], 'primary'],
            ['Đã xuất bản', $summary['published'], 'success'],
            ['Bản nháp', $summary['draft'], 'secondary'],
        ] as [$label, $value, $tone])
            <div class="col-6 col-lg-4">
                <div class="card border h-100"><div class="card-body py-2">
                    <div class="small text-secondary">{{ $label }}</div>
                    <div class="h5 fw-bold mb-0 text-{{ $tone }}">{{ number_format($value, 0, ',', '.') }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-7">
            <div class="card border h-100"><div class="card-body">
                <div class="fw-semibold mb-2">Bài xuất bản — 30 ngày gần đây</div>
                <canvas data-chart-type="blog-daily" data-chart='@json($daily)'
                        role="img" aria-label="Biểu đồ số bài xuất bản theo ngày"></canvas>
            </div></div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="card border h-100"><div class="card-body">
                <div class="fw-semibold mb-2">Theo danh mục</div>
                @if ($byCategory)
                    <canvas data-chart-type="count-bars" data-chart='@json($byCategory)'
                            role="img" aria-label="Biểu đồ số bài đã xuất bản theo danh mục"></canvas>
                @else
                    <div class="text-secondary small">Chưa có bài đã xuất bản.</div>
                @endif
            </div></div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="table-meta mb-0">{{ number_format($posts->total(), 0, ',', '.') }} bài</div>
        <a href="{{ route('admin.blog.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Viết bài mới
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle small">
            <thead>
                <tr>
                    <th>Tiêu đề</th><th>Danh mục</th><th>Tác giả</th><th>Trạng thái</th><th>Ngày</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($posts as $post)
                    <tr>
                        <td>
                            <a href="{{ route('admin.blog.edit', $post) }}" class="fw-semibold text-decoration-none">{{ $post->title }}</a>
                        </td>
                        <td>{{ $post->category->name }}</td>
                        <td>{{ $post->author?->name ?? '—' }}</td>
                        <td>
                            <span class="badge text-bg-{{ $post->isPublished() ? 'success' : 'secondary' }}">{{ $post->statusLabel() }}</span>
                        </td>
                        <td class="text-nowrap">{{ ($post->published_at ?? $post->created_at)->format('d/m/Y') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.blog.edit', $post) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                            <form method="POST" action="{{ route('admin.blog.destroy', $post) }}" class="d-inline"
                                  data-confirm="Xoá bài «{{ $post->title }}»?" data-confirm-ok="Xoá">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Xoá</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-4">Chưa có bài viết nào.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$posts" label="bài" />
@endsection
