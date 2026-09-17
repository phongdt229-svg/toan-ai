{{-- Một mục lộ trình: bài học / luyện tập / đề. --}}
@php
    $icons = ['lesson' => 'bi-journal-text', 'practice' => 'bi-pencil-square', 'exam' => 'bi-clipboard-check'];
    $url = $service->targetUrl($item);
@endphp

<div class="d-flex align-items-center gap-2 py-2 {{ $item->isDone() ? 'text-secondary' : '' }}">
    <i class="bi {{ $item->isDone() ? 'bi-check-circle-fill text-success' : $icons[$item->item_type] . ' text-primary' }} fs-5"></i>

    <div class="flex-grow-1 min-w-0">
        <div class="{{ $item->isDone() ? 'text-decoration-line-through' : '' }}">{{ $item->title }}</div>
        @if ($item->origin === 'review')
            <span class="badge text-bg-warning text-dark">Ôn tập thêm</span>
        @endif
        @if ($item->item_type === 'practice' && ! $item->isDone())
            <div class="small text-secondary">Làm ít nhất {{ \App\Services\Learning\LearningPathService::PRACTICE_ATTEMPTS_TO_COMPLETE }} câu để hoàn thành</div>
        @endif
    </div>

    @if (! $item->isDone() && ($actionable ?? true))
        @if ($item->item_type === 'practice')
            <form method="POST" action="{{ route('student.practice.start') }}">
                @csrf
                <input type="hidden" name="topic_id" value="{{ $item->topic_id }}">
                @if ($item->difficulty)<input type="hidden" name="difficulty" value="{{ $item->difficulty }}">@endif
                <input type="hidden" name="limit" value="10">
                <button class="btn btn-sm btn-primary">Luyện</button>
            </form>
        @elseif ($url)
            <a href="{{ $url }}" class="btn btn-sm btn-primary">Mở</a>
        @endif
    @endif
</div>
