@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'AI soạn bài — TOÁN AI')
@section('page_title', 'AI soạn bài')

@php
    use App\Models\Question;
    $statusBadge = [
        'pending' => ['secondary', 'Đang chờ'],
        'processing' => ['info', 'Đang soạn'],
        'ready' => ['success', 'Sẵn sàng duyệt'],
        'failed' => ['danger', 'Lỗi'],
    ];
@endphp

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
        <h2 class="h5 fw-bold mb-0"><i class="bi bi-robot text-primary me-1"></i>AI hỗ trợ soạn nội dung</h2>
        <span class="badge text-bg-light border">
            @if ($usage['limit'] === null)
                Không giới hạn lượt
            @else
                Còn {{ $usage['remaining'] }}/{{ $usage['limit'] }} lượt hôm nay
            @endif
        </span>
    </div>
    <div class="alert alert-light border small mb-4">
        <i class="bi bi-shield-check me-1"></i>
        AI chỉ tạo <strong>bản nháp</strong>. Không có gì đến tay học sinh cho tới khi bạn duyệt từng câu / từng bài.
    </div>

    @if ($errors->any())
        <div class="alert alert-danger small"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3 mb-4">
        {{-- Tạo câu hỏi --}}
        <div class="col-12 col-xl-6">
            <div class="card border h-100">
                <div class="card-body">
                    <h3 class="h6 fw-bold mb-3"><i class="bi bi-question-circle me-1"></i>Tạo câu hỏi</h3>
                    <form method="POST" action="{{ route('teacher.ai.questions') }}" data-ai-generate-form>
                        @csrf
                        <div class="mb-3">@include('teacher.ai.partials.topic-select', ['id' => 'q-topic'])</div>

                        <div class="row g-2 mb-3">
                            <div class="col-6 col-sm-3">
                                <label class="form-label small mb-0" for="count">Số câu</label>
                                <input type="number" id="count" name="count" min="1" max="20" value="{{ old('count', 10) }}" class="form-control" required>
                            </div>
                            @foreach (['easy' => ['Dễ %', 30], 'medium' => ['TB %', 50], 'hard' => ['Khó %', 20]] as $key => [$label, $default])
                                <div class="col-6 col-sm-3">
                                    <label class="form-label small mb-0" for="ai-{{ $key }}">{{ $label }}</label>
                                    <input type="number" id="ai-{{ $key }}" name="{{ $key }}" min="0" max="100" value="{{ old($key, $default) }}" class="form-control" required>
                                </div>
                            @endforeach
                        </div>

                        <div class="mb-3">
                            <div class="form-label small mb-1">Loại câu hỏi</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($types as $type)
                                    <input type="checkbox" class="btn-check" name="types[]" id="t-{{ $type }}" value="{{ $type }}"
                                           @checked(in_array($type, old('types', ['single_choice', 'short_answer']), true))>
                                    <label class="btn btn-sm btn-outline-secondary" for="t-{{ $type }}">{{ Question::TYPES[$type] }}</label>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small mb-0" for="q-notes">Yêu cầu thêm (không bắt buộc)</label>
                            <input type="text" id="q-notes" name="notes" maxlength="500" value="{{ old('notes') }}" class="form-control"
                                   placeholder="VD: dùng ví dụ thực tế về chia bánh, tiền">
                        </div>

                        <button class="btn btn-primary btn-touch"><i class="bi bi-stars me-1"></i>Tạo câu hỏi</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Tạo bài học --}}
        <div class="col-12 col-xl-6">
            <div class="card border h-100">
                <div class="card-body">
                    <h3 class="h6 fw-bold mb-3"><i class="bi bi-journal-plus me-1"></i>Tạo bài học</h3>
                    <form method="POST" action="{{ route('teacher.ai.lesson') }}" data-ai-generate-form>
                        @csrf
                        <div class="mb-3">@include('teacher.ai.partials.topic-select', ['id' => 'l-topic'])</div>

                        <div class="mb-3">
                            <label class="form-label small mb-0" for="l-title">Tên bài học</label>
                            <input type="text" id="l-title" name="title" maxlength="191" value="{{ old('title') }}" class="form-control" required
                                   placeholder="VD: Quy đồng mẫu số nhiều phân số">
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-12 col-sm-4">
                                <label class="form-label small mb-0" for="l-diff">Độ khó</label>
                                <select id="l-diff" name="difficulty" class="form-select">
                                    @foreach (Question::DIFFICULTIES as $v => $l)
                                        <option value="{{ $v }}" @selected(old('difficulty', 'medium') === $v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-sm-8">
                                <label class="form-label small mb-0" for="l-notes">Yêu cầu thêm</label>
                                <input type="text" id="l-notes" name="notes" maxlength="500" value="{{ old('notes') }}" class="form-control">
                            </div>
                        </div>

                        <button class="btn btn-primary btn-touch"><i class="bi bi-stars me-1"></i>Soạn bài học</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <h3 class="h6 fw-bold mb-2">Bản nháp của tôi</h3>
    @forelse ($drafts as $draft)
        <a href="{{ route('teacher.ai.show', $draft) }}" class="card border mb-2 text-decoration-none text-body">
            <div class="card-body py-2 d-flex align-items-center gap-2">
                <i class="bi {{ $draft->type === 'lesson' ? 'bi-journal-text' : 'bi-question-circle' }} text-primary"></i>
                <div class="flex-grow-1 min-w-0">
                    <div class="text-truncate">
                        @if ($draft->type === 'lesson')
                            Bài học: {{ $draft->input['title'] ?? '' }}
                        @else
                            {{ $draft->input['count'] ?? '' }} câu hỏi
                        @endif
                    </div>
                    <div class="text-secondary small">{{ $draft->created_at->diffForHumans() }}</div>
                </div>
                <span class="badge text-bg-{{ $statusBadge[$draft->status][0] }}">{{ $statusBadge[$draft->status][1] }}</span>
            </div>
        </a>
    @empty
        <p class="text-secondary small">Chưa có bản nháp nào.</p>
    @endforelse

    <x-pagination :paginator="$drafts" label="bản nháp" class="mt-3" />
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-ai-generate-form]').forEach((form) => {
    const select = form.querySelector('[data-topic-select]');
    const grade = form.querySelector('[data-grade-input]');
    const sync = () => { grade.value = select.selectedOptions[0]?.dataset.gradeId || ''; };
    select.addEventListener('change', sync);
    sync();
});
</script>
@endpush
