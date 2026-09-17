@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Đang luyện tập — TOÁN AI')
@section('page_title', 'Luyện tập')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="h5 fw-bold mb-0">{{ $topic?->name }}</h2>
            <div class="text-secondary small">{{ $questions->count() }} câu</div>
        </div>
        <span class="badge text-bg-light border" id="answered-count">0/{{ $questions->count() }}</span>
    </div>

    <form method="POST" action="{{ route('student.practice.submit') }}" id="practice-form"
          onsubmit="return confirm('Nộp bài luyện tập?')">
        @csrf

        @foreach ($questions as $i => $question)
            <div class="card border mb-3" data-question-id="{{ $question->id }}">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge text-bg-primary">Câu {{ $i + 1 }}</span>
                        <span class="badge text-bg-light border">{{ $question->difficultyLabel() }}</span>
                        <span class="text-secondary small ms-auto">{{ $question->points }} điểm</span>
                    </div>

                    <div class="mb-3" data-math>{!! $question->content !!}</div>

                    @include('student.practice.partials.input', ['question' => $question, 'answers' => []])
                    @include('components.ai-question-actions', [
                        'question' => $question,
                        'modes' => ['hint', 'check_answer'],
                        'readFrom' => '[data-question-id="' . $question->id . '"]',
                    ])

                    <input type="hidden" name="time_spent[{{ $question->id }}]" value="0"
                           data-time-for="{{ $question->id }}">
                </div>
            </div>
        @endforeach

        <button type="submit" class="btn btn-primary btn-lg w-100 btn-touch">
            <i class="bi bi-check2-circle me-1"></i>Nộp bài
        </button>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('practice-form');
    if (!form) return;

    const counter = document.getElementById('answered-count');
    const cards = [...form.querySelectorAll('[data-question-id]')];
    const total = cards.length;

    // Đếm thời gian dừng ở từng câu, tính từ lần chạm đầu tiên vào câu đó.
    const firstTouch = new Map();

    function updateCounter() {
        const answered = cards.filter((card) => {
            const inputs = [...card.querySelectorAll('input, textarea')];
            return inputs.some((el) => {
                if (el.type === 'radio' || el.type === 'checkbox') return el.checked;
                if (el.type === 'hidden') return false;
                return el.value.trim() !== '';
            });
        }).length;

        counter.textContent = `${answered}/${total}`;
    }

    cards.forEach((card) => {
        const id = card.dataset.questionId;

        card.addEventListener('input', () => {
            if (!firstTouch.has(id)) firstTouch.set(id, Date.now());
            updateCounter();
        });
        card.addEventListener('change', () => {
            if (!firstTouch.has(id)) firstTouch.set(id, Date.now());
            updateCounter();
        });
    });

    form.addEventListener('submit', () => {
        cards.forEach((card) => {
            const id = card.dataset.questionId;
            const field = card.querySelector(`[data-time-for="${id}"]`);
            if (field && firstTouch.has(id)) {
                field.value = Math.min(1800, Math.round((Date.now() - firstTouch.get(id)) / 1000));
            }
        });
    });
})();
</script>
@endpush
