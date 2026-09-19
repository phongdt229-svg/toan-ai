@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Tìm kiếm — TOÁN AI')
@section('page_title', 'Tìm kiếm')

@section('content')
    <form method="GET" action="{{ route('teacher.search') }}" class="mb-4" style="max-width:32rem">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="search" name="q" class="form-control" value="{{ $q }}"
                   placeholder="Tìm bài học, câu hỏi, học sinh..." autofocus>
            <button class="btn btn-primary">Tìm</button>
        </div>
    </form>

    @if ($q === '')
        <p class="text-secondary">Gõ từ khoá để tìm trong bài học, câu hỏi và học sinh của bạn.</p>
    @elseif (mb_strlen($q) < 2)
        <p class="text-secondary">Gõ ít nhất 2 ký tự.</p>
    @elseif ($lessons->isEmpty() && $questions->isEmpty() && $students->isEmpty())
        <p class="text-secondary">Không tìm thấy kết quả nào cho "<strong>{{ $q }}</strong>".</p>
    @else
        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <h3 class="h6 fw-bold mb-2"><i class="bi bi-journal-text me-1"></i>Bài học ({{ $lessons->count() }})</h3>
                @forelse ($lessons as $lesson)
                    <a href="{{ route('teacher.lessons.edit', $lesson) }}" class="d-block card border p-2 mb-2 text-decoration-none">
                        <div class="fw-semibold small">{{ $lesson->title }}</div>
                        <span class="badge {{ $lesson->status === 'published' ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $lesson->status === 'published' ? 'Đã xuất bản' : 'Nháp' }}
                        </span>
                    </a>
                @empty
                    <p class="small text-secondary">Không có bài học phù hợp.</p>
                @endforelse
            </div>

            <div class="col-12 col-lg-4">
                <h3 class="h6 fw-bold mb-2"><i class="bi bi-question-circle me-1"></i>Câu hỏi ({{ $questions->count() }})</h3>
                @forelse ($questions as $question)
                    <a href="{{ route('teacher.questions.edit', $question) }}" class="d-block card border p-2 mb-2 text-decoration-none">
                        <div class="small">{{ \Illuminate\Support\Str::limit(strip_tags($question->content), 80) }}</div>
                        <span class="badge text-bg-light border">{{ $question->typeLabel() }}</span>
                    </a>
                @empty
                    <p class="small text-secondary">Không có câu hỏi phù hợp.</p>
                @endforelse
            </div>

            <div class="col-12 col-lg-4">
                <h3 class="h6 fw-bold mb-2"><i class="bi bi-mortarboard me-1"></i>Học sinh ({{ $students->count() }})</h3>
                @forelse ($students as $student)
                    <a href="{{ route('teacher.students.show', $student) }}" class="d-block card border p-2 mb-2 text-decoration-none">
                        <div class="fw-semibold small">{{ $student->name }}</div>
                        <div class="small text-secondary">{{ $student->email }}</div>
                    </a>
                @empty
                    <p class="small text-secondary">Không có học sinh phù hợp.</p>
                @endforelse
            </div>
        </div>
    @endif
@endsection
