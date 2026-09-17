@extends('layouts.app', ['portal' => 'teacher'])

@section('title', $class->name . ' — TOÁN AI')
@section('page_title', $class->name)

@section('content')
    <a href="{{ route('teacher.classes.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Lớp học
    </a>

    <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mt-2 mb-4">
        <div>
            <h2 class="h4 fw-bold mb-1">{{ $class->name }}</h2>
            <div class="text-secondary small">
                {{ $class->grade->name }} ·
                GV: {{ $class->teachers->map(fn ($t) => $t->name . ($t->pivot->role === 'assistant' ? ' (phụ)' : ''))->implode(', ') }}
            </div>
            @if ($class->status === 'archived')
                <span class="badge text-bg-secondary mt-1">Đã lưu trữ</span>
            @endif
        </div>

        <div class="card border-primary">
            <div class="card-body py-2 px-3 text-center">
                <div class="small text-secondary">Mã tham gia lớp</div>
                <div class="fs-3 fw-bold font-monospace" style="letter-spacing:.15em">{{ $class->code }}</div>
                @if ($isOwner)
                    <form method="POST" action="{{ route('teacher.classes.code', $class) }}"
                          onsubmit="return confirm('Đổi mã? Mã cũ sẽ không dùng được nữa.')">
                        @csrf
                        <button class="btn btn-link btn-sm p-0">Đổi mã</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="{{ route('teacher.assignments.create', ['class_id' => $class->id]) }}" class="btn btn-primary">
            <i class="bi bi-send-plus me-1"></i>Giao bài cho lớp
        </a>
    </div>

    {{-- ---------- Học sinh ---------- --}}
    <h3 class="h6 fw-bold mb-2">Học sinh</h3>

    @include('teacher.partials.student-filters')
    @include('teacher.partials.student-table', ['students' => $students, 'removeFrom' => $class])

    <form method="POST" action="{{ route('teacher.classes.students.add', $class) }}"
          class="d-flex flex-column flex-sm-row gap-2 mt-2 mb-5" style="max-width:32rem">
        @csrf
        <input type="email" name="email" required placeholder="Email học sinh"
               class="form-control @error('email') is-invalid @enderror" aria-label="Email học sinh">
        <button class="btn btn-outline-primary flex-shrink-0">
            <i class="bi bi-person-plus me-1"></i>Thêm học sinh
        </button>
    </form>
    @error('email') <div class="text-danger small mt-n4 mb-4">{{ $message }}</div> @enderror

    {{-- ---------- Bài giao gần đây ---------- --}}
    <h3 class="h6 fw-bold mb-2">Bài giao gần đây</h3>

    @forelse ($assignments as $assignment)
        <a href="{{ route('teacher.assignments.show', $assignment) }}" class="card border mb-2 text-decoration-none text-body">
            <div class="card-body d-flex align-items-center gap-3 py-2">
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ $assignment->title }}</div>
                    <div class="text-secondary small">
                        {{ $assignment->typeLabel() }}
                        @if ($assignment->due_at) · hạn {{ $assignment->due_at->format('H:i d/m') }} @endif
                    </div>
                </div>
                <span class="badge text-bg-light border">{{ $assignment->done_count }}/{{ $assignment->recipients_count }} đã làm</span>
            </div>
        </a>
    @empty
        <p class="text-secondary small">Chưa giao bài nào.</p>
    @endforelse

    @if ($isOwner)
        {{-- ---------- Cài đặt lớp ---------- --}}
        <h3 class="h6 fw-bold mt-5 mb-2">Cài đặt lớp</h3>

        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="card border h-100">
                    <div class="card-body">
                        <form method="POST" action="{{ route('teacher.classes.update', $class) }}" novalidate>
                            @csrf
                            @method('PUT')
                            @include('teacher.classes.partials.form')
                            <button class="btn btn-primary mt-3">Lưu</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card border mb-3">
                    <div class="card-body">
                        <div class="fw-semibold mb-2">Thêm giáo viên phụ</div>
                        <form method="POST" action="{{ route('teacher.classes.assistants.add', $class) }}" class="d-flex gap-2">
                            @csrf
                            <input type="email" name="email" required placeholder="Email giáo viên" class="form-control"
                                   aria-label="Email giáo viên">
                            <button class="btn btn-outline-primary flex-shrink-0">Thêm</button>
                        </form>
                        <div class="form-text">Giáo viên phụ xem được học sinh, giao bài và thêm/xoá học sinh.</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('teacher.classes.archive', $class) }}">
                    @csrf
                    <button class="btn {{ $class->status === 'archived' ? 'btn-outline-success' : 'btn-outline-danger' }}">
                        {{ $class->status === 'archived' ? 'Mở lại lớp' : 'Lưu trữ lớp' }}
                    </button>
                </form>
            </div>
        </div>
    @endif
@endsection
