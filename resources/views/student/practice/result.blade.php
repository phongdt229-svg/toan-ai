@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Kết quả luyện tập — TOÁN AI')
@section('page_title', 'Kết quả')

@section('content')
    @php
        $tone = match (true) {
            $percent >= 80 => ['success', 'bi-emoji-smile', 'Làm tốt lắm!'],
            $percent >= 50 => ['warning', 'bi-emoji-neutral', 'Tạm ổn, còn chỗ phải xem lại.'],
            default => ['danger', 'bi-emoji-frown', 'Chủ đề này cần luyện thêm.'],
        };
    @endphp

    <div class="card border-{{ $tone[0] }} mb-4">
        <div class="card-body text-center p-4">
            <i class="bi {{ $tone[1] }} text-{{ $tone[0] }}" style="font-size:3rem"></i>

            <div class="display-6 fw-bold mt-2">{{ $percent }}%</div>
            <p class="text-secondary mb-3">
                Đúng {{ $correct }}/{{ $questions->count() }} câu ·
                {{ rtrim(rtrim(number_format($score, 2, ',', '.'), '0'), ',') }}/{{ rtrim(rtrim(number_format($maxScore, 2, ',', '.'), '0'), ',') }} điểm
            </p>
            <p class="fw-semibold mb-3">{{ $tone[2] }}</p>

            <div class="d-grid d-sm-flex justify-content-sm-center gap-2">
                <a href="{{ route('student.practice.index') }}" class="btn btn-primary btn-touch">Luyện chủ đề khác</a>
                @if ($topic)
                    <form method="POST" action="{{ route('student.practice.start') }}">
                        @csrf
                        <input type="hidden" name="topic_id" value="{{ $topic->id }}">
                        <input type="hidden" name="limit" value="10">
                        <button class="btn btn-outline-primary w-100 btn-touch">Làm lại chủ đề này</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <h2 class="h6 fw-bold mb-2">Xem lại từng câu</h2>

    @foreach ($questions as $i => $question)
        @php
            $given = $answers[$question->id] ?? null;
            $isEssay = $question->needsManualGrading();
        @endphp

        <div class="card border mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge text-bg-primary">Câu {{ $i + 1 }}</span>
                    <span class="badge text-bg-light border">{{ $question->difficultyLabel() }}</span>

                    @if ($isEssay)
                        <span class="badge text-bg-secondary ms-auto">Chờ giáo viên chấm</span>
                    @endif
                </div>

                <div class="mb-3" data-math>{!! $question->content !!}</div>

                @include('student.practice.partials.input', [
                    'question' => $question,
                    'answers' => $answers,
                    'readonly' => true,
                ])

                @if ($question->usesOptions())
                    <div class="alert alert-success small mt-3 mb-0" data-math>
                        <strong>Đáp án đúng:</strong>
                        {!! $question->options->where('is_correct', true)->map(fn ($o) => $o->content)->implode(' · ') !!}
                    </div>
                @elseif (! $isEssay && $question->correct_answer)
                    <div class="alert alert-success small mt-3 mb-0">
                        <strong>Đáp án đúng:</strong>
                        @if ($question->type === \App\Models\Question::TYPE_TRUE_FALSE)
                            {{ ($question->correct_answer['value'] ?? false) ? 'Đúng' : 'Sai' }}
                        @elseif ($question->type === \App\Models\Question::TYPE_FILL_BLANK)
                            {{ collect($question->correct_answer['blanks'] ?? [])->map(fn ($b) => is_array($b) ? $b[0] : $b)->implode(' · ') }}
                        @else
                            {{ collect($question->correct_answer['accepted'] ?? [])->first() }}
                        @endif
                    </div>
                @endif

                @if ($question->explanation)
                    <div class="border-start border-3 border-primary ps-3 mt-3" data-math>
                        <div class="fw-semibold small mb-1">
                            <i class="bi bi-lightbulb text-primary me-1"></i>Giải thích
                        </div>
                        {!! $question->explanation !!}
                    </div>
                @endif
            </div>
        </div>
    @endforeach
@endsection
