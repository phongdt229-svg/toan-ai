@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Kết quả: ' . $exam->title . ' — TOÁN AI')
@section('page_title', 'Kết quả')

@push('head')
    @vite('resources/js/charts.js')
@endpush

@php
    use App\Models\Question;
    use App\Support\Score;

    $percent = $attempt->percent() ?? 0;
    $pendingCount = $answers->filter(fn ($a) => $a->score === null)->count();
    $duration = $attempt->durationSeconds();

    $tone = match (true) {
        $percent >= 80 => ['success', 'bi-trophy'],
        $percent >= 50 => ['warning', 'bi-hand-thumbs-up'],
        default => ['danger', 'bi-arrow-repeat'],
    };
@endphp

@section('content')
    <a href="{{ route('student.exams.show', $exam) }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> {{ $exam->title }}
    </a>

    <div class="card border-{{ $tone[0] }} mt-2 mb-4">
        <div class="card-body text-center p-4">
            <i class="bi {{ $tone[1] }} text-{{ $tone[0] }}" style="font-size:2.75rem"></i>

            <div class="display-6 fw-bold mt-2">
                {{ Score::format($attempt->score) }}<span class="fs-4 text-secondary">/{{ Score::format($attempt->total_points) }}</span>
            </div>
            <div class="text-secondary">{{ $percent }}% · đúng {{ $attempt->correct_count }}/{{ $questions->count() }} câu</div>

            <div class="d-flex flex-wrap justify-content-center gap-2 mt-3 small">
                @if ($duration !== null)
                    <span class="badge text-bg-light border">
                        <i class="bi bi-stopwatch me-1"></i>{{ intdiv($duration, 60) }} phút {{ $duration % 60 }} giây
                    </span>
                @endif
                <span class="badge text-bg-light border">Lượt {{ $attempt->attempt_no }}</span>
                @if ($attempt->auto_submitted)
                    <span class="badge text-bg-secondary">Tự nộp khi hết giờ</span>
                @endif
            </div>

            @if ($pendingCount > 0)
                <div class="alert alert-info small mt-3 mb-0">
                    <i class="bi bi-hourglass-split me-1"></i>
                    Còn {{ $pendingCount }} câu tự luận chờ giáo viên chấm — điểm sẽ cập nhật sau.
                </div>
            @endif
        </div>
    </div>

    @if (count($breakdown) > 0)
        <div class="card border mb-4">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Kết quả theo chủ đề</h2>
                <div class="position-relative">
                    <canvas data-chart-type="topic-bars" data-chart='@json($breakdown)'
                            aria-label="Biểu đồ tỉ lệ đúng theo chủ đề" role="img"></canvas>
                </div>
            </div>
        </div>
    @endif

    <h2 class="h6 fw-bold mb-2">Xem lại bài làm</h2>

    @unless ($revealAnswers)
        <div class="alert alert-light border small">
            <i class="bi bi-eye-slash me-1"></i>
            Đáp án đúng và lời giải sẽ hiện sau khi đề đóng
            @if ($exam->available_to) ({{ $exam->available_to->format('H:i d/m/Y') }})@endif.
        </div>
    @endunless

    @foreach ($questions as $i => $question)
        @php
            $answer = $answers->get($question->id);
            $given = $answer?->value();
            $blank = $given === null || (is_array($given) ? ! collect($given)->filter(fn ($v) => filled($v))->count() : blank($given));
            $state = match (true) {
                $answer === null => ['secondary', 'Chưa trả lời'],
                $answer->score === null => ['info', 'Chờ chấm'],
                $blank => ['secondary', 'Chưa trả lời'],
                $answer->is_correct => ['success', 'Đúng'],
                (float) $answer->score > 0 => ['warning', 'Đúng một phần'],
                default => ['danger', 'Sai'],
            };
        @endphp

        <div class="card border mb-3 border-{{ $state[0] }}">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="badge text-bg-primary">Câu {{ $i + 1 }}</span>
                    <span class="badge text-bg-{{ $state[0] }}">{{ $state[1] }}</span>
                    <span class="text-secondary small ms-auto">
                        {{ Score::format($answer?->score) }}/{{ Score::format($question->pivot->points) }} điểm
                    </span>
                </div>

                <div class="mb-3" data-math>{!! $question->content !!}</div>

                @include('student.practice.partials.input', [
                    'question' => $question,
                    'answers' => [$question->id => $given],
                    'readonly' => true,
                ])

                @if ($answer?->feedback)
                    <div class="alert alert-primary small mt-3 mb-0">
                        <strong>Nhận xét của giáo viên:</strong> {{ $answer->feedback }}
                    </div>
                @endif

                @if ($revealAnswers)
                    @if ($question->usesOptions())
                        <div class="alert alert-success small mt-3 mb-0" data-math>
                            <strong>Đáp án đúng:</strong>
                            {!! $question->options->where('is_correct', true)->map(fn ($o) => $o->content)->implode(' · ') !!}
                        </div>
                    @elseif ($question->type === Question::TYPE_TRUE_FALSE)
                        <div class="alert alert-success small mt-3 mb-0">
                            <strong>Đáp án đúng:</strong> {{ ($question->correct_answer['value'] ?? false) ? 'Đúng' : 'Sai' }}
                        </div>
                    @elseif ($question->type === Question::TYPE_FILL_BLANK)
                        <div class="alert alert-success small mt-3 mb-0">
                            <strong>Đáp án đúng:</strong>
                            {{ collect($question->correct_answer['blanks'] ?? [])->map(fn ($b) => is_array($b) ? $b[0] : $b)->implode(' · ') }}
                        </div>
                    @elseif ($question->type === Question::TYPE_SHORT_ANSWER)
                        <div class="alert alert-success small mt-3 mb-0">
                            <strong>Đáp án đúng:</strong> {{ collect($question->correct_answer['accepted'] ?? [])->first() }}
                        </div>
                    @endif

                    @include('components.ai-question-actions', [
                        'question' => $question,
                        'modes' => $answer?->is_correct ? ['explain', 'similar_exercise'] : ['analyze_mistake', 'explain', 'similar_exercise'],
                        'answer' => $given,
                    ])

                    @if ($question->explanation)
                        <div class="border-start border-3 border-primary ps-3 mt-3" data-math>
                            <div class="fw-semibold small mb-1">
                                <i class="bi bi-lightbulb text-primary me-1"></i>Giải thích
                            </div>
                            {!! $question->explanation !!}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endforeach
@endsection
