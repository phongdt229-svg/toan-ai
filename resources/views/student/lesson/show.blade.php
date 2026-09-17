@extends('layouts.app', ['portal' => 'student'])

@section('title', $lesson->title . ' — TOÁN AI')
@section('page_title', $lesson->title)

@push('ai-context')
    data-context-type="lesson" data-context-id="{{ $lesson->id }}"
@endpush

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('student.learn.index') }}">Chương trình</a></li>
            <li class="breadcrumb-item">
                <a href="{{ route('student.learn.topic', $lesson->topic) }}">{{ $lesson->topic->name }}</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">{{ $lesson->title }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <h1 class="h4 fw-bold mb-2">{{ $lesson->title }}</h1>

            @if ($lesson->summary)
                <p class="text-secondary">{{ $lesson->summary }}</p>
            @endif

            <div class="progress mb-4" style="height:8px" role="progressbar"
                 aria-valuenow="{{ $progress->progress_percent }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" id="lesson-progress-bar"
                     style="width:{{ $progress->progress_percent }}%"></div>
            </div>

            <div id="lesson-sections" data-lesson-progress-url="{{ route('student.lesson.progress', $lesson) }}">
                @foreach ($lesson->sections as $section)
                    @include('student.lesson.partials.section', [
                        'section' => $section,
                        'isDone' => $completedSections->contains($section->id),
                    ])
                @endforeach
            </div>

            <form method="POST" action="{{ route('student.lesson.complete', $lesson) }}" class="mt-4">
                @csrf
                <button type="submit" class="btn btn-primary btn-lg w-100 btn-touch">
                    <i class="bi bi-check2-circle me-1"></i>Hoàn thành bài học
                </button>
            </form>
        </div>

        <aside class="col-12 col-lg-4">
            <div class="card border position-lg-sticky" style="top:5rem">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3">Nội dung bài học</h2>

                    <ol class="list-unstyled d-grid gap-1 mb-0 small">
                        @foreach ($lesson->sections as $section)
                            <li>
                                <a href="#section-{{ $section->id }}"
                                   class="d-flex align-items-center gap-2 text-decoration-none text-body p-2 rounded">
                                    <i class="bi {{ $section->typeIcon() }} text-primary"></i>
                                    <span class="flex-grow-1">{{ $section->title ?: $section->typeLabel() }}</span>
                                    <i class="bi bi-check-circle-fill text-success section-check
                                              {{ $completedSections->contains($section->id) ? '' : 'invisible' }}"
                                       data-check-for="{{ $section->id }}"></i>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </aside>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const root = document.getElementById('lesson-sections');
    if (!root) return;

    const url = root.dataset.lessonProgressUrl;
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const bar = document.getElementById('lesson-progress-bar');

    // Mốc thời gian mở từng section, để gửi kèm số giây đã đọc.
    const openedAt = new Map();

    async function markDone(sectionId) {
        const seconds = openedAt.has(sectionId)
            ? Math.min(300, Math.round((Date.now() - openedAt.get(sectionId)) / 1000))
            : 0;

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: JSON.stringify({ section_id: sectionId, seconds_spent: seconds }),
            });
            if (!res.ok) return;

            const { data } = await res.json();
            bar.style.width = data.progress_percent + '%';

            const check = document.querySelector(`[data-check-for="${sectionId}"]`);
            if (check) check.classList.remove('invisible');
        } catch (e) {
            // Mất mạng thì bỏ qua — lần mở bài sau sẽ ghi lại.
        }
    }

    root.querySelectorAll('[data-section-id]').forEach((el) => {
        const id = Number(el.dataset.sectionId);
        openedAt.set(id, Date.now());

        el.querySelector('[data-mark-done]')?.addEventListener('click', (e) => {
            e.preventDefault();
            e.currentTarget.closest('[data-section-id]').classList.add('border-success');
            e.currentTarget.disabled = true;
            e.currentTarget.innerHTML = '<i class="bi bi-check2 me-1"></i>Đã hiểu';
            markDone(id);
        });
    });
})();
</script>
@endpush
