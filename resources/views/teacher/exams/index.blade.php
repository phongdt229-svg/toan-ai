@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Đề kiểm tra — TOÁN AI')
@section('page_title', 'Đề kiểm tra')

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <h2 class="h5 fw-bold mb-0">Đề kiểm tra của tôi</h2>
        <a href="{{ route('teacher.exams.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Tạo đề
        </a>
    </div>

    @if ($exams->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4 text-secondary">
                <i class="bi bi-clipboard-plus fs-2 d-block mb-2"></i>
                Chưa có đề nào.
            </div>
        </div>
    @else
        <div class="d-grid gap-2">
            @foreach ($exams as $exam)
                <div class="card border">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="fw-semibold">{{ $exam->title }}</span>
                            @if ($exam->isPublished())
                                <span class="badge text-bg-success">Đã xuất bản</span>
                            @else
                                <span class="badge text-bg-secondary">Nháp</span>
                            @endif
                            @if ($exam->pending_grading_count > 0)
                                <span class="badge text-bg-warning text-dark">
                                    {{ $exam->pending_grading_count }} bài chờ chấm
                                </span>
                            @endif
                        </div>

                        <div class="text-secondary small mb-3">
                            {{ $exam->grade->name }} · {{ $exam->total_questions }} câu ·
                            {{ $exam->duration_minutes }} phút · {{ $exam->attempts_count }} lượt làm
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('teacher.exams.edit', $exam) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil me-1"></i>Sửa
                            </a>
                            <a href="{{ route('teacher.exams.attempts', $exam) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-people me-1"></i>Bài làm
                            </a>
                            <form method="POST" action="{{ route('teacher.exams.publish', $exam) }}">
                                @csrf
                                <button class="btn btn-sm {{ $exam->isPublished() ? 'btn-outline-secondary' : 'btn-success' }}">
                                    {{ $exam->isPublished() ? 'Gỡ xuất bản' : 'Xuất bản' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3">{{ $exams->links() }}</div>
    @endif
@endsection
