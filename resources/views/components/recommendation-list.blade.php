{{--
    Danh sách đề xuất học tập (§11).
    @param Collection<Recommendation> $recommendations
    @param bool $actionable  true = học sinh bấm được (dashboard HS); false = chỉ đọc (phụ huynh)
--}}
@php
    $icons = [
        'review_lesson' => ['bi-journal-text', 'Học bài'],
        'practice_topic' => ['bi-pencil-square', 'Luyện tập'],
        'take_exam' => ['bi-clipboard-check', 'Kiểm tra lại'],
    ];
    $service = app(\App\Services\Learning\RecommendationService::class);
@endphp

<div class="d-grid gap-2">
    @foreach ($recommendations as $rec)
        @php [$icon, $label] = $icons[$rec->type]; @endphp

        <div class="card border">
            <div class="card-body py-2 d-flex align-items-center gap-3">
                <div class="feature-card__icon mb-0 flex-shrink-0"><i class="bi {{ $icon }}"></i></div>
                <div class="flex-grow-1 min-w-0">
                    <div class="small text-secondary">{{ $label }}{{ $rec->topic ? ' · ' . $rec->topic->name : '' }}</div>
                    <div>{{ $rec->reason }}</div>
                </div>

                @if ($actionable)
                    @if ($rec->type === 'practice_topic')
                        <form method="POST" action="{{ route('student.practice.start') }}">
                            @csrf
                            <input type="hidden" name="topic_id" value="{{ $rec->target_id }}">
                            <input type="hidden" name="difficulty" value="{{ $rec->difficulty }}">
                            <input type="hidden" name="limit" value="10">
                            <button class="btn btn-sm btn-primary">Làm</button>
                        </form>
                    @elseif ($url = $service->urlFor($rec))
                        <a href="{{ $url }}" class="btn btn-sm btn-primary">Mở</a>
                    @endif
                @endif
            </div>
        </div>
    @endforeach
</div>
