@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Sửa đề: ' . $exam->title . ' — TOÁN AI')
@section('page_title', 'Sửa đề kiểm tra')

@php
    use App\Models\Question;
    use App\Support\Score;
@endphp

@section('content')
    <a href="{{ route('teacher.exams.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Danh sách đề
    </a>

    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-0">{{ $exam->title }}</h2>
            <div class="text-secondary small">
                {{ $exam->total_questions }} câu · {{ Score::format($exam->total_points) }} điểm
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('teacher.exams.attempts', $exam) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-people me-1"></i>Bài làm
            </a>
            <form method="POST" action="{{ route('teacher.exams.publish', $exam) }}">
                @csrf
                <button class="btn btn-sm {{ $exam->isPublished() ? 'btn-outline-secondary' : 'btn-success' }}">
                    {{ $exam->isPublished() ? 'Gỡ xuất bản' : 'Xuất bản' }}
                </button>
            </form>
        </div>
    </div>

    <div class="card border mb-4">
        <div class="card-body">
            <h3 class="h6 fw-bold mb-3">Thông tin đề</h3>
            <form method="POST" action="{{ route('teacher.exams.update', $exam) }}" novalidate>
                @csrf
                @method('PUT')
                @include('teacher.exams.partials.form')
                <button type="submit" class="btn btn-primary mt-4 btn-touch">Lưu thông tin</button>
            </form>
        </div>
    </div>

    {{-- ---------------- Câu hỏi trong đề ---------------- --}}
    <h3 class="h6 fw-bold mb-2">Câu hỏi trong đề</h3>

    @if ($isLocked)
        <div class="alert alert-warning small">
            <i class="bi bi-lock me-1"></i>
            Đề đã có học sinh làm bài nên bộ câu hỏi và điểm bị khoá, để kết quả đã có không bị sai lệch.
            Muốn đổi câu hỏi, hãy tạo đề mới.
        </div>
    @endif

    @forelse ($examQuestions as $i => $question)
        <div class="card border mb-2">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="badge text-bg-primary">Câu {{ $i + 1 }}</span>
                    <span class="badge text-bg-light border">{{ $question->typeLabel() }}</span>
                    <span class="badge text-bg-light border">{{ $question->difficultyLabel() }}</span>
                    @if ($question->topic)
                        <span class="text-secondary small">{{ $question->topic->name }}</span>
                    @endif
                </div>

                <div class="mb-2" data-math>{!! $question->content !!}</div>

                @unless ($isLocked)
                    <div class="d-flex flex-wrap gap-2 align-items-end">
                        <form method="POST" action="{{ route('teacher.exams.questions.update', [$exam, $question]) }}"
                              class="d-flex gap-2 align-items-end">
                            @csrf
                            @method('PUT')
                            <div>
                                <label class="form-label small mb-0">Điểm</label>
                                <input type="number" name="points" step="0.25" min="0.25" max="100"
                                       value="{{ $question->pivot->points }}" class="form-control form-control-sm"
                                       style="width:6rem">
                            </div>
                            <div>
                                <label class="form-label small mb-0">Thứ tự</label>
                                <input type="number" name="sort_order" min="0" max="999"
                                       value="{{ $question->pivot->sort_order }}" class="form-control form-control-sm"
                                       style="width:5rem">
                            </div>
                            <button class="btn btn-sm btn-outline-primary">Lưu</button>
                        </form>

                        <form method="POST" action="{{ route('teacher.exams.questions.remove', [$exam, $question]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-x-lg me-1"></i>Gỡ khỏi đề
                            </button>
                        </form>
                    </div>
                @else
                    <div class="text-secondary small">{{ Score::format($question->pivot->points) }} điểm</div>
                @endunless
            </div>
        </div>
    @empty
        <div class="alert alert-light border">Đề chưa có câu hỏi.</div>
    @endforelse

    @unless ($isLocked)
        {{-- ---------------- Bốc ngẫu nhiên ---------------- --}}
        <div class="card border border-primary mt-4">
            <div class="card-body">
                <h4 class="h6 fw-bold mb-1">Bốc ngẫu nhiên từ ngân hàng</h4>
                <p class="text-secondary small">Chỉ lấy câu đã xuất bản của {{ $exam->grade->name }}.</p>

                <form method="POST" action="{{ route('teacher.exams.questions.random', $exam) }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <label class="form-label small mb-0" for="count">Số câu</label>
                            <input type="number" id="count" name="count" min="1" max="100"
                                   value="{{ old('count', 10) }}" class="form-control" required>
                        </div>
                        @foreach (['easy' => ['Dễ %', 30], 'medium' => ['TB %', 50], 'hard' => ['Khó %', 20]] as $key => [$label, $default])
                            <div class="col-6 col-md-3">
                                <label class="form-label small mb-0" for="ratio-{{ $key }}">{{ $label }}</label>
                                <input type="number" id="ratio-{{ $key }}" name="{{ $key }}" min="0" max="100"
                                       value="{{ old($key, $default) }}" class="form-control" required>
                            </div>
                        @endforeach

                        @if ($topics->isNotEmpty())
                            <div class="col-12">
                                <label class="form-label small mb-1">Giới hạn chủ đề (để trống = tất cả)</label>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($topics as $topic)
                                        <input type="checkbox" class="btn-check" name="topic_ids[]"
                                               id="rt-{{ $topic->id }}" value="{{ $topic->id }}">
                                        <label class="btn btn-sm btn-outline-secondary" for="rt-{{ $topic->id }}">
                                            {{ $topic->name }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <button class="btn btn-primary mt-3 btn-touch">
                        <i class="bi bi-shuffle me-1"></i>Bốc câu hỏi
                    </button>
                </form>
            </div>
        </div>

        {{-- ---------------- Chọn tay ---------------- --}}
        <div class="card border mt-3">
            <div class="card-body">
                <h4 class="h6 fw-bold mb-3">Chọn từ ngân hàng câu hỏi</h4>

                <form method="GET" class="row g-2 mb-3">
                    <div class="col-12 col-md-4">
                        <select name="topic_id" class="form-select form-select-sm" aria-label="Chủ đề">
                            <option value="">Mọi chủ đề</option>
                            @foreach ($topics as $topic)
                                <option value="{{ $topic->id }}" @selected(request('topic_id') == $topic->id)>{{ $topic->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <select name="difficulty" class="form-select form-select-sm" aria-label="Độ khó">
                            <option value="">Mọi độ khó</option>
                            @foreach (Question::DIFFICULTIES as $value => $label)
                                <option value="{{ $value }}" @selected(request('difficulty') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <select name="type" class="form-select form-select-sm" aria-label="Loại">
                            <option value="">Mọi loại</option>
                            @foreach (Question::TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <button class="btn btn-sm btn-outline-secondary w-100">Lọc</button>
                    </div>
                </form>

                @if ($candidates->isEmpty())
                    <p class="text-secondary small mb-0">Không còn câu hỏi phù hợp trong ngân hàng.</p>
                @else
                    <form method="POST" action="{{ route('teacher.exams.questions.add', $exam) }}">
                        @csrf
                        <div class="d-grid gap-2 mb-3">
                            @foreach ($candidates as $candidate)
                                <label class="border rounded-3 p-3 d-flex gap-3 align-items-start" for="cand-{{ $candidate->id }}">
                                    <input class="form-check-input mt-1" type="checkbox" name="question_ids[]"
                                           id="cand-{{ $candidate->id }}" value="{{ $candidate->id }}">
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex flex-wrap gap-1 mb-1">
                                            <span class="badge text-bg-light border">{{ $candidate->typeLabel() }}</span>
                                            <span class="badge text-bg-light border">{{ $candidate->difficultyLabel() }}</span>
                                            @if ($candidate->status !== 'published')
                                                <span class="badge text-bg-secondary">Nháp</span>
                                            @endif
                                        </div>
                                        <div data-math>{!! $candidate->content !!}</div>
                                        @if ($candidate->topic)
                                            <div class="text-secondary small">{{ $candidate->topic->name }}</div>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        <button class="btn btn-primary btn-touch">
                            <i class="bi bi-plus-lg me-1"></i>Thêm câu đã chọn
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endunless
@endsection
