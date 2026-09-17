@extends('layouts.app', ['portal' => 'teacher'])

@section('title', $student->name . ' — TOÁN AI')
@section('page_title', 'Học sinh')

@section('content')
    <a href="{{ route('teacher.students.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Học sinh
    </a>

    <div class="mt-2 mb-4">
        <h2 class="h4 fw-bold mb-1">{{ $student->name }}</h2>
        <div class="text-secondary small">
            {{ $student->studentProfile?->grade?->name }}
            @if ($student->studentProfile?->school) · {{ $student->studentProfile->school }} @endif
            · Lớp: {{ $classes->pluck('name')->implode(', ') }}
        </div>
    </div>

    @if ($insight)
        <div class="row g-3 mb-4">
            @foreach ([
                ['Đã làm', $insight['done'] . '/' . $insight['assigned']],
                ['Quá hạn', $insight['overdue']],
                ['Điểm TB', $insight['avg_percent'] !== null ? $insight['avg_percent'] . '%' : '—'],
                ['Chủ đề yếu', $insight['weak_topics']],
            ] as [$label, $value])
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-card__label">{{ $label }}</div>
                        <div class="stat-card__value">{{ $value }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <h3 class="h6 fw-bold mb-2">Bài được giao</h3>
            @forelse ($records as $r)
                <div class="card border mb-2">
                    <div class="card-body py-2 d-flex align-items-center gap-2">
                        <div class="flex-grow-1 min-w-0">
                            <a href="{{ route('teacher.assignments.show', $r->assignment) }}" class="fw-semibold text-decoration-none">
                                {{ $r->assignment->title }}
                            </a>
                            <div class="text-secondary small">
                                {{ $r->assignment->typeLabel() }}
                                @if ($r->assignment->due_at) · hạn {{ $r->assignment->due_at->format('d/m') }} @endif
                            </div>
                        </div>
                        @if ($r->isDone())
                            <span class="badge text-bg-{{ $r->status === 'completed' ? 'success' : 'info' }}">
                                {{ $r->percent !== null ? $r->percent . '%' : 'Chờ chấm' }}
                            </span>
                        @elseif ($r->assignment->isOverdue())
                            <span class="badge text-bg-danger">Quá hạn</span>
                        @else
                            <span class="badge text-bg-secondary">Chưa làm</span>
                        @endif
                        @if ($r->is_late)<span class="badge text-bg-warning text-dark">Trễ</span>@endif
                    </div>
                </div>
            @empty
                <p class="text-secondary small">Chưa có bài nào.</p>
            @endforelse

            <h3 class="h6 fw-bold mt-4 mb-2">Mức nắm vững chủ đề</h3>
            <div class="row g-2">
                <div class="col-12 col-sm-6">
                    <div class="card border-danger h-100">
                        <div class="card-body">
                            <div class="small fw-semibold text-danger mb-2">Còn yếu</div>
                            @forelse ($weakTopics as $m)
                                <div class="d-flex justify-content-between small"><span>{{ $m->topic->name }}</span><span>{{ $m->mastery_score }}%</span></div>
                            @empty
                                <div class="small text-secondary">Chưa đủ dữ liệu.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="card border-success h-100">
                        <div class="card-body">
                            <div class="small fw-semibold text-success mb-2">Làm tốt</div>
                            @forelse ($strongTopics as $m)
                                <div class="d-flex justify-content-between small"><span>{{ $m->topic->name }}</span><span>{{ $m->mastery_score }}%</span></div>
                            @empty
                                <div class="small text-secondary">Chưa đủ dữ liệu.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <h3 class="h6 fw-bold mb-2">Nhận xét</h3>

            <div class="card border mb-3">
                <div class="card-body">
                    <form method="POST" action="{{ route('teacher.students.comments.store', $student) }}">
                        @csrf
                        <textarea name="content" rows="3" maxlength="2000" required placeholder="Nhận xét về tình hình học của em…"
                                  class="form-control mb-2 @error('content') is-invalid @enderror" aria-label="Nội dung nhận xét">{{ old('content') }}</textarea>
                        @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror

                        @if ($classes->count() > 1)
                            <select name="class_id" class="form-select form-select-sm mb-2" aria-label="Lớp">
                                @foreach ($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                            </select>
                        @elseif ($classes->isNotEmpty())
                            <input type="hidden" name="class_id" value="{{ $classes->first()->id }}">
                        @endif

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="visible_to_parent" name="visible_to_parent" value="1" checked>
                            <label class="form-check-label small" for="visible_to_parent">Phụ huynh được xem</label>
                        </div>
                        <button class="btn btn-primary btn-sm">Lưu nhận xét</button>
                    </form>
                </div>
            </div>

            @forelse ($comments as $comment)
                <div class="border-start border-3 {{ $comment->visible_to_parent ? 'border-primary' : 'border-secondary' }} ps-3 mb-3">
                    <div class="small" style="white-space:pre-line">{{ $comment->content }}</div>
                    <div class="text-secondary small mt-1 d-flex align-items-center gap-2">
                        <span>{{ $comment->teacher->name }} · {{ $comment->created_at->format('d/m/Y') }}</span>
                        @unless ($comment->visible_to_parent)<span class="badge text-bg-light border">Chỉ giáo viên</span>@endunless
                        @if ($comment->teacher_id === auth()->id())
                            <form method="POST" action="{{ route('teacher.students.comments.destroy', $comment) }}" class="ms-auto">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-link btn-sm text-danger p-0">Xoá</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-secondary small">Chưa có nhận xét.</p>
            @endforelse
        </div>
    </div>
@endsection
