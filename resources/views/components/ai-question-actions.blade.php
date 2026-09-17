{{--
    Nút AI cạnh một câu hỏi.
    @param Question $question
    @param array<string> $modes   các chế độ muốn hiện (hint, explain, analyze_mistake, similar_exercise, check_answer)
    @param mixed $answer          đáp án học sinh (cho analyze_mistake)
    @param string|null $readFrom  selector khối câu hỏi để đọc đáp án đang chọn (cho check_answer khi đang làm)
--}}
@php
    $labels = [
        'hint' => ['bi-lightbulb', 'Gợi ý'],
        'check_answer' => ['bi-check2-circle', 'Kiểm tra'],
        'explain' => ['bi-chat-square-text', 'Giải thích'],
        'analyze_mistake' => ['bi-search', 'Em sai ở đâu?'],
        'similar_exercise' => ['bi-shuffle', 'Bài tương tự'],
    ];
@endphp

<div class="d-flex flex-wrap gap-2 mt-3">
    @foreach ($modes as $mode)
        <button type="button" class="btn btn-sm btn-outline-primary"
                data-ai-action="{{ $mode }}" data-question-id="{{ $question->id }}"
                @if ($mode === 'analyze_mistake' && isset($answer)) data-answer='@json($answer)' @endif
                @if ($mode === 'check_answer' && ! empty($readFrom)) data-read-from="{{ $readFrom }}" @endif>
            <i class="bi {{ $labels[$mode][0] }} me-1"></i>{{ $labels[$mode][1] }}
        </button>
    @endforeach
</div>
