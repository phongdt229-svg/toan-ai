@php
    $currentTopicId = old('topic_id', $lesson->topic_id);
@endphp

<div class="row g-3">
    <div class="col-12">
        <label for="title" class="form-label">Tiêu đề <span class="text-danger">*</span></label>
        <input type="text" id="title" name="title" value="{{ old('title', $lesson->title) }}"
               class="form-control form-control-lg @error('title') is-invalid @enderror" required>
        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="topic_id" class="form-label">Chủ đề <span class="text-danger">*</span></label>
        <select id="topic_id" name="topic_id" class="form-select @error('topic_id') is-invalid @enderror" required>
            <option value="">— Chọn chủ đề —</option>
            @foreach ($grades as $grade)
                @foreach ($grade->subjects as $subject)
                    @foreach ($subject->chapters as $chapter)
                        @if ($chapter->topics->isNotEmpty())
                            <optgroup label="{{ $grade->name }} · {{ $chapter->name }}">
                                @foreach ($chapter->topics as $topic)
                                    <option value="{{ $topic->id }}" @selected($currentTopicId == $topic->id)>
                                        {{ $topic->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                @endforeach
            @endforeach
        </select>
        @error('topic_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">
            Chưa có chủ đề phù hợp? Nhờ quản trị viên thêm vào cây chương trình.
        </div>
    </div>

    <div class="col-12">
        <label for="summary" class="form-label">Tóm tắt</label>
        <textarea id="summary" name="summary" rows="2" maxlength="500"
                  class="form-control @error('summary') is-invalid @enderror">{{ old('summary', $lesson->summary) }}</textarea>
        @error('summary') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-6 col-lg-3">
        <label for="difficulty" class="form-label">Độ khó</label>
        <select id="difficulty" name="difficulty" class="form-select">
            @foreach (['easy' => 'Dễ', 'medium' => 'Trung bình', 'hard' => 'Khó'] as $value => $label)
                <option value="{{ $value }}" @selected(old('difficulty', $lesson->difficulty) === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-6 col-lg-3">
        <label for="estimated_minutes" class="form-label">Thời lượng (phút)</label>
        <input type="number" id="estimated_minutes" name="estimated_minutes" min="1" max="600"
               value="{{ old('estimated_minutes', $lesson->estimated_minutes) }}"
               class="form-control @error('estimated_minutes') is-invalid @enderror" required>
        @error('estimated_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-6 col-lg-3">
        <label for="access_level" class="form-label">Gói truy cập</label>
        <select id="access_level" name="access_level" class="form-select">
            @foreach (['free' => 'Free', 'pro' => 'Pro', 'premium' => 'Premium'] as $value => $label)
                <option value="{{ $value }}" @selected(old('access_level', $lesson->access_level) === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-6 col-lg-3">
        <label for="sort_order" class="form-label">Thứ tự</label>
        <input type="number" id="sort_order" name="sort_order" min="0" max="9999"
               value="{{ old('sort_order', $lesson->sort_order) }}" class="form-control">
    </div>
</div>
