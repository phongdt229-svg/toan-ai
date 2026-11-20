@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Thi thử — Math AI')
@section('page_title', 'Thi thử')

@section('content')
    <p class="text-secondary small">
        Đề thi thử theo cấu trúc kỳ thi thật, bấm giờ như phòng thi. Làm xong em có điểm thang 10,
        biết mình đứng thứ mấy giữa các bạn đã thi và chủ đề nào cần ôn thêm.
    </p>

    @if ($exams->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4 text-secondary">
                <i class="bi bi-mortarboard fs-2 d-block mb-2"></i>
                Chưa có đề thi thử nào đang mở. Thầy cô sẽ đăng đề trước mỗi đợt thi thử.
            </div>
        </div>
    @else
        <div class="d-grid gap-2">
            @foreach ($exams as $exam)
                @php
                    $left = max(0, $exam->max_attempts - $exam->my_attempts_count);
                    $total = (float) $exam->total_points;
                @endphp
                <a href="{{ route('student.exams.show', $exam) }}" class="card border text-decoration-none text-body" data-mock-exam="{{ $exam->id }}">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                            <span class="badge {{ $exam->mock_track === $preferred ? 'text-bg-primary' : 'text-bg-light border' }}">
                                {{ \App\Models\Exam::MOCK_TRACKS[$exam->mock_track] ?? 'Thi thử' }}
                            </span>
                            @if ($locked[$exam->id])
                                <span class="badge text-bg-warning"><i class="bi bi-lock-fill"></i> Gói {{ ucfirst($exam->access_level) }}</span>
                            @endif
                            @if ($inProgress->has($exam->id))
                                <span class="badge text-bg-info">Đang làm dở</span>
                            @endif
                        </div>
                        <div class="fw-semibold">{{ $exam->title }}</div>
                        <div class="small text-secondary">
                            {{ $exam->duration_minutes }} phút · {{ $exam->total_questions }} câu · còn {{ $left }} lượt
                            @if ($exam->my_best_score !== null && $total > 0)
                                · điểm cao nhất của em: <strong class="text-body">{{ rtrim(rtrim(number_format($mockService->scoreOutOfTen((float) $exam->my_best_score, $total), 2, ',', ''), '0'), ',') }}/10</strong>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
