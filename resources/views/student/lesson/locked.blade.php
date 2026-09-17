@extends('layouts.app', ['portal' => 'student'])

@section('title', $lesson->title . ' — TOÁN AI')
@section('page_title', $lesson->title)

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('student.learn.index') }}">Chương trình</a></li>
            <li class="breadcrumb-item">
                <a href="{{ route('student.learn.topic', $lesson->topic) }}">{{ $lesson->topic->name }}</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">{{ $lesson->title }}</li>
        </ol>
    </nav>

    <h1 class="h4 fw-bold mb-2">{{ $lesson->title }}</h1>

    @if ($preview)
        <div class="card border mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-book text-primary"></i>
                    <h2 class="h6 fw-bold mb-0">{{ $preview->title ?: $preview->typeLabel() }}</h2>
                </div>

                <div class="lesson-content position-relative" data-math
                     style="max-height:260px;overflow:hidden">
                    {!! $preview->content !!}
                    <div class="position-absolute bottom-0 start-0 end-0" style="height:90px;
                         background:linear-gradient(transparent, #fff)"></div>
                </div>
            </div>
        </div>
    @endif

    <div class="card border-warning">
        <div class="card-body text-center p-4">
            <i class="bi bi-lock text-warning" style="font-size:2.5rem"></i>

            <h2 class="h5 fw-bold mt-3 mb-2">Bài học này thuộc gói {{ \App\Models\Package::TIER_LABELS[$requiredTier] ?? $requiredTier }}</h2>
            <p class="text-secondary mb-4">
                Nâng cấp để mở toàn bộ bài học, bài tập và AI Tutor.
            </p>

            <a href="{{ route('packages.index') }}" class="btn btn-warning btn-touch">Xem các gói học</a>
        </div>
    </div>
@endsection
