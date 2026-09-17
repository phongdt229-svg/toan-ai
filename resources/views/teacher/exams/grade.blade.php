@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Chấm bài — TOÁN AI')
@section('page_title', 'Chấm bài')

@php use App\Support\Score; @endphp

@section('content')
    <a href="{{ route('teacher.exams.attempts', $exam) }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Bài làm của {{ $exam->title }}
    </a>

    <div class="card border mt-2 mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="flex-grow-1">
                <div class="fw-bold">{{ $attempt->user->name }}</div>
                <div class="text-secondary small">
                    Lượt {{ $attempt->attempt_no }} · nộp {{ $attempt->submitted_at?->format('H:i d/m/Y') }}
                </div>
            </div>
            <div class="text-end">
                <div class="fs-4 fw-bold">
                    {{ Score::format($attempt->score) }}/{{ Score::format($attempt->total_points) }}
                </div>
                @if ($attempt->status === 'submitted')
                    <span class="badge text-bg-warning text-dark">Còn câu chờ chấm</span>
                @else
                    <span class="badge text-bg-success">Đã chấm xong</span>
                @endif
            </div>
        </div>
    </div>

    @foreach ($questions as $i => $question)
        @php
            $answer = $answers->get($question->id);
            $needsGrading = $question->needsManualGrading() && $answer;
        @endphp

        <div class="card border mb-3 {{ $needsGrading && $answer->score === null ? 'border-warning' : '' }}">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="badge text-bg-primary">Câu {{ $i + 1 }}</span>
                    <span class="badge text-bg-light border">{{ $question->typeLabel() }}</span>
                    <span class="text-secondary small ms-auto">
                        {{ Score::format($answer?->score) }}/{{ Score::format($question->pivot->points) }} điểm
                    </span>
                </div>

                <div class="mb-3" data-math>{!! $question->content !!}</div>

                @if ($question->needsManualGrading())
                    <div class="border rounded-3 p-3 bg-light mb-3" style="white-space:pre-wrap">{{ $answer?->value() ?: '(bỏ trắng)' }}</div>

                    @if ($answer)
                        <form method="POST" action="{{ route('teacher.exams.grade.answer', $answer) }}" class="row g-2">
                            @csrf
                            <div class="col-5 col-sm-3">
                                <label class="form-label small mb-0" for="score-{{ $answer->id }}">
                                    Điểm (tối đa {{ Score::format($answer->max_score) }})
                                </label>
                                <input type="number" step="0.25" min="0" max="{{ $answer->max_score }}"
                                       id="score-{{ $answer->id }}" name="score"
                                       value="{{ old('score', $answer->score) }}"
                                       class="form-control @error("score.{$answer->id}") is-invalid @enderror" required>
                                @error("score.{$answer->id}")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-sm">
                                <label class="form-label small mb-0" for="feedback-{{ $answer->id }}">Nhận xét</label>
                                <input type="text" id="feedback-{{ $answer->id }}" name="feedback" maxlength="2000"
                                       value="{{ old('feedback', $answer->feedback) }}" class="form-control">
                            </div>
                            <div class="col-12 col-sm-auto d-flex align-items-end">
                                <button class="btn btn-primary w-100">
                                    {{ $answer->graded_at ? 'Chấm lại' : 'Lưu điểm' }}
                                </button>
                            </div>
                        </form>
                    @endif
                @else
                    @include('student.practice.partials.input', [
                        'question' => $question,
                        'answers' => [$question->id => $answer?->value()],
                        'readonly' => true,
                    ])
                    <div class="small mt-2">
                        @if ($answer?->is_correct)
                            <span class="text-success"><i class="bi bi-check-circle me-1"></i>Tự chấm: đúng</span>
                        @else
                            <span class="text-danger"><i class="bi bi-x-circle me-1"></i>Tự chấm: chưa đúng</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endforeach
@endsection
