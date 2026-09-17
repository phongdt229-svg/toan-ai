@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Luyện tập — TOÁN AI')
@section('page_title', 'Luyện tập')

@section('content')
    @if ($weakTopics->isNotEmpty())
        <div class="card border-warning mb-4">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-2">
                    <i class="bi bi-lightning-charge text-warning me-1"></i>Nên ôn lại
                </h2>
                <p class="text-secondary small mb-3">
                    Những chủ đề bạn còn hay sai — luyện thêm ở đây sẽ hiệu quả nhất.
                </p>

                <div class="d-grid gap-2">
                    @foreach ($weakTopics as $mastery)
                        <form method="POST" action="{{ route('student.practice.start') }}">
                            @csrf
                            <input type="hidden" name="topic_id" value="{{ $mastery->topic_id }}">
                            <input type="hidden" name="limit" value="10">
                            <button class="btn btn-outline-warning w-100 d-flex align-items-center gap-2 btn-touch">
                                <span class="flex-grow-1 text-start">{{ $mastery->topic->name }}</span>
                                <span class="badge text-bg-warning text-dark">{{ $mastery->mastery_score }}%</span>
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <h2 class="h5 fw-bold mb-0">Chọn chủ đề</h2>

        <form method="GET" class="d-flex align-items-center gap-2">
            <label for="grade" class="form-label mb-0 small text-secondary">Lớp</label>
            <select id="grade" name="grade" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                @foreach ($grades as $g)
                    <option value="{{ $g->slug }}" @selected($g->id === $grade->id)>{{ $g->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if ($topics->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4 text-secondary">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                {{ $grade->name }} chưa có câu hỏi luyện tập nào.
            </div>
        </div>
    @else
        <div class="d-grid gap-2">
            @foreach ($topics as $topic)
                <div class="card border">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="bi bi-pencil-square text-primary"></i>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $topic->name }}</div>
                                <div class="text-secondary small">
                                    {{ $topic->chapter->name }} · {{ $topic->questions_count }} câu hỏi
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('student.practice.start') }}" class="row g-2">
                            @csrf
                            <input type="hidden" name="topic_id" value="{{ $topic->id }}">

                            <div class="col-6 col-sm-4">
                                <select name="difficulty" class="form-select form-select-sm"
                                        aria-label="Độ khó">
                                    <option value="">Mọi độ khó</option>
                                    <option value="easy">Dễ</option>
                                    <option value="medium">Trung bình</option>
                                    <option value="hard">Khó</option>
                                </select>
                            </div>

                            <div class="col-6 col-sm-3">
                                <select name="limit" class="form-select form-select-sm" aria-label="Số câu">
                                    <option value="5">5 câu</option>
                                    <option value="10" selected>10 câu</option>
                                    <option value="20">20 câu</option>
                                </select>
                            </div>

                            <div class="col-12 col-sm-5">
                                <button class="btn btn-primary btn-sm w-100 btn-touch">Bắt đầu luyện</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
