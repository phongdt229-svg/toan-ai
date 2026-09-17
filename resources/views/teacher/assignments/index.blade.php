@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Giao bài — TOÁN AI')
@section('page_title', 'Giao bài')

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <h2 class="h5 fw-bold mb-0">Bài đã giao</h2>
        <a href="{{ route('teacher.assignments.create') }}" class="btn btn-primary">
            <i class="bi bi-send-plus me-1"></i>Giao bài mới
        </a>
    </div>

    @if ($assignments->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4 text-secondary">Chưa giao bài nào.</div>
        </div>
    @else
        <div class="d-grid gap-2">
            @foreach ($assignments as $assignment)
                @php
                    $pct = $assignment->recipients_count > 0
                        ? (int) round($assignment->done_count / $assignment->recipients_count * 100) : 0;
                @endphp
                <a href="{{ route('teacher.assignments.show', $assignment) }}" class="card border text-decoration-none text-body">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="fw-semibold">{{ $assignment->title }}</span>
                            <span class="badge text-bg-light border">{{ $assignment->typeLabel() }}</span>
                            @if ($assignment->isClosed())
                                <span class="badge text-bg-secondary">Đã đóng</span>
                            @elseif ($assignment->isOverdue())
                                <span class="badge text-bg-warning text-dark">Quá hạn</span>
                            @endif
                        </div>
                        <div class="text-secondary small mb-2">
                            {{ $assignment->schoolClass?->name }}
                            @if ($assignment->due_at) · hạn {{ $assignment->due_at->format('H:i d/m/Y') }} @endif
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:6px" role="progressbar"
                                 aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width:{{ $pct }}%"></div>
                            </div>
                            <span class="small text-secondary">{{ $assignment->done_count }}/{{ $assignment->recipients_count }}</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-3">{{ $assignments->links() }}</div>
    @endif
@endsection
