@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Kiểm tra cuối buổi — TOÁN AI')
@section('page_title', 'Kiểm tra cuối buổi')

@section('content')
    <a href="{{ route('student.path.show') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Lộ trình
    </a>

    <h2 class="h5 fw-bold mt-2 mb-1">Kiểm tra cuối buổi {{ $session->session_no }}</h2>
    <p class="text-secondary small mb-3">
        {{ $questions->count() }} câu về các chủ đề vừa học. Đạt từ {{ \App\Services\Learning\LearningPathService::QUIZ_PASS_PERCENT }}% là hoàn thành buổi;
        chưa đạt thì buổi sau có thêm phần ôn đúng chỗ còn sai.
    </p>

    <form method="POST" action="{{ route('student.path.quiz.submit', $session) }}" onsubmit="return confirm('Nộp bài kiểm tra cuối buổi?')">
        @csrf

        @foreach ($questions as $i => $question)
            <div class="card border mb-3">
                <div class="card-body">
                    <span class="badge text-bg-primary mb-2">Câu {{ $i + 1 }}</span>
                    <div class="mb-3" data-math>{!! $question->content !!}</div>
                    @include('student.practice.partials.input', ['question' => $question, 'answers' => []])
                </div>
            </div>
        @endforeach

        <button class="btn btn-primary btn-lg w-100 btn-touch"><i class="bi bi-send me-1"></i>Nộp bài</button>
    </form>
@endsection
