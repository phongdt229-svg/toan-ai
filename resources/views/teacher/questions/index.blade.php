@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Ngân hàng câu hỏi — TOÁN AI')
@section('page_title', 'Ngân hàng câu hỏi')

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <h2 class="h5 fw-bold mb-0">Câu hỏi của tôi</h2>

        <div class="d-flex gap-2">
            <a href="{{ route('teacher.questions.import') }}" class="btn btn-outline-primary">
                <i class="bi bi-upload me-1"></i>Nhập CSV
            </a>
            <a href="{{ route('teacher.questions.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Tạo câu hỏi
            </a>
        </div>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-12 col-lg">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control"
                   placeholder="Tìm trong nội dung câu hỏi">
        </div>
        <div class="col-6 col-lg-auto">
            <select name="type" class="form-select">
                <option value="">Mọi loại</option>
                @foreach (\App\Models\Question::TYPES as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-auto">
            <select name="difficulty" class="form-select">
                <option value="">Mọi độ khó</option>
                @foreach (\App\Models\Question::DIFFICULTIES as $value => $label)
                    <option value="{{ $value }}" @selected(request('difficulty') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-auto">
            <select name="status" class="form-select">
                <option value="">Mọi trạng thái</option>
                <option value="draft" @selected(request('status') === 'draft')>Nháp</option>
                <option value="published" @selected(request('status') === 'published')>Đã xuất bản</option>
            </select>
        </div>
        <div class="col-6 col-lg-auto">
            <button class="btn btn-outline-secondary w-100">Lọc</button>
        </div>
    </form>

    @if ($questions->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4 text-secondary">
                <i class="bi bi-question-circle fs-2 d-block mb-2"></i>
                Chưa có câu hỏi nào. Tạo mới hoặc nhập từ tệp CSV.
            </div>
        </div>
    @else
        <div class="d-grid gap-2">
            @foreach ($questions as $question)
                <div class="card border">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                            <span class="badge text-bg-primary">{{ $question->typeLabel() }}</span>
                            <span class="badge text-bg-light border">{{ $question->difficultyLabel() }}</span>
                            <span class="badge text-bg-light border">{{ $question->points }} điểm</span>

                            @if ($question->status === 'published')
                                <span class="badge text-bg-success">Đã xuất bản</span>
                            @else
                                <span class="badge text-bg-secondary">Nháp</span>
                            @endif

                            @if ($question->source === 'ai')
                                <span class="badge text-bg-info">AI soạn</span>
                            @endif
                        </div>

                        <div class="mb-2" data-math>{!! $question->content !!}</div>

                        <div class="text-secondary small mb-3">
                            {{ $question->grade->name }}
                            @if ($question->topic)
                                · {{ $question->topic->chapter->name }} · {{ $question->topic->name }}
                            @else
                                · <span class="text-warning">Chưa gán chủ đề</span>
                            @endif
                        </div>

                        <div class="d-flex gap-2">
                            <a href="{{ route('teacher.questions.edit', $question) }}"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil me-1"></i>Sửa
                            </a>

                            <form method="POST" action="{{ route('teacher.questions.destroy', $question) }}"
                                  data-confirm="Xoá câu hỏi này?" data-confirm-ok="Xoá">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash me-1"></i>Xoá
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <x-pagination :paginator="$questions" label="câu hỏi" class="mt-3" />
    @endif
@endsection
