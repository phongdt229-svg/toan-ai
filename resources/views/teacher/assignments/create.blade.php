@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Giao bài — TOÁN AI')
@section('page_title', 'Giao bài')

@php
    use App\Models\Assignment;

    $type = old('type', Assignment::TYPE_QUESTION_SET);
    $toAll = old('assign_to_all', '1') == '1';
    $oldStudents = array_map('strval', old('student_ids', []));
    $oldQuestions = array_map('strval', old('question_ids', []));
@endphp

@section('content')
    <a href="{{ route('teacher.assignments.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Bài đã giao
    </a>

    <h2 class="h5 fw-bold mt-2 mb-3">Giao bài mới</h2>

    @if ($errors->any())
        <div class="alert alert-danger small">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Bước 1 đổi lớp bằng GET để các bước sau nạp đúng học sinh / nội dung của khối lớp đó. --}}
    <div class="card border mb-3">
        <div class="card-body">
            <div class="fw-semibold mb-2"><span class="badge text-bg-primary me-1">1</span>Chọn lớp</div>
            <form method="GET" class="d-flex gap-2">
                <select name="class_id" class="form-select" onchange="this.form.submit()" aria-label="Lớp">
                    @foreach ($classes as $c)
                        <option value="{{ $c->id }}" @selected($c->id === $class->id)>{{ $c->name }} ({{ $c->grade->name }})</option>
                    @endforeach
                </select>
                <noscript><button class="btn btn-outline-secondary">Chọn</button></noscript>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('teacher.assignments.store') }}" novalidate id="assign-form">
        @csrf
        <input type="hidden" name="class_id" value="{{ $class->id }}">

        {{-- Bước 2: học sinh --}}
        <div class="card border mb-3">
            <div class="card-body">
                <div class="fw-semibold mb-2"><span class="badge text-bg-primary me-1">2</span>Chọn học sinh</div>

                @if ($students->isEmpty())
                    <div class="alert alert-warning small mb-0">
                        Lớp chưa có học sinh. Đưa mã <strong class="font-monospace">{{ $class->code }}</strong> cho học sinh tham gia trước.
                    </div>
                @else
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="assign_to_all"
                               name="assign_to_all" value="1" @checked($toAll) data-toggle-students>
                        <label class="form-check-label" for="assign_to_all">
                            Cả lớp ({{ $students->count() }} học sinh) — học sinh vào lớp sau cũng nhận bài
                        </label>
                    </div>

                    <div class="d-flex flex-wrap gap-2" data-student-list @if ($toAll) hidden @endif>
                        @foreach ($students as $s)
                            <input type="checkbox" class="btn-check" name="student_ids[]" id="st-{{ $s->id }}"
                                   value="{{ $s->id }}" @checked(in_array((string) $s->id, $oldStudents, true))>
                            <label class="btn btn-sm btn-outline-secondary" for="st-{{ $s->id }}">{{ $s->name }}</label>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Bước 3: nội dung --}}
        <div class="card border mb-3">
            <div class="card-body">
                <div class="fw-semibold mb-2"><span class="badge text-bg-primary me-1">3</span>Chọn bài</div>

                <div class="mb-3">
                    <label for="title" class="form-label">Tên bài giao <span class="text-danger">*</span></label>
                    <input type="text" id="title" name="title" value="{{ old('title') }}" maxlength="191" required
                           class="form-control @error('title') is-invalid @enderror" placeholder="VD: BTVN tuần 3 — Cộng phân số">
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Lời dặn</label>
                    <textarea id="description" name="description" rows="2" class="form-control">{{ old('description') }}</textarea>
                </div>

                <div class="btn-group w-100 mb-3" role="group" aria-label="Loại bài">
                    @foreach (Assignment::TYPES as $value => $label)
                        <input type="radio" class="btn-check" name="type" id="type-{{ $value }}" value="{{ $value }}"
                               @checked($type === $value) data-type-radio>
                        <label class="btn btn-outline-primary" for="type-{{ $value }}">{{ $label }}</label>
                    @endforeach
                </div>

                <div data-type-block="question_set">
                    @if ($questions->isEmpty())
                        <p class="text-secondary small mb-0">Ngân hàng chưa có câu hỏi tự chấm cho {{ $class->grade->name }}.</p>
                    @else
                        <p class="text-secondary small">
                            Chỉ hiện câu tự chấm được. Câu tự luận hãy đưa vào đề kiểm tra để chấm tay.
                            <span class="fw-semibold" data-question-count>0</span> câu đã chọn.
                        </p>
                        <div class="d-grid gap-2" style="max-height:28rem;overflow-y:auto">
                            @foreach ($questions as $q)
                                <label class="border rounded-3 p-2 d-flex gap-2 align-items-start" for="q-{{ $q->id }}">
                                    <input class="form-check-input mt-1" type="checkbox" name="question_ids[]" id="q-{{ $q->id }}"
                                           value="{{ $q->id }}" @checked(in_array((string) $q->id, $oldQuestions, true))>
                                    <div class="flex-grow-1 min-w-0 small">
                                        <span class="badge text-bg-light border">{{ $q->typeLabel() }}</span>
                                        <span class="badge text-bg-light border">{{ $q->difficultyLabel() }}</span>
                                        @if ($q->topic)<span class="text-secondary">{{ $q->topic->name }}</span>@endif
                                        <div data-math>{!! $q->content !!}</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div data-type-block="exam">
                    @if ($exams->isEmpty())
                        <p class="text-secondary small mb-0">Chưa có đề đã xuất bản cho {{ $class->grade->name }}.</p>
                    @else
                        <select name="exam_id" class="form-select" aria-label="Đề kiểm tra">
                            <option value="">— Chọn đề —</option>
                            @foreach ($exams as $exam)
                                <option value="{{ $exam->id }}" @selected(old('exam_id') == $exam->id)>
                                    {{ $exam->title }} ({{ $exam->total_questions }} câu, {{ $exam->duration_minutes }} phút)
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Số lượt làm theo cài đặt của đề. Chỉ lượt làm sau khi giao mới được tính.
                        </div>
                    @endif
                </div>

                <div data-type-block="lesson">
                    @if ($lessons->isEmpty())
                        <p class="text-secondary small mb-0">Chưa có bài học đã xuất bản cho {{ $class->grade->name }}.</p>
                    @else
                        <select name="lesson_id" class="form-select" aria-label="Bài học">
                            <option value="">— Chọn bài học —</option>
                            @foreach ($lessons as $lesson)
                                <option value="{{ $lesson->id }}" @selected(old('lesson_id') == $lesson->id)>
                                    {{ $lesson->topic->name }} · {{ $lesson->title }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Học sinh đã học xong bài này từ trước được tính là hoàn thành luôn.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Bước 4–5: hạn nộp & làm lại --}}
        <div class="card border mb-4">
            <div class="card-body">
                <div class="fw-semibold mb-2"><span class="badge text-bg-primary me-1">4</span>Hạn nộp & làm lại</div>

                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <label for="due_at" class="form-label">Hạn nộp</label>
                        <input type="datetime-local" id="due_at" name="due_at" value="{{ old('due_at') }}"
                               class="form-control @error('due_at') is-invalid @enderror">
                        <div class="form-text">Để trống = không có hạn. Nộp sau hạn vẫn được nhận nhưng bị đánh dấu trễ.</div>
                    </div>

                    <div class="col-12 col-sm-6" data-type-block="question_set">
                        <div class="form-check form-switch mt-sm-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="allow_retry"
                                   name="allow_retry" value="1" @checked(old('allow_retry'))>
                            <label class="form-check-label" for="allow_retry">Cho phép làm lại</label>
                        </div>
                        <div class="input-group input-group-sm mt-2" style="max-width:14rem">
                            <span class="input-group-text">Tối đa</span>
                            <input type="number" name="max_attempts" min="2" max="10" value="{{ old('max_attempts', 3) }}"
                                   class="form-control" aria-label="Số lượt tối đa">
                            <span class="input-group-text">lượt</span>
                        </div>
                        <div class="form-text">Điểm tính theo lượt cao nhất.</div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100 btn-touch" @disabled($students->isEmpty())>
            <i class="bi bi-send me-1"></i>Giao bài
        </button>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('assign-form');
    if (!form) return;

    const syncType = () => {
        const type = form.querySelector('[data-type-radio]:checked')?.value;
        form.querySelectorAll('[data-type-block]').forEach((el) => {
            el.hidden = el.dataset.typeBlock !== type;
            // Khối ẩn không gửi dữ liệu — tránh chọn đề rồi đổi sang bộ câu hỏi mà vẫn gửi exam_id.
            el.querySelectorAll('input, select').forEach((f) => { f.disabled = el.hidden; });
        });
    };
    form.querySelectorAll('[data-type-radio]').forEach((r) => r.addEventListener('change', syncType));
    syncType();

    const toggle = form.querySelector('[data-toggle-students]');
    const list = form.querySelector('[data-student-list]');
    toggle?.addEventListener('change', () => { list.hidden = toggle.checked; });

    const counter = form.querySelector('[data-question-count]');
    const countQuestions = () => {
        if (counter) counter.textContent = form.querySelectorAll('input[name="question_ids[]"]:checked').length;
    };
    form.addEventListener('change', countQuestions);
    countQuestions();
})();
</script>
@endpush
