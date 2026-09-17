@php
    use App\Models\Question;

    $name = "answers[{$question->id}]";
    $prev = $answers[$question->id] ?? null;
    $readonly = $readonly ?? false;
@endphp

@switch ($question->type)
    @case (Question::TYPE_SINGLE_CHOICE)
        <div class="d-grid gap-2">
            @foreach ($question->options as $option)
                <div class="form-check border rounded-3 p-3 ps-5">
                    <input class="form-check-input" type="radio" name="{{ $name }}"
                           id="opt-{{ $option->id }}" value="{{ $option->id }}"
                           @checked((string) $prev === (string) $option->id) @disabled($readonly)>
                    <label class="form-check-label w-100" for="opt-{{ $option->id }}" data-math>
                        {!! $option->content !!}
                    </label>
                </div>
            @endforeach
        </div>
        @break

    @case (Question::TYPE_MULTIPLE_CHOICE)
        <p class="text-secondary small mb-2">Có thể có nhiều đáp án đúng.</p>
        <div class="d-grid gap-2">
            @foreach ($question->options as $option)
                <div class="form-check border rounded-3 p-3 ps-5">
                    <input class="form-check-input" type="checkbox" name="{{ $name }}[]"
                           id="opt-{{ $option->id }}" value="{{ $option->id }}"
                           @checked(in_array((string) $option->id, array_map('strval', (array) $prev), true))
                           @disabled($readonly)>
                    <label class="form-check-label w-100" for="opt-{{ $option->id }}" data-math>
                        {!! $option->content !!}
                    </label>
                </div>
            @endforeach
        </div>
        @break

    @case (Question::TYPE_TRUE_FALSE)
        <div class="row g-2">
            @foreach (['1' => 'Đúng', '0' => 'Sai'] as $value => $label)
                <div class="col-6">
                    <input type="radio" class="btn-check" name="{{ $name }}"
                           id="tf-{{ $question->id }}-{{ $value }}" value="{{ $value }}"
                           @checked((string) $prev === $value) @disabled($readonly)>
                    <label class="btn btn-outline-primary w-100 btn-touch"
                           for="tf-{{ $question->id }}-{{ $value }}">{{ $label }}</label>
                </div>
            @endforeach
        </div>
        @break

    @case (Question::TYPE_FILL_BLANK)
        @php $blankCount = count($question->correct_answer['blanks'] ?? []); @endphp
        <div class="row g-2">
            @for ($b = 0; $b < $blankCount; $b++)
                <div class="col-12 col-sm-6">
                    <label class="form-label small text-secondary" for="blank-{{ $question->id }}-{{ $b }}">
                        Chỗ trống {{ $b + 1 }}
                    </label>
                    <input type="text" class="form-control" id="blank-{{ $question->id }}-{{ $b }}"
                           name="{{ $name }}[]" value="{{ ((array) $prev)[$b] ?? '' }}"
                           autocomplete="off" @disabled($readonly)>
                </div>
            @endfor
        </div>
        @break

    @case (Question::TYPE_SHORT_ANSWER)
        <input type="text" class="form-control form-control-lg" name="{{ $name }}"
               value="{{ is_array($prev) ? ($prev[0] ?? '') : $prev }}"
               placeholder="Nhập đáp án" autocomplete="off" @disabled($readonly)>
        @break

    @case (Question::TYPE_ESSAY)
        <textarea class="form-control" name="{{ $name }}" rows="5"
                  placeholder="Trình bày lời giải" @disabled($readonly)>{{ is_array($prev) ? ($prev[0] ?? '') : $prev }}</textarea>
        <div class="form-text">Câu tự luận sẽ do giáo viên chấm.</div>
        @break
@endswitch
