@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Chương trình học — TOÁN AI')
@section('page_title', 'Chương trình học')

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <h2 class="h5 fw-bold mb-0">Chương trình {{ $grade->name }}</h2>

        <form method="GET" class="d-flex align-items-center gap-2">
            <label for="grade" class="form-label mb-0 small text-secondary">Đổi lớp</label>
            <select id="grade" name="grade" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                @foreach ($grades as $g)
                    <option value="{{ $g->slug }}" @selected($g->id === $grade->id)>{{ $g->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if ($subjects->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4 text-secondary">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                Chương trình {{ $grade->name }} chưa có nội dung.
            </div>
        </div>
    @else
        <div class="accordion" id="curriculum">
            @foreach ($subjects as $subject)
                @foreach ($subject->chapters as $chapter)
                    @php $collapseId = "chapter-{$chapter->id}"; @endphp
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button {{ $loop->parent->first && $loop->first ? '' : 'collapsed' }}"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}">
                                <span class="fw-semibold">{{ $chapter->name }}</span>
                                <span class="badge text-bg-light border ms-2">{{ $chapter->topics->count() }} chủ đề</span>
                            </button>
                        </h3>

                        <div id="{{ $collapseId }}"
                             class="accordion-collapse collapse {{ $loop->parent->first && $loop->first ? 'show' : '' }}"
                             data-bs-parent="#curriculum">
                            <div class="accordion-body p-2">
                                @forelse ($chapter->topics as $topic)
                                    <a href="{{ route('student.learn.topic', $topic) }}"
                                       class="d-flex align-items-center gap-3 p-3 rounded-3 text-decoration-none text-body border mb-2">
                                        <i class="bi bi-diagram-3 text-primary"></i>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold">{{ $topic->name }}</div>
                                            <div class="text-secondary small">
                                                {{ $topic->lessons_count }} bài học
                                            </div>
                                        </div>
                                        <i class="bi bi-chevron-right text-secondary"></i>
                                    </a>
                                @empty
                                    <p class="text-secondary small mb-0 p-2">Chương này chưa có chủ đề.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    @endif
@endsection
