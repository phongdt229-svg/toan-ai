@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Đang làm kiểm tra đầu vào — TOÁN AI')
@section('page_title', 'Kiểm tra đầu vào')

@section('content')
    <div class="card border-primary sticky-top mb-3" style="top:3.6rem;z-index:1010">
        <div class="card-body py-2 d-flex align-items-center gap-3">
            <div class="fw-bold fs-4 font-monospace" id="placement-timer" aria-live="polite">--:--</div>
            <div class="small text-secondary flex-grow-1">{{ $questions->count() }} câu · đồng hồ không dừng khi đóng trang</div>
            <button type="submit" form="placement-form" class="btn btn-primary btn-sm">Nộp bài</button>
        </div>
    </div>

    <form method="POST" action="{{ route('student.placement.submit', $test) }}" id="placement-form"
          data-remaining="{{ $remainingSeconds }}">
        @csrf

        @foreach ($questions as $i => $question)
            <div class="card border mb-3" data-question-id="{{ $question->id }}">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge text-bg-primary">Câu {{ $i + 1 }}</span>
                        <span class="badge text-bg-light border">{{ $question->difficultyLabel() }}</span>
                    </div>
                    <div class="mb-3" data-math>{!! $question->content !!}</div>

                    @include('student.practice.partials.input', ['question' => $question, 'answers' => []])

                    <input type="hidden" name="time_spent[{{ $question->id }}]" value="0" data-time-for="{{ $question->id }}">
                </div>
            </div>
        @endforeach

        <button type="submit" class="btn btn-primary btn-lg w-100 btn-touch">
            <i class="bi bi-send me-1"></i>Nộp bài
        </button>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('placement-form');
    if (!form) return;

    const timer = document.getElementById('placement-timer');
    // Đếm từ số giây server gửi — không tin đồng hồ máy.
    const deadline = Date.now() + Number(form.dataset.remaining) * 1000;
    const firstTouch = new Map();
    let submitting = false;

    const fillTimes = () => firstTouch.forEach((start, id) => {
        const f = form.querySelector(`[data-time-for="${id}"]`);
        if (f) f.value = Math.min(1800, Math.round((Date.now() - start) / 1000));
    });

    form.querySelectorAll('[data-question-id]').forEach((card) => {
        const mark = () => { if (!firstTouch.has(card.dataset.questionId)) firstTouch.set(card.dataset.questionId, Date.now()); };
        card.addEventListener('input', mark);
        card.addEventListener('change', mark);
    });

    const tick = () => {
        const left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
        timer.textContent = `${String(Math.floor(left / 60)).padStart(2, '0')}:${String(left % 60).padStart(2, '0')}`;
        timer.classList.toggle('text-danger', left <= 60);
        if (left === 0 && !submitting) {
            submitting = true;
            fillTimes();
            form.submit();
        }
    };
    tick();
    setInterval(tick, 1000);

    form.addEventListener('submit', (e) => {
        if (submitting) return;
        if (!confirm('Nộp bài kiểm tra đầu vào?')) { e.preventDefault(); return; }
        submitting = true;
        fillTimes();
    });
})();
</script>
@endpush
