@extends('layouts.app', ['portal' => 'student'])

@section('title', $assignment->title . ' — TOÁN AI')
@section('page_title', 'Bài được giao')

@php
    use App\Models\Assignment;
    use App\Support\Score;
@endphp

@section('content')
    <a href="{{ route('student.assignments.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Bài được giao
    </a>

    <div class="card border mt-2 mb-3">
        <div class="card-body">
            <span class="badge text-bg-primary mb-2">{{ $assignment->typeLabel() }}</span>
            <h1 class="h5 fw-bold mb-1">{{ $assignment->title }}</h1>
            <div class="text-secondary small mb-2">
                {{ $assignment->schoolClass?->name }} · GV {{ $assignment->teacher?->name }}
            </div>

            @if ($assignment->description)
                <div class="mb-3" data-math>{!! $assignment->description !!}</div>
            @endif

            <div class="d-flex flex-wrap gap-2">
                @if ($assignment->due_at)
                    <span class="badge {{ $assignment->isOverdue() ? 'text-bg-danger' : 'text-bg-light border' }}">
                        <i class="bi bi-calendar-event me-1"></i>Hạn nộp {{ $assignment->due_at->format('H:i d/m/Y') }}
                    </span>
                @endif
                @if ($assignment->type === Assignment::TYPE_QUESTION_SET)
                    <span class="badge text-bg-light border">
                        Lượt đã dùng {{ $record->attempts_count }}/{{ $assignment->effectiveMaxAttempts() }}
                    </span>
                @endif
                @if ($record->percent !== null)
                    <span class="badge text-bg-success">Điểm tốt nhất {{ $record->percent }}%</span>
                @endif
                @if ($record->is_late)
                    <span class="badge text-bg-warning text-dark">Nộp trễ</span>
                @endif
            </div>
        </div>
    </div>

    @switch ($assignment->type)
        @case (Assignment::TYPE_EXAM)
            <div class="card border">
                <div class="card-body">
                    <p class="mb-3">
                        Làm đề <strong>{{ $assignment->exam?->title }}</strong>.
                        Chỉ các lượt làm <strong>sau khi bài được giao</strong> mới được tính.
                    </p>
                    @if ($assignment->exam && ! $assignment->exam->trashed())
                        <a href="{{ route('student.exams.show', $assignment->exam) }}" class="btn btn-primary btn-touch">
                            <i class="bi bi-clipboard-check me-1"></i>Đến đề kiểm tra
                        </a>
                    @else
                        <div class="alert alert-secondary mb-0">Đề này không còn khả dụng.</div>
                    @endif
                </div>
            </div>
            @break

        @case (Assignment::TYPE_LESSON)
            <div class="card border">
                <div class="card-body">
                    @if ($record->isDone())
                        <p class="text-success mb-3"><i class="bi bi-check-circle me-1"></i>Bạn đã hoàn thành bài học này.</p>
                    @else
                        <p class="mb-3">Học xong bài <strong>{{ $assignment->lesson?->title }}</strong> và bấm "Hoàn thành bài học".</p>
                    @endif
                    @if ($assignment->lesson && $assignment->lesson->isPublished())
                        <a href="{{ route('student.lesson.show', $assignment->lesson) }}" class="btn btn-primary btn-touch">
                            <i class="bi bi-journal-text me-1"></i>Mở bài học
                        </a>
                    @endif
                </div>
            </div>
            @break

        @case (Assignment::TYPE_QUESTION_SET)
            @if ($latestSubmission)
                <a href="{{ route('student.assignments.result', [$assignment, $latestSubmission]) }}"
                   class="btn btn-outline-primary w-100 mb-3">
                    Xem kết quả lượt {{ $latestSubmission->attempt_no }}
                    ({{ Score::format($latestSubmission->score) }}/{{ Score::format($latestSubmission->max_score) }})
                </a>
            @endif

            @if ($blockReason)
                <div class="alert alert-secondary">{{ $blockReason }}</div>
            @else
                @if ($assignment->isOverdue())
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle me-1"></i>Đã quá hạn — bài nộp bây giờ sẽ bị đánh dấu trễ.
                    </div>
                @endif

                <form method="POST" action="{{ route('student.assignments.submit', $assignment) }}" id="assignment-form"
                      onsubmit="return confirm('Nộp bài?')">
                    @csrf

                    @foreach ($questions as $i => $question)
                        <div class="card border mb-3" data-question-id="{{ $question->id }}">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge text-bg-primary">Câu {{ $i + 1 }}</span>
                                    <span class="text-secondary small ms-auto">{{ Score::format($question->pivot->points) }} điểm</span>
                                </div>
                                <div class="mb-3" data-math>{!! $question->content !!}</div>

                                @include('student.practice.partials.input', ['question' => $question, 'answers' => []])
                                @include('components.ai-question-actions', ['question' => $question, 'modes' => ['hint']])

                                <input type="hidden" name="time_spent[{{ $question->id }}]" value="0"
                                       data-time-for="{{ $question->id }}">
                            </div>
                        </div>
                    @endforeach

                    <button type="submit" class="btn btn-primary btn-lg w-100 btn-touch">
                        <i class="bi bi-send me-1"></i>Nộp bài
                    </button>
                </form>
            @endif
            @break
    @endswitch
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('assignment-form');
    if (!form) return;

    const firstTouch = new Map();
    form.querySelectorAll('[data-question-id]').forEach((card) => {
        const id = card.dataset.questionId;
        const mark = () => { if (!firstTouch.has(id)) firstTouch.set(id, Date.now()); };
        card.addEventListener('input', mark);
        card.addEventListener('change', mark);
    });

    form.addEventListener('submit', () => {
        firstTouch.forEach((start, id) => {
            const field = form.querySelector(`[data-time-for="${id}"]`);
            if (field) field.value = Math.min(1800, Math.round((Date.now() - start) / 1000));
        });
    });
})();
</script>
@endpush
