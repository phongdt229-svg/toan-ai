@extends('layouts.app', ['portal' => 'student'])

@section('title', 'AI Tutor — TOÁN AI')
@section('page_title', 'AI Tutor')

@php
    $modeIcons = [
        'chat' => 'bi-chat-dots', 'hint' => 'bi-lightbulb', 'explain' => 'bi-chat-square-text',
        'check_answer' => 'bi-check2-circle', 'similar_exercise' => 'bi-shuffle', 'analyze_mistake' => 'bi-search',
    ];
@endphp

@section('content')
    <div class="card border-primary mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <i class="bi bi-robot text-primary" style="font-size:2.5rem"></i>
            <div class="flex-grow-1">
                <h2 class="h5 fw-bold mb-1">Hỏi AI về bài Toán</h2>
                <p class="text-secondary small mb-0">
                    AI gợi ý để em tự tìm ra lời giải. Trong bài luyện tập và trang kết quả còn có nút
                    <strong>Gợi ý</strong>, <strong>Giải thích</strong>, <strong>Em sai ở đâu?</strong> cạnh từng câu.
                </p>
            </div>
            <div class="text-end">
                @if ($usage['limit'] === null)
                    <span class="badge text-bg-light border">Không giới hạn lượt</span>
                @else
                    <div class="fw-bold fs-4">{{ $usage['remaining'] }}<span class="fs-6 text-secondary">/{{ $usage['limit'] }}</span></div>
                    <div class="small text-secondary">lượt còn lại hôm nay</div>
                @endif
            </div>
        </div>
    </div>

    @if ($examInProgress)
        <div class="alert alert-warning">
            <i class="bi bi-lock me-1"></i>AI Tutor tạm khoá vì em đang làm một bài kiểm tra.
        </div>
    @else
        <button type="button" class="btn btn-primary btn-lg w-100 btn-touch mb-4"
                data-bs-toggle="offcanvas" data-bs-target="#ai-tutor-panel">
            <i class="bi bi-chat-dots me-1"></i>Bắt đầu hỏi
        </button>
    @endif

    <h3 class="h6 fw-bold mb-2">Đã hỏi gần đây</h3>

    @forelse ($conversations as $c)
        <div class="card border mb-2">
            <div class="card-body py-2 d-flex align-items-center gap-2">
                <i class="bi {{ $modeIcons[$c->mode] ?? 'bi-chat' }} text-primary"></i>
                <div class="flex-grow-1 min-w-0">
                    <div class="text-truncate">{{ $c->title ?: $c->modeLabel() }}</div>
                    <div class="text-secondary small">{{ $c->modeLabel() }} · {{ $c->last_message_at?->diffForHumans() }}</div>
                </div>
                <a href="#" class="btn btn-sm btn-outline-secondary" data-ai-conversation="{{ $c->id }}">Xem lại</a>
            </div>
        </div>
    @empty
        <p class="text-secondary small">Em chưa hỏi AI lần nào.</p>
    @endforelse
@endsection
