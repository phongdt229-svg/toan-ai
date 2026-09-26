@extends('layouts.app', ['portal' => auth()->user()->isAdmin() ? 'admin' : (auth()->user()->isTeacher() ? 'teacher' : 'student')])

@php use App\Models\QaQuestion; @endphp

@section('title', 'Hỏi đáp — TOÁN AI')
@section('page_title', 'Hỏi đáp')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-secondary small mb-0" style="max-width:34rem">
            Chỗ hỏi bài công khai. Thầy cô và các bạn cùng trả lời — khác với AI Tutor ở chỗ
            em nghe được nhiều cách nghĩ.
        </p>
        <a href="{{ route('student.qa.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Đặt câu hỏi
        </a>
    </div>

    <form method="GET" class="filter-bar">
        <input name="q" class="form-control" style="max-width:280px" placeholder="Tìm trong câu hỏi" value="{{ $search }}">
        <select name="loc" class="form-select" style="max-width:190px" onchange="this.form.submit()">
            <option value="">Tất cả câu hỏi</option>
            <option value="chua-tra-loi" @selected($filter === 'chua-tra-loi')>Chưa có trả lời</option>
            <option value="da-giai" @selected($filter === 'da-giai')>Đã có lời giải</option>
            <option value="cua-toi" @selected($filter === 'cua-toi')>Câu hỏi của tôi</option>
        </select>
        <button class="btn btn-outline-primary">Tìm</button>
    </form>

    <div class="table-meta">{{ number_format($questions->total(), 0, ',', '.') }} câu hỏi</div>

    <div class="d-grid gap-2">
        @forelse ($questions as $question)
            <div class="card border">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <a href="{{ route('student.qa.show', $question) }}" class="fw-semibold text-decoration-none">
                                {{ $question->title }}
                            </a>
                            <div class="small text-secondary mt-1">
                                {{ $question->topic->chapter->name }} · {{ $question->topic->name }}
                            </div>
                            <div class="small text-secondary">
                                {{ $question->user->name }} · {{ $question->created_at->diffForHumans() }}
                            </div>
                        </div>

                        <div class="text-end flex-shrink-0">
                            @if ($question->isResolved())
                                <span class="badge text-bg-success">Đã có lời giải</span>
                            @elseif ($question->answers_count > 0)
                                <span class="badge text-bg-primary">{{ $question->answers_count }} trả lời</span>
                            @else
                                <span class="badge text-bg-light border">Chưa có trả lời</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="card border">
                <div class="card-body text-center text-secondary py-5">
                    @if ($search !== '' || $filter !== '')
                        Không có câu hỏi nào khớp. Thử bỏ bớt bộ lọc xem.
                    @else
                        Chưa có câu hỏi nào. Em hỏi câu đầu tiên nhé — không có câu hỏi nào là ngớ ngẩn cả.
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    <x-pagination :paginator="$questions" label="câu hỏi" />
@endsection
