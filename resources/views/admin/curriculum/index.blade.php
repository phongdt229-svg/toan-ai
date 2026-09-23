@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Chương trình học — TOÁN AI')
@section('page_title', 'Chương trình học')

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <h2 class="h5 fw-bold mb-0">Cây chương trình {{ $grade->name }}</h2>

        <form method="GET" class="d-flex align-items-center gap-2">
            <label for="grade" class="form-label mb-0 small text-secondary">Lớp</label>
            <select id="grade" name="grade" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                @foreach ($grades as $g)
                    <option value="{{ $g->slug }}" @selected($g->id === $grade->id)>{{ $g->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <p class="text-secondary small">
        Cấu trúc: Lớp → Môn → Chương → Chủ đề. Bài học do giáo viên soạn trong portal giáo viên.
    </p>

    @foreach ($subjects as $subject)
        <div class="card border mb-3">
            <div class="card-body">
                <h3 class="h6 fw-bold mb-3">
                    <i class="bi bi-journal-bookmark text-primary me-1"></i>{{ $subject->name }}
                </h3>

                @foreach ($subject->chapters as $chapter)
                    <div class="border rounded-3 p-3 mb-2">
                        <div class="fw-semibold mb-2">{{ $chapter->name }}</div>

                        @forelse ($chapter->topics as $topic)
                            <div class="d-flex align-items-center gap-2 py-1">
                                <i class="bi bi-dot"></i>
                                <span class="flex-grow-1">{{ $topic->name }}</span>
                                <span class="badge text-bg-light border">{{ $topic->lessons_count }} bài</span>

                                <form method="POST" action="{{ route('admin.curriculum.topics.destroy', $topic) }}"
                                      data-confirm="Xoá chủ đề {{ $topic->name }}?" data-confirm-ok="Xoá">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger p-0" title="Xoá chủ đề">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        @empty
                            <p class="text-secondary small mb-2">Chương này chưa có chủ đề.</p>
                        @endforelse

                        <form method="POST" action="{{ route('admin.curriculum.topics.store', $chapter) }}"
                              class="d-flex gap-2 mt-2">
                            @csrf
                            <input type="text" name="name" class="form-control form-control-sm"
                                   placeholder="Tên chủ đề mới" required maxlength="191">
                            <button class="btn btn-sm btn-outline-primary flex-shrink-0">Thêm chủ đề</button>
                        </form>
                    </div>
                @endforeach

                <form method="POST" action="{{ route('admin.curriculum.chapters.store', $subject) }}"
                      class="d-flex gap-2 mt-3">
                    @csrf
                    <input type="text" name="name" class="form-control form-control-sm"
                           placeholder="Tên chương mới" required maxlength="191">
                    <button class="btn btn-sm btn-outline-primary flex-shrink-0">Thêm chương</button>
                </form>
            </div>
        </div>
    @endforeach

    <div class="card border border-primary">
        <div class="card-body">
            <h3 class="h6 fw-bold mb-3">Thêm môn học vào {{ $grade->name }}</h3>

            <form method="POST" action="{{ route('admin.curriculum.subjects.store', $grade) }}"
                  class="d-flex flex-column flex-sm-row gap-2">
                @csrf
                <input type="text" name="name" value="{{ old('name') }}"
                       class="form-control @error('name') is-invalid @enderror"
                       placeholder="Tên môn học (vd: Toán)" required maxlength="191">
                <button class="btn btn-primary flex-shrink-0 btn-touch">Thêm môn học</button>
            </form>
            @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
    </div>
@endsection
