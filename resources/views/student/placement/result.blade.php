@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Kết quả kiểm tra đầu vào — TOÁN AI')
@section('page_title', 'Kết quả đầu vào')

@php
    use App\Support\Score;

    $tone = ['average' => 'warning', 'good' => 'primary', 'excellent' => 'success'][$test->level_result] ?? 'secondary';
@endphp

@section('content')
    <div class="card border-{{ $tone }} mb-4">
        <div class="card-body text-center p-4">
            <div class="small text-secondary mb-1">Kiểm tra đầu vào · {{ $test->submitted_at?->format('d/m/Y') }}</div>
            <div class="display-5 fw-bold">{{ Score::format($test->score) }}<span class="fs-4 text-secondary">/10</span></div>
            <span class="badge text-bg-{{ $tone }} fs-6 mt-1">Học lực: {{ $test->levelLabel() }}</span>
            <div class="text-secondary mt-2">Đúng {{ $test->correct_count }}/{{ $test->total_questions }} câu</div>
            @if ($test->auto_submitted)
                <span class="badge text-bg-secondary mt-2">Tự nộp khi hết giờ</span>
            @endif
        </div>
    </div>

    {{-- §34 Output: nhóm kiến thức yếu · tốc độ làm bài · mức độ hiểu --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="stat-card__label"><i class="bi bi-lightbulb me-1"></i>Mức độ hiểu</div>
                <div class="stat-card__value fs-5">{{ $test->understandingLabel() }}</div>
                <div class="small text-secondary">{{ $test->understanding_percent }}% — đúng câu khó được tính nhiều hơn</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="stat-card__label"><i class="bi bi-stopwatch me-1"></i>Tốc độ làm bài</div>
                <div class="stat-card__value fs-5">{{ $test->speedLabel() ?? '—' }}</div>
                <div class="small text-secondary">
                    {{ $test->avg_seconds_per_question ? $test->avg_seconds_per_question . ' giây/câu' : 'Không đo được (bài tự nộp)' }}
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="stat-card__label"><i class="bi bi-exclamation-circle me-1"></i>Kiến thức cần củng cố</div>
                @forelse ($test->weak_topics ?? [] as $t)
                    <div class="d-flex justify-content-between small"><span>{{ $t['name'] }}</span><span class="text-danger">{{ $t['percent'] }}%</span></div>
                @empty
                    <div class="stat-card__value fs-6 text-success">Không có chủ đề yếu</div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($analysisHtml)
        <div class="card border mb-4">
            <div class="card-body d-flex gap-3">
                <i class="bi bi-robot text-primary fs-3"></i>
                <div data-math>{!! $analysisHtml !!}</div>
            </div>
        </div>
    @endif

    <a href="{{ route('student.path.show') }}" class="btn btn-primary btn-lg w-100 btn-touch mb-4">
        <i class="bi bi-signpost-split me-1"></i>Xem lộ trình học của em
    </a>

    <h2 class="h6 fw-bold mb-2">Xem lại bài làm</h2>
    @foreach ($test->questions as $i => $pq)
        @php
            $answer = $answers->get($pq->id);
            $question = $pq->toQuestion();
        @endphp
        <div class="card border mb-2 border-{{ $answer?->is_correct ? 'success' : 'danger' }}">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <span class="badge text-bg-primary">Câu {{ $i + 1 }}</span>
                    <span class="badge text-bg-{{ $answer?->is_correct ? 'success' : 'danger' }}">{{ $answer?->is_correct ? 'Đúng' : 'Chưa đúng' }}</span>
                    @if ($pq->topic)<span class="text-secondary small">{{ $pq->topic->name }}</span>@endif
                </div>
                <div class="mb-2" data-math>{!! $pq->content !!}</div>
                @include('student.practice.partials.input', [
                    'question' => $question,
                    'answers' => [$question->id => $answer?->value()],
                    'readonly' => true,
                ])
                @if ($pq->explanation)
                    <div class="border-start border-3 border-primary ps-3 mt-2 small" data-math>{!! $pq->explanation !!}</div>
                @endif
            </div>
        </div>
    @endforeach
@endsection
