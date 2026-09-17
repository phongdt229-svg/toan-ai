@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Kết quả: ' . $assignment->title . ' — TOÁN AI')
@section('page_title', 'Kết quả')

@php
    use App\Models\Question;
    use App\Support\Score;

    $percent = (float) $submission->max_score > 0
        ? (int) round((float) $submission->score / (float) $submission->max_score * 100)
        : 0;
    $answers = $submission->answers ?? [];
@endphp

@section('content')
    <a href="{{ route('student.assignments.show', $assignment) }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> {{ $assignment->title }}
    </a>

    <div class="card border mt-2 mb-4">
        <div class="card-body text-center p-4">
            <div class="display-6 fw-bold">{{ $percent }}%</div>
            <div class="text-secondary">
                {{ Score::format($submission->score) }}/{{ Score::format($submission->max_score) }} điểm ·
                đúng {{ $submission->correct_count }}/{{ $questions->count() }} câu · lượt {{ $submission->attempt_no }}
            </div>
            @if ($submission->is_late)
                <span class="badge text-bg-warning text-dark mt-2">Nộp trễ hạn</span>
            @endif
            @if ($record && $record->percent !== null && $record->percent > $percent)
                <div class="small text-secondary mt-2">Điểm được tính: lượt tốt nhất {{ $record->percent }}%</div>
            @endif
        </div>
    </div>

    @foreach ($questions as $i => $question)
        @php
            $row = $answers[$question->id] ?? null;
            $ok = $row['is_correct'] ?? false;
        @endphp
        <div class="card border mb-3 border-{{ $ok ? 'success' : 'danger' }}">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge text-bg-primary">Câu {{ $i + 1 }}</span>
                    <span class="badge text-bg-{{ $ok ? 'success' : 'danger' }}">{{ $ok ? 'Đúng' : 'Chưa đúng' }}</span>
                    <span class="text-secondary small ms-auto">
                        {{ Score::format($row['score'] ?? 0) }}/{{ Score::format($question->pivot->points) }} điểm
                    </span>
                </div>

                <div class="mb-3" data-math>{!! $question->content !!}</div>

                @include('student.practice.partials.input', [
                    'question' => $question,
                    'answers' => [$question->id => $row['value'] ?? null],
                    'readonly' => true,
                ])

                @if ($question->explanation)
                    <div class="border-start border-3 border-primary ps-3 mt-3" data-math>
                        <div class="fw-semibold small mb-1"><i class="bi bi-lightbulb text-primary me-1"></i>Giải thích</div>
                        {!! $question->explanation !!}
                    </div>
                @endif
            </div>
        </div>
    @endforeach
@endsection
