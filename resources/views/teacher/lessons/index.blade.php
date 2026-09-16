@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Bài học — TOÁN AI')
@section('page_title', 'Bài học')

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <h2 class="h5 fw-bold mb-0">Bài học của tôi</h2>
        <a href="{{ route('teacher.lessons.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Tạo bài học
        </a>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-12 col-sm">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control"
                   placeholder="Tìm theo tiêu đề">
        </div>
        <div class="col-8 col-sm-auto">
            <select name="status" class="form-select">
                <option value="">Mọi trạng thái</option>
                <option value="draft" @selected(request('status') === 'draft')>Nháp</option>
                <option value="published" @selected(request('status') === 'published')>Đã xuất bản</option>
            </select>
        </div>
        <div class="col-4 col-sm-auto">
            <button class="btn btn-outline-secondary w-100">Lọc</button>
        </div>
    </form>

    @if ($lessons->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4 text-secondary">
                <i class="bi bi-journal-plus fs-2 d-block mb-2"></i>
                Chưa có bài học nào. Bấm "Tạo bài học" để bắt đầu.
            </div>
        </div>
    @else
        <div class="d-grid gap-2">
            @foreach ($lessons as $lesson)
                <div class="card border">
                    <div class="card-body d-flex flex-wrap align-items-center gap-3">
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-semibold">{{ $lesson->title }}</span>

                                @if ($lesson->isPublished())
                                    <span class="badge text-bg-success">Đã xuất bản</span>
                                @else
                                    <span class="badge text-bg-secondary">Nháp</span>
                                @endif

                                @if ($lesson->access_level !== 'free')
                                    <span class="badge text-bg-warning text-dark">
                                        {{ strtoupper($lesson->access_level) }}
                                    </span>
                                @endif
                            </div>

                            <div class="text-secondary small">
                                {{ $lesson->topic->chapter->subject->grade->name }}
                                · {{ $lesson->topic->chapter->name }}
                                · {{ $lesson->topic->name }}
                            </div>
                            <div class="text-secondary small">
                                Cập nhật {{ $lesson->updated_at->diffForHumans() }}
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="{{ route('teacher.lessons.edit', $lesson) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil me-1"></i>Sửa
                            </a>

                            <form method="POST" action="{{ route('teacher.lessons.publish', $lesson) }}">
                                @csrf
                                <button class="btn btn-sm {{ $lesson->isPublished() ? 'btn-outline-secondary' : 'btn-success' }}">
                                    {{ $lesson->isPublished() ? 'Gỡ xuất bản' : 'Xuất bản' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3">{{ $lessons->links() }}</div>
    @endif
@endsection
