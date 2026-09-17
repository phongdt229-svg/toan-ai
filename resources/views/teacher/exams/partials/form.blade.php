@php
    use App\Models\Exam;

    $dt = fn ($v) => $v ? $v->format('Y-m-d\TH:i') : '';
@endphp

<div class="row g-3">
    <div class="col-12">
        <label for="title" class="form-label">Tên đề <span class="text-danger">*</span></label>
        <input type="text" id="title" name="title" value="{{ old('title', $exam->title) }}"
               class="form-control form-control-lg @error('title') is-invalid @enderror" required>
        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Mô tả / hướng dẫn</label>
        <textarea id="description" name="description" rows="2"
                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $exam->description) }}</textarea>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <label for="grade_id" class="form-label">Lớp <span class="text-danger">*</span></label>
        <select id="grade_id" name="grade_id" class="form-select @error('grade_id') is-invalid @enderror">
            @foreach ($grades as $grade)
                <option value="{{ $grade->id }}" @selected(old('grade_id', $exam->grade_id) == $grade->id)>{{ $grade->name }}</option>
            @endforeach
        </select>
        @error('grade_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-6 col-lg-3">
        <label for="type" class="form-label">Loại đề</label>
        <select id="type" name="type" class="form-select">
            @foreach (Exam::TYPES as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $exam->type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-6 col-lg-3">
        <label for="duration_minutes" class="form-label">Thời gian (phút)</label>
        <input type="number" id="duration_minutes" name="duration_minutes" min="1" max="300"
               value="{{ old('duration_minutes', $exam->duration_minutes) }}"
               class="form-control @error('duration_minutes') is-invalid @enderror" required>
        @error('duration_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-6 col-lg-3">
        <label for="difficulty" class="form-label">Độ khó</label>
        <select id="difficulty" name="difficulty" class="form-select">
            @foreach (Exam::DIFFICULTIES as $value => $label)
                <option value="{{ $value }}" @selected(old('difficulty', $exam->difficulty) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-6 col-lg-3">
        <label for="max_attempts" class="form-label">Số lượt làm tối đa</label>
        <input type="number" id="max_attempts" name="max_attempts" min="1" max="20"
               value="{{ old('max_attempts', $exam->max_attempts) }}"
               class="form-control @error('max_attempts') is-invalid @enderror" required>
    </div>

    <div class="col-6 col-lg-3">
        <label for="access_level" class="form-label">Gói truy cập</label>
        <select id="access_level" name="access_level" class="form-select">
            @foreach (['free' => 'Free', 'pro' => 'Pro', 'premium' => 'Premium'] as $value => $label)
                <option value="{{ $value }}" @selected(old('access_level', $exam->access_level) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <label for="available_from" class="form-label">Mở lúc</label>
        <input type="datetime-local" id="available_from" name="available_from"
               value="{{ old('available_from', $dt($exam->available_from)) }}"
               class="form-control @error('available_from') is-invalid @enderror">
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <label for="available_to" class="form-label">Đóng lúc</label>
        <input type="datetime-local" id="available_to" name="available_to"
               value="{{ old('available_to', $dt($exam->available_to)) }}"
               class="form-control @error('available_to') is-invalid @enderror">
        @error('available_to') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="d-flex flex-column flex-md-row gap-2 gap-md-4">
            @foreach ([
                'shuffle_questions' => 'Xáo thứ tự câu hỏi',
                'shuffle_options' => 'Xáo thứ tự lựa chọn',
                'show_answers_after_submit' => 'Cho xem đáp án sau khi nộp',
            ] as $field => $label)
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="{{ $field }}"
                           name="{{ $field }}" value="1" @checked(old($field, $exam->{$field}))>
                    <label class="form-check-label" for="{{ $field }}">{{ $label }}</label>
                </div>
            @endforeach
        </div>
        <div class="form-text">
            Nếu có giờ đóng đề, đáp án chỉ hiện sau giờ đóng — tránh học sinh làm trước chuyền đáp án.
        </div>
    </div>
</div>
