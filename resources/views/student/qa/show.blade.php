@extends('layouts.app', ['portal' => auth()->user()->isTeacher() ? 'teacher' : 'student'])

@php
    use App\Models\QaQuestion;

    $me = auth()->user();
    $canModerate = $me->can('moderate', QaQuestion::class);
@endphp

@section('title', $question->title . ' — Hỏi đáp TOÁN AI')
@section('page_title', 'Hỏi đáp')

@section('content')
    <a href="{{ route('student.qa.index') }}" class="small text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>Hỏi đáp
    </a>

    <div class="card border mt-2 mb-4 @if ($question->isHidden()) border-warning @endif">
        <div class="card-body">
            @if ($question->isHidden())
                <div class="alert alert-warning small">
                    <i class="bi bi-eye-slash me-1"></i>Câu hỏi này đang ẩn, người khác không thấy.
                </div>
            @endif

            <h1 class="h5 fw-bold">{{ $question->title }}</h1>
            <div class="small text-secondary mb-3">
                {{ $question->topic->chapter->name }} · {{ $question->topic->name }} —
                {{ $question->user->name }}, {{ $question->created_at->diffForHumans() }}
            </div>

            {{-- Nội dung đã lọc HTML lúc lưu (QaService), hiển thị nguyên văn để giữ công thức. --}}
            <div data-math class="qa-body">{!! nl2br($question->body) !!}</div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                @can('report', $question)
                    <form method="POST" action="{{ route('student.qa.report', ['cau-hoi', $question->id]) }}"
                          data-confirm="Báo nội dung này cho thầy cô?" data-confirm-ok="Báo xấu">
                        @csrf
                        <button class="btn btn-sm btn-link text-secondary text-decoration-none p-0">
                            <i class="bi bi-flag me-1"></i>Báo xấu
                        </button>
                    </form>
                @endcan

                @if ($canModerate)
                    <form method="POST" action="{{ route('student.qa.moderate', ['cau-hoi', $question->id]) }}">
                        @csrf
                        <input type="hidden" name="hide" value="{{ $question->isHidden() ? 0 : 1 }}">
                        <button class="btn btn-sm btn-outline-secondary">
                            {{ $question->isHidden() ? 'Hiện lại' : 'Ẩn câu hỏi' }}
                        </button>
                    </form>
                    @if ($question->reports_count > 0)
                        <span class="badge text-bg-warning align-self-center">{{ $question->reports_count }} lượt báo xấu</span>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <h2 class="h6 fw-bold mb-2">
        {{ $answers->count() }} câu trả lời
    </h2>

    <div class="d-grid gap-2 mb-4">
        @forelse ($answers as $answer)
            <div class="card border @if ($answer->id === $question->best_answer_id) border-success @endif">
                <div class="card-body py-3">
                    @if ($answer->id === $question->best_answer_id)
                        <div class="small text-success fw-semibold mb-2">
                            <i class="bi bi-check-circle-fill me-1"></i>Lời giải được chọn
                        </div>
                    @endif

                    @if ($answer->isHidden())
                        <div class="alert alert-warning small py-1 px-2">Câu trả lời này đang ẩn.</div>
                    @endif

                    <div data-math class="qa-body">{!! nl2br($answer->body) !!}</div>

                    <div class="small text-secondary mt-2">
                        {{ $answer->user->name }} · {{ $answer->created_at->diffForHumans() }}
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @can('accept', $question)
                            @if ($answer->id !== $question->best_answer_id && ! $answer->isHidden())
                                <form method="POST" action="{{ route('student.qa.accept', [$question, $answer]) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">Chọn làm lời giải</button>
                                </form>
                            @endif
                        @endcan

                        @can('report', $answer)
                            <form method="POST" action="{{ route('student.qa.report', ['tra-loi', $answer->id]) }}"
                                  data-confirm="Báo câu trả lời này cho thầy cô?" data-confirm-ok="Báo xấu">
                                @csrf
                                <button class="btn btn-sm btn-link text-secondary text-decoration-none p-0">
                                    <i class="bi bi-flag me-1"></i>Báo xấu
                                </button>
                            </form>
                        @endcan

                        @if ($canModerate)
                            <form method="POST" action="{{ route('student.qa.moderate', ['tra-loi', $answer->id]) }}">
                                @csrf
                                <input type="hidden" name="hide" value="{{ $answer->isHidden() ? 0 : 1 }}">
                                <button class="btn btn-sm btn-outline-secondary">
                                    {{ $answer->isHidden() ? 'Hiện lại' : 'Ẩn' }}
                                </button>
                            </form>
                            @if ($answer->reports_count > 0)
                                <span class="badge text-bg-warning align-self-center">{{ $answer->reports_count }} báo xấu</span>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="card border">
                <div class="card-body text-secondary small">
                    Chưa ai trả lời. Em biết câu này thì giúp bạn một tay nhé.
                </div>
            </div>
        @endforelse
    </div>

    @can('answer', $question)
        <form method="POST" action="{{ route('student.qa.answer', $question) }}" class="card border" style="max-width:44rem">
            @csrf
            <div class="card-body">
                <label class="form-label fw-semibold" for="body">Trả lời</label>
                <textarea id="body" name="body" rows="5" required maxlength="5000"
                          class="form-control @error('body') is-invalid @enderror"
                          placeholder="Giải thích cách làm để bạn hiểu, đừng chỉ ghi đáp án.">{{ old('body') }}</textarea>
                @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <button class="btn btn-primary btn-touch mt-3">Gửi câu trả lời</button>
            </div>
        </form>
    @endcan
@endsection
