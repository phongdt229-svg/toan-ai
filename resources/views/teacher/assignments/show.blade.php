@extends('layouts.app', ['portal' => 'teacher'])

@section('title', $assignment->title . ' — TOÁN AI')
@section('page_title', 'Theo dõi bài giao')

@php
    use App\Models\Assignment;
    use App\Support\Score;

    $total = $recipients->count();
    $dt = fn ($v) => $v ? $v->format('Y-m-d\TH:i') : '';
    $minutes = fn (int $s) => $s > 0 ? max(1, (int) round($s / 60)) . ' phút' : '—';
@endphp

@section('content')
    <a href="{{ route('teacher.classes.show', $assignment->schoolClass) }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> {{ $assignment->schoolClass->name }}
    </a>

    <div class="d-flex flex-wrap gap-2 align-items-start justify-content-between mt-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">{{ $assignment->title }}</h2>
            <div class="text-secondary small">
                {{ $assignment->typeLabel() }}:
                @switch ($assignment->type)
                    @case (Assignment::TYPE_EXAM) {{ $assignment->exam?->title }} @break
                    @case (Assignment::TYPE_LESSON) {{ $assignment->lesson?->title }} @break
                    @default {{ $questionCount }} câu hỏi
                @endswitch
                · giao {{ $assignment->published_at->format('H:i d/m') }}
            </div>
        </div>

        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('teacher.assignments.close', $assignment) }}">
                @csrf
                <button class="btn btn-sm {{ $assignment->isClosed() ? 'btn-outline-success' : 'btn-outline-secondary' }}">
                    {{ $assignment->isClosed() ? 'Mở lại' : 'Đóng bài' }}
                </button>
            </form>
            <form method="POST" action="{{ route('teacher.assignments.destroy', $assignment) }}"
                  onsubmit="return confirm('Xoá bài giao này? Học sinh sẽ không thấy bài nữa.')">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">Xoá</button>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['Đã làm', "{$doneCount}/{$total}", 'bi-check2-square'],
            ['Chưa làm', $total - $doneCount, 'bi-hourglass'],
            ['Nộp trễ', $lateCount, 'bi-alarm'],
            ['Điểm TB', $avgPercent !== null ? "{$avgPercent}%" : '—', 'bi-star'],
        ] as [$label, $value, $icon])
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between"><div class="stat-card__label">{{ $label }}</div><i class="bi {{ $icon }} text-primary"></i></div>
                    <div class="stat-card__value">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="table-responsive mb-4">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Học sinh</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Điểm</th>
                    <th class="text-end d-none d-sm-table-cell">Thời gian</th>
                    <th class="text-center d-none d-md-table-cell">Lượt</th>
                    <th class="d-none d-md-table-cell">Nộp lúc</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recipients as $r)
                    <tr>
                        <td>
                            <a href="{{ route('teacher.students.show', $r->student) }}" class="text-decoration-none">{{ $r->student->name }}</a>
                        </td>
                        <td>
                            @if ($r->status === 'completed')
                                <span class="badge text-bg-success">Đã làm</span>
                            @elseif ($r->status === 'submitted')
                                <span class="badge text-bg-info">Chờ chấm</span>
                            @elseif ($assignment->isOverdue())
                                <span class="badge text-bg-danger">Quá hạn</span>
                            @else
                                <span class="badge text-bg-secondary">Chưa làm</span>
                            @endif
                            @if ($r->is_late)<span class="badge text-bg-warning text-dark">Trễ</span>@endif
                        </td>
                        <td class="text-end">
                            @if ($r->percent !== null)
                                {{ $r->percent }}%
                                <div class="small text-secondary">{{ Score::format($r->score) }}/{{ Score::format($r->max_score) }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end d-none d-sm-table-cell">{{ $minutes($r->time_spent_seconds) }}</td>
                        <td class="text-center d-none d-md-table-cell">{{ $r->attempts_count ?: '—' }}</td>
                        <td class="small d-none d-md-table-cell">{{ $r->completed_at?->format('H:i d/m') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card border">
        <div class="card-body">
            <h3 class="h6 fw-bold mb-3">Sửa thông tin bài giao</h3>
            <form method="POST" action="{{ route('teacher.assignments.update', $assignment) }}" novalidate>
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="title">Tên bài</label>
                        <input type="text" id="title" name="title" value="{{ old('title', $assignment->title) }}" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="due_at">Hạn nộp</label>
                        <input type="datetime-local" id="due_at" name="due_at" value="{{ old('due_at', $dt($assignment->due_at)) }}" class="form-control">
                        <div class="form-text">Đổi hạn sẽ tính lại cờ "trễ" của các bài đã nộp.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Lời dặn</label>
                        <textarea id="description" name="description" rows="2" class="form-control">{{ old('description', $assignment->description) }}</textarea>
                    </div>
                    @if ($assignment->type === Assignment::TYPE_QUESTION_SET)
                        <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="allow_retry" name="allow_retry" value="1"
                                       @checked(old('allow_retry', $assignment->allow_retry))>
                                <label class="form-check-label" for="allow_retry">Cho phép làm lại</label>
                            </div>
                            <div class="input-group input-group-sm" style="max-width:12rem">
                                <span class="input-group-text">Tối đa</span>
                                <input type="number" name="max_attempts" min="2" max="10"
                                       value="{{ old('max_attempts', max(2, $assignment->max_attempts)) }}" class="form-control" aria-label="Số lượt">
                            </div>
                        </div>
                    @endif
                </div>
                <button class="btn btn-primary mt-3">Lưu</button>
            </form>
        </div>
    </div>
@endsection
