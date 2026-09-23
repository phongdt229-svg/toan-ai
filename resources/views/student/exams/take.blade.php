@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Đang làm: ' . $exam->title . ' — TOÁN AI')
@section('page_title', $exam->title)

@section('content')
    {{-- Thanh đồng hồ dính trên cùng, luôn thấy khi cuộn trên điện thoại. --}}
    <div class="card border-primary sticky-top mb-3" style="top:3.6rem;z-index:1010">
        <div class="card-body py-2 d-flex align-items-center gap-3">
            <div class="fw-bold fs-4 font-monospace" id="exam-timer" aria-live="polite">--:--</div>
            <div class="small text-secondary flex-grow-1">
                <span id="exam-answered">0</span>/{{ $questions->count() }} câu đã trả lời
                <span class="d-block" id="exam-save-state">Đã lưu</span>
            </div>
            <button type="submit" form="exam-submit-form" class="btn btn-primary btn-sm">Nộp bài</button>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-1 mb-3" id="exam-nav" aria-label="Chuyển nhanh đến câu">
        @foreach ($questions as $i => $question)
            <a href="#q-{{ $question->id }}" class="btn btn-sm btn-outline-secondary"
               style="min-width:2.5rem" data-nav-for="{{ $question->id }}">{{ $i + 1 }}</a>
        @endforeach
    </div>

    <div id="exam-root"
         data-save-url="{{ route('student.exams.answer', $attempt) }}"
         data-remaining="{{ $remainingSeconds }}">
        @foreach ($questions as $i => $question)
            <div class="card border mb-3" id="q-{{ $question->id }}"
                 data-question-id="{{ $question->id }}" data-question-type="{{ $question->type }}">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge text-bg-primary">Câu {{ $i + 1 }}</span>
                        <span class="text-secondary small ms-auto">
                            {{ \App\Support\Score::format($question->pivot->points) }} điểm
                        </span>
                    </div>

                    <div class="mb-3" data-math>{!! $question->content !!}</div>

                    @include('student.practice.partials.input', [
                        'question' => $question,
                        'answers' => $saved,
                    ])
                </div>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('student.exams.submit', $attempt) }}" id="exam-submit-form">
        @csrf
        <button type="submit" class="btn btn-primary btn-lg w-100 btn-touch">
            <i class="bi bi-send me-1"></i>Nộp bài
        </button>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const root = document.getElementById('exam-root');
    if (!root) return;

    const saveUrl = root.dataset.saveUrl;
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const timerEl = document.getElementById('exam-timer');
    const answeredEl = document.getElementById('exam-answered');
    const saveStateEl = document.getElementById('exam-save-state');
    const submitForm = document.getElementById('exam-submit-form');
    const cards = [...root.querySelectorAll('[data-question-id]')];

    // ---- Đồng hồ -----------------------------------------------------------
    // Đếm từ số giây server gửi, không từ đồng hồ máy — máy học sinh có thể chỉnh giờ.
    let remaining = Number(root.dataset.remaining);
    let deadline = Date.now() + remaining * 1000;
    let submitting = false;

    function renderTimer() {
        remaining = Math.max(0, Math.round((deadline - Date.now()) / 1000));
        const m = String(Math.floor(remaining / 60)).padStart(2, '0');
        const s = String(remaining % 60).padStart(2, '0');
        timerEl.textContent = `${m}:${s}`;
        timerEl.classList.toggle('text-danger', remaining <= 60);

        if (remaining === 0 && !submitting) {
            submitting = true;
            flushAll().finally(() => submitForm.submit());
        }
    }

    renderTimer();
    setInterval(renderTimer, 1000);

    // ---- Đọc giá trị trả lời theo loại câu -----------------------------------
    function readValue(card) {
        const type = card.dataset.questionType;
        const q = (sel) => [...card.querySelectorAll(sel)];

        switch (type) {
            case 'single_choice':
            case 'true_false':
                return q('input[type="radio"]:checked')[0]?.value ?? null;
            case 'multiple_choice':
                return q('input[type="checkbox"]:checked').map((el) => el.value);
            case 'fill_blank':
                return q('input[type="text"]').map((el) => el.value);
            default:
                return card.querySelector('input[type="text"], textarea')?.value ?? null;
        }
    }

    function isAnswered(value) {
        if (value === null) return false;
        if (Array.isArray(value)) return value.some((v) => String(v).trim() !== '');
        return String(value).trim() !== '';
    }

    function refreshProgress() {
        let count = 0;
        cards.forEach((card) => {
            const done = isAnswered(readValue(card));
            count += done ? 1 : 0;
            const nav = document.querySelector(`[data-nav-for="${card.dataset.questionId}"]`);
            nav?.classList.toggle('btn-success', done);
            nav?.classList.toggle('btn-outline-secondary', !done);
        });
        answeredEl.textContent = count;
    }

    // ---- Autosave ------------------------------------------------------------
    const dirty = new Set();
    const timers = new Map();
    const openedAt = new Map();

    async function save(card) {
        const id = card.dataset.questionId;
        dirty.delete(id);
        timers.delete(id);
        saveStateEl.textContent = 'Đang lưu…';

        const spent = openedAt.has(id) ? Math.round((Date.now() - openedAt.get(id)) / 1000) : 0;

        try {
            const res = await fetch(saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ question_id: Number(id), value: readValue(card), time_spent: spent }),
            });
            const body = await res.json().catch(() => ({}));

            if (res.status === 409 && body.redirect) {
                // Hết giờ hoặc bài đã nộp ở tab khác — server đã chốt, chuyển sang kết quả.
                window.onbeforeunload = null;
                window.location = body.redirect;
                return;
            }

            if (!res.ok) throw new Error('save failed');

            // Đồng bộ lại đồng hồ theo server mỗi lần lưu.
            if (typeof body.data?.remaining_seconds === 'number') {
                deadline = Date.now() + body.data.remaining_seconds * 1000;
            }
            saveStateEl.textContent = dirty.size ? 'Đang lưu…' : 'Đã lưu';
        } catch (e) {
            dirty.add(id);
            saveStateEl.textContent = 'Chưa lưu được — sẽ thử lại';
        }
    }

    function schedule(card, delay) {
        const id = card.dataset.questionId;
        dirty.add(id);
        saveStateEl.textContent = 'Có thay đổi…';
        clearTimeout(timers.get(id));
        timers.set(id, setTimeout(() => save(card), delay));
    }

    function flushAll() {
        timers.forEach((t) => clearTimeout(t));
        const pending = cards.filter((c) => dirty.has(c.dataset.questionId));
        return Promise.allSettled(pending.map(save));
    }

    cards.forEach((card) => {
        const id = card.dataset.questionId;

        card.addEventListener('focusin', () => {
            if (!openedAt.has(id)) openedAt.set(id, Date.now());
        });
        // Chọn đáp án → lưu gần như ngay; gõ chữ → đợi ngừng gõ rồi mới lưu.
        card.addEventListener('change', () => { refreshProgress(); schedule(card, 150); });
        card.addEventListener('input', (e) => {
            if (e.target.type === 'text' || e.target.tagName === 'TEXTAREA') {
                refreshProgress();
                schedule(card, 1200);
            }
        });
    });

    // Thử lưu lại các câu lỗi mạng mỗi 10 giây.
    setInterval(() => {
        cards.filter((c) => dirty.has(c.dataset.questionId) && !timers.has(c.dataset.questionId))
            .forEach(save);
    }, 10000);

    submitForm.addEventListener('submit', async (e) => {
        if (submitting) return;
        e.preventDefault();

        const unanswered = cards.length - Number(answeredEl.textContent);
        const msg = unanswered > 0
            ? `Còn ${unanswered} câu chưa trả lời. Vẫn nộp bài?`
            : 'Nộp bài? Sau khi nộp không sửa được nữa.';

        if (!await window.confirmDialog(msg, { title: 'Nộp bài kiểm tra', ok: 'Nộp bài' })) return;

        submitting = true;
        window.onbeforeunload = null;
        flushAll().finally(() => submitForm.submit());
    });

    window.onbeforeunload = () => (dirty.size > 0 ? 'Có câu trả lời chưa lưu.' : undefined);

    refreshProgress();
})();
</script>
@endpush
