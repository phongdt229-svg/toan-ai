@php
    use App\Models\Question;

    $type = old('type', $question->type);
    $existingOptions = old('options') ?: $question->options
        ->map(fn ($o) => ['content' => $o->content])->all();
    $correctIndexes = old('correct_options') ?: $question->options
        ->values()
        ->filter(fn ($o) => $o->is_correct)
        ->keys()
        ->map(fn ($k) => (string) $k)
        ->all();

    if (empty($existingOptions)) {
        $existingOptions = [['content' => ''], ['content' => '']];
    }

    $blanks = old('blanks') ?: collect($question->correct_answer['blanks'] ?? [])
        ->map(fn ($alts) => implode(' | ', (array) $alts))->all();
    if (empty($blanks)) {
        $blanks = [''];
    }

    $accepted = old('accepted') ?: implode(' | ', $question->correct_answer['accepted'] ?? []);
    $tfValue = old('true_false_value', isset($question->correct_answer['value'])
        ? ($question->correct_answer['value'] ? '1' : '0')
        : null);
@endphp

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <label for="type" class="form-label">Loại câu hỏi <span class="text-danger">*</span></label>
        <select id="type" name="type" class="form-select @error('type') is-invalid @enderror"
                required data-question-type>
            @foreach (Question::TYPES as $value => $label)
                <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 col-lg-6">
        <label for="topic_id" class="form-label">Chủ đề</label>
        <select id="topic_id" name="topic_id" class="form-select @error('topic_id') is-invalid @enderror">
            <option value="">— Chưa gán —</option>
            @foreach ($grades as $grade)
                @foreach ($grade->subjects as $subject)
                    @foreach ($subject->chapters as $chapter)
                        @if ($chapter->topics->isNotEmpty())
                            <optgroup label="{{ $grade->name }} · {{ $chapter->name }}">
                                @foreach ($chapter->topics as $topic)
                                    <option value="{{ $topic->id }}"
                                            data-grade-id="{{ $grade->id }}"
                                            @selected(old('topic_id', $question->topic_id) == $topic->id)>
                                        {{ $topic->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                @endforeach
            @endforeach
        </select>
        <div class="form-text">Không gán chủ đề thì câu hỏi không xuất hiện trong luyện tập.</div>
    </div>

    <div class="col-12 col-sm-4">
        <label for="grade_id" class="form-label">Lớp <span class="text-danger">*</span></label>
        <select id="grade_id" name="grade_id" class="form-select @error('grade_id') is-invalid @enderror" required>
            @foreach ($grades as $grade)
                <option value="{{ $grade->id }}" @selected(old('grade_id', $question->grade_id) == $grade->id)>
                    {{ $grade->name }}
                </option>
            @endforeach
        </select>
        @error('grade_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-6 col-sm-4">
        <label for="difficulty" class="form-label">Độ khó</label>
        <select id="difficulty" name="difficulty" class="form-select">
            @foreach (Question::DIFFICULTIES as $value => $label)
                <option value="{{ $value }}" @selected(old('difficulty', $question->difficulty) === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-6 col-sm-4">
        <label for="points" class="form-label">Điểm</label>
        <input type="number" id="points" name="points" step="0.25" min="0.25" max="100"
               value="{{ old('points', $question->points ?? 1) }}"
               class="form-control @error('points') is-invalid @enderror" required>
        @error('points') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="content" class="form-label">Nội dung câu hỏi <span class="text-danger">*</span></label>
        <textarea id="content" name="content" rows="4" required data-math-editor
                  class="form-control font-monospace @error('content') is-invalid @enderror"
                  placeholder="Tính $\frac{1}{2} + \frac{1}{3}$">{{ old('content', $question->content) }}</textarea>
        @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-preview-toggle>Xem trước</button>
        <div class="border rounded p-3 mt-2 d-none bg-light" data-preview-target data-math></div>
    </div>
</div>

{{-- Khối đáp án — hiện/ẩn theo loại câu hỏi, xử lý bằng JS ở cuối trang. --}}
<hr class="my-4">
<h3 class="h6 fw-bold mb-3">Đáp án</h3>

<div data-answer-block="choice" class="mb-3">
    <p class="text-secondary small">Tick vào ô bên trái để đánh dấu đáp án đúng.</p>

    <div id="option-rows" class="d-grid gap-2">
        @foreach ($existingOptions as $i => $option)
            <div class="input-group" data-option-row>
                <div class="input-group-text">
                    <input class="form-check-input mt-0" type="checkbox" name="correct_options[]"
                           value="{{ $i }}" aria-label="Đáp án đúng"
                           @checked(in_array((string) $i, array_map('strval', $correctIndexes), true))>
                </div>
                <input type="text" class="form-control" name="options[{{ $i }}][content]"
                       value="{{ $option['content'] ?? '' }}" placeholder="Nội dung lựa chọn {{ $i + 1 }}">
                <button type="button" class="btn btn-outline-danger" data-remove-option>
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        @endforeach
    </div>

    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="add-option">
        <i class="bi bi-plus-lg me-1"></i>Thêm lựa chọn
    </button>

    @error('options') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    @error('correct_options') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
</div>

<div data-answer-block="true_false" class="mb-3">
    <div class="row g-2" style="max-width:20rem">
        @foreach (['1' => 'Đúng', '0' => 'Sai'] as $value => $label)
            <div class="col-6">
                <input type="radio" class="btn-check" name="true_false_value"
                       id="tf-{{ $value }}" value="{{ $value }}" @checked($tfValue === $value)>
                <label class="btn btn-outline-primary w-100 btn-touch" for="tf-{{ $value }}">{{ $label }}</label>
            </div>
        @endforeach
    </div>
    @error('true_false_value') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
</div>

<div data-answer-block="fill_blank" class="mb-3">
    <p class="text-secondary small">
        Mỗi dòng là một chỗ trống. Nhiều cách viết cùng đáp án thì cách nhau bằng <code>|</code>,
        ví dụ <code>1/2 | 0,5</code>.
    </p>

    <div id="blank-rows" class="d-grid gap-2">
        @foreach ($blanks as $i => $blank)
            <div class="input-group" data-blank-row>
                <span class="input-group-text">Chỗ {{ $i + 1 }}</span>
                <input type="text" class="form-control" name="blanks[]" value="{{ $blank }}">
                <button type="button" class="btn btn-outline-danger" data-remove-blank>
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        @endforeach
    </div>

    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="add-blank">
        <i class="bi bi-plus-lg me-1"></i>Thêm chỗ trống
    </button>

    @error('blanks') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
</div>

<div data-answer-block="short_answer" class="mb-3">
    <label for="accepted" class="form-label">Đáp án chấp nhận được</label>
    <input type="text" id="accepted" name="accepted" value="{{ $accepted }}"
           class="form-control @error('accepted') is-invalid @enderror"
           placeholder="5/6 | 5 / 6">
    <div class="form-text">Cách nhau bằng <code>|</code>. Hệ thống tự bỏ qua hoa/thường và khoảng trắng.</div>
    @error('accepted') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
</div>

<div data-answer-block="essay" class="mb-3">
    <div class="alert alert-info small mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Câu tự luận không chấm tự động được — giáo viên sẽ chấm tay sau khi học sinh nộp.
    </div>
</div>

<hr class="my-4">

<div class="mb-3">
    <label for="explanation" class="form-label">Giải thích đáp án</label>
    <textarea id="explanation" name="explanation" rows="3"
              class="form-control @error('explanation') is-invalid @enderror"
              placeholder="Hiện cho học sinh sau khi nộp bài">{{ old('explanation', $question->explanation) }}</textarea>
    @error('explanation') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

@if (old('source') === 'ai')
    <input type="hidden" name="source" value="ai">
    <input type="hidden" name="ai_draft_id" value="{{ old('ai_draft_id') }}">
    <input type="hidden" name="ai_item_index" value="{{ old('ai_item_index') }}">
    <div class="alert alert-info small"><i class="bi bi-robot me-1"></i>Câu hỏi do AI soạn — kiểm tra kỹ đáp án trước khi lưu.</div>
@endif

<div class="mb-3" style="max-width:20rem">
    <label for="status" class="form-label">Trạng thái</label>
    <select id="status" name="status" class="form-select">
        <option value="draft" @selected(old('status', $question->status) === 'draft')>Nháp</option>
        <option value="published" @selected(old('status', $question->status) === 'published')>Xuất bản</option>
    </select>
    <div class="form-text">Chỉ câu đã xuất bản mới vào bộ luyện tập của học sinh.</div>
</div>
