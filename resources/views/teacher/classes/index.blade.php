@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Lớp học — TOÁN AI')
@section('page_title', 'Lớp học')

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <h2 class="h5 fw-bold mb-0">Lớp của tôi</h2>
        <a href="{{ route('teacher.classes.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Tạo lớp
        </a>
    </div>

    @if ($classes->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4 text-secondary">
                <i class="bi bi-people fs-2 d-block mb-2"></i>
                Chưa có lớp nào. Tạo lớp rồi đưa mã cho học sinh tự tham gia.
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach ($classes as $class)
                <div class="col-12 col-md-6 col-xl-4">
                    <a href="{{ route('teacher.classes.show', $class) }}"
                       class="card border h-100 text-decoration-none text-body {{ $class->status === 'archived' ? 'opacity-50' : '' }}">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                <div>
                                    <div class="fw-bold">{{ $class->name }}</div>
                                    <div class="text-secondary small">{{ $class->grade->name }}</div>
                                </div>
                                @if ($class->status === 'archived')
                                    <span class="badge text-bg-secondary">Lưu trữ</span>
                                @else
                                    <span class="badge text-bg-light border font-monospace">{{ $class->code }}</span>
                                @endif
                            </div>
                            <div class="d-flex gap-3 small text-secondary">
                                <span><i class="bi bi-mortarboard me-1"></i>{{ $class->active_students_count }} học sinh</span>
                                <span><i class="bi bi-send-check me-1"></i>{{ $class->assignments_count }} bài đang giao</span>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
@endsection
