@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Bản nháp AI — TOÁN AI')
@section('page_title', 'Bản nháp AI')

@php
    use App\Models\Question;
    $working = in_array($draft->status, ['pending', 'processing'], true);
@endphp

@if ($working)
    {{-- AI chạy nền: tự tải lại cho tới khi xong. --}}
    @push('head')<meta http-equiv="refresh" content="5">@endpush
@endif

@section('content')
    <a href="{{ route('teacher.ai.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> AI soạn bài
    </a>

    <div class="mt-2 mb-3">
        <h2 class="h5 fw-bold mb-1">
            {{ $draft->type === 'lesson' ? 'Bài học: ' . $draft->input['title'] : ($draft->input['count'] . ' câu hỏi') }}
        </h2>
        <div class="text-secondary small">
            {{ $topic?->chapter?->subject?->grade?->name }} · {{ $topic?->chapter?->name }} · {{ $topic?->name }}
        </div>
    </div>

    @if ($working)
        <div class="card border">
            <div class="card-body text-center p-5">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <p class="fw-semibold mb-1">AI đang soạn…</p>
                <p class="text-secondary small mb-0">Thường mất 20–60 giây. Trang tự cập nhật.</p>
                @if ($draft->created_at->lt(now()->subMinutes(3)))
                    <div class="alert alert-warning small mt-3 mb-0">
                        Chờ lâu bất thường — có thể hàng đợi chưa chạy. Người quản trị cần bật <code>php artisan queue:work</code>.
                    </div>
                @endif
            </div>
        </div>
    @elseif ($draft->status === 'failed')
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-1"></i>{{ $draft->error }}
            <a href="{{ route('teacher.ai.index') }}" class="alert-link ms-1">Thử lại</a>
        </div>
    @elseif ($draft->type === 'questions')
        @if (($draft->output['dropped'] ?? 0) > 0)
            <div class="alert alert-light border small">
                Đã bỏ {{ $draft->output['dropped'] }} câu AI tạo sai cấu trúc (thiếu đáp án, sai loại…).
            </div>
        @endif

        @foreach ($draft->items() as $i => $item)
            @php $status = $item['status'] ?? 'pending'; @endphp
            <div class="card border mb-3 {{ $status === 'accepted' ? 'border-success' : ($status === 'rejected' ? 'opacity-50' : '') }}">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                        <span class="badge text-bg-primary">Câu {{ $i + 1 }}</span>
                        <span class="badge text-bg-light border">{{ Question::TYPES[$item['type']] }}</span>
                        <span class="badge text-bg-light border">{{ Question::DIFFICULTIES[$item['difficulty']] }}</span>
                        @if ($status === 'accepted')
                            <span class="badge text-bg-success ms-auto">Đã thêm vào ngân hàng</span>
                        @elseif ($status === 'rejected')
                            <span class="badge text-bg-secondary ms-auto">Đã bỏ</span>
                        @endif
                    </div>

                    {{-- Nội dung AI chưa qua duyệt: escape, không render HTML. --}}
                    <div class="mb-2" data-math>{{ $item['content'] }}</div>

                    <div class="small" data-math>
                        @switch ($item['type'])
                            @case ('single_choice')
                            @case ('multiple_choice')
                                <ol type="A" class="mb-1">
                                    @foreach ($item['options'] as $k => $opt)
                                        <li class="{{ in_array($k, $item['correct'], true) ? 'text-success fw-semibold' : '' }}">
                                            {{ $opt }} @if (in_array($k, $item['correct'], true)) ✓ @endif
                                        </li>
                                    @endforeach
                                </ol>
                                @break
                            @case ('true_false')
                                <div class="text-success fw-semibold">Đáp án: {{ $item['correct'] ? 'Đúng' : 'Sai' }}</div>
                                @break
                            @case ('fill_blank')
                                <div class="text-success fw-semibold">
                                    Đáp án: {{ collect($item['blanks'])->map(fn ($b) => implode(' / ', $b))->implode(' ; ') }}
                                </div>
                                @break
                            @case ('short_answer')
                                <div class="text-success fw-semibold">Đáp án: {{ implode(' / ', $item['accepted']) }}</div>
                                @break
                            @default
                                <div class="text-secondary">Tự luận — không có đáp án cố định.</div>
                        @endswitch
                    </div>

                    @if (! empty($item['explanation']))
                        <div class="border-start border-3 ps-2 mt-2 small text-secondary" data-math>{{ $item['explanation'] }}</div>
                    @endif

                    @if ($status === 'pending')
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <form method="POST" action="{{ route('teacher.ai.accept', [$draft, $i]) }}">
                                @csrf <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Chấp nhận</button>
                            </form>
                            <a href="{{ route('teacher.ai.edit', [$draft, $i]) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil me-1"></i>Sửa
                            </a>
                            <form method="POST" action="{{ route('teacher.ai.regenerate', [$draft, $i]) }}">
                                @csrf <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Tạo lại</button>
                            </form>
                            <form method="POST" action="{{ route('teacher.ai.reject', [$draft, $i]) }}">
                                @csrf <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg me-1"></i>Xoá</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        @php $acceptedCount = collect($draft->items())->where('status', 'accepted')->count(); @endphp
        @if ($createdExam)
            <a href="{{ route('teacher.exams.edit', $createdExam) }}" class="btn btn-outline-primary mt-2">
                <i class="bi bi-file-earmark-text me-1"></i>Mở đề đã tạo
            </a>
        @elseif ($acceptedCount > 0)
            <form method="POST" action="{{ route('teacher.ai.create-exam', $draft) }}" class="mt-2">
                @csrf
                <button class="btn btn-primary btn-touch">
                    <i class="bi bi-file-earmark-plus me-1"></i>Tạo đề nháp từ {{ $acceptedCount }} câu đã chấp nhận
                </button>
                <div class="form-text">Chỉ các câu bạn đã chấp nhận mới vào đề. Đề ở trạng thái Nháp — bạn chỉnh rồi mới xuất bản.</div>
            </form>
        @endif
    @else
        {{-- Bài học: xem trước các phần rồi tạo bài NHÁP --}}
        @foreach ($draft->output['sections'] as $section)
            <div class="card border mb-2">
                <div class="card-body">
                    <div class="small text-secondary mb-1">{{ $sectionTypes[$section['type']] ?? $section['type'] }}</div>
                    <div class="fw-semibold mb-2">{{ $section['title'] }}</div>
                    {{-- Đã lọc qua HtmlSanitizer lúc nhận từ AI. --}}
                    <div data-math>{!! $section['content'] !!}</div>
                </div>
            </div>
        @endforeach

        @if (($draft->output['status'] ?? null) === 'pending')
            <form method="POST" action="{{ route('teacher.ai.create-lesson', $draft) }}" class="mt-3">
                @csrf
                <button class="btn btn-primary btn-lg btn-touch">
                    <i class="bi bi-journal-plus me-1"></i>Tạo bài học nháp từ nội dung này
                </button>
                <div class="form-text">Bài được tạo ở trạng thái Nháp — bạn chỉnh sửa rồi mới xuất bản cho học sinh.</div>
            </form>
        @elseif ($createdLesson)
            <a href="{{ route('teacher.lessons.edit', $createdLesson) }}" class="btn btn-outline-primary mt-3">
                Mở bài học đã tạo
            </a>
        @endif
    @endif
@endsection
