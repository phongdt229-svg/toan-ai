@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Kiểm tra cuối buổi — TOÁN AI')
@section('page_title', 'Kiểm tra cuối buổi')

@section('content')
    <a href="{{ route('student.path.show') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Lộ trình
    </a>

    <h2 class="h5 fw-bold mt-2 mb-1">Kiểm tra cuối buổi {{ $session->session_no }}</h2>
    <p class="text-secondary small mb-3">
        {{ $questions->count() }} câu về các chủ đề vừa học, làm trong {{ \App\Services\Learning\LearningPathService::QUIZ_MINUTES }} phút —
        hết giờ bài tự nộp. Từ {{ \App\Services\Learning\LearningPathService::QUIZ_ADVANCE_PERCENT }}% trở lên là học bài mới;
        thấp hơn thì buổi sau có phần ôn đúng chỗ còn sai.
    </p>

    {{-- Đồng hồ dính trên cùng, luôn thấy khi cuộn trên điện thoại. --}}
    <div class="card border-primary sticky-top mb-3" style="top:3.6rem;z-index:1010">
        <div class="card-body py-2 d-flex align-items-center gap-3">
            <div class="fw-bold fs-4 font-monospace" id="quiz-timer" aria-live="polite">--:--</div>
            <div class="small text-secondary flex-grow-1">Thời gian còn lại</div>
            <button type="submit" form="quiz-form" class="btn btn-primary btn-sm">Nộp bài</button>
        </div>
    </div>

    <form method="POST" action="{{ route('student.path.quiz.submit', $session) }}" id="quiz-form"
          data-remaining="{{ $session->quizRemainingSeconds() }}">
        @csrf

        @foreach ($questions as $i => $question)
            <div class="card border mb-3" data-question-id="{{ $question->id }}">
                <div class="card-body">
                    <span class="badge text-bg-primary mb-2">Câu {{ $i + 1 }}</span>
                    <div class="mb-3" data-math>{!! $question->content !!}</div>
                    @include('student.practice.partials.input', ['question' => $question, 'answers' => []])
                    <input type="hidden" name="time_spent[{{ $question->id }}]" value="0" data-time-for="{{ $question->id }}">
                </div>
            </div>
        @endforeach

        <button class="btn btn-primary btn-lg w-100 btn-touch"><i class="bi bi-send me-1"></i>Nộp bài</button>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('quiz-form');
    if (!form) return;

    const timer = document.getElementById('quiz-timer');
    // Đếm từ số giây server gửi — không tin đồng hồ máy.
    const deadline = Date.now() + Number(form.dataset.remaining) * 1000;
    const firstTouch = new Map();
    let submitting = false;

    const fillTimes = () => firstTouch.forEach((start, id) => {
        const f = form.querySelector(`[data-time-for="${id}"]`);
        if (f) f.value = Math.min(900, Math.round((Date.now() - start) / 1000));
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

    form.addEventListener('submit', async (e) => {
        if (submitting) return;

        // Hộp thoại là bất đồng bộ nên phải chặn rồi tự gửi lại.
        e.preventDefault();

        if (!await window.confirmDialog('Nộp bài kiểm tra cuối buổi?', {
            title: 'Kiểm tra cuối buổi', ok: 'Nộp bài',
        })) return;

        submitting = true;
        fillTimes();
        form.submit();
    });
})();
</script>
@endpush
