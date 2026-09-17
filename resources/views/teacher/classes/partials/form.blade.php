<div class="mb-3">
    <label for="name" class="form-label">Tên lớp <span class="text-danger">*</span></label>
    <input type="text" id="name" name="name" value="{{ old('name', $class->name) }}" maxlength="150"
           placeholder="VD: 6A1 — Toán nâng cao"
           class="form-control form-control-lg @error('name') is-invalid @enderror" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="grade_id" class="form-label">Khối lớp <span class="text-danger">*</span></label>
    <select id="grade_id" name="grade_id" class="form-select @error('grade_id') is-invalid @enderror" required>
        @foreach ($grades as $grade)
            <option value="{{ $grade->id }}" @selected(old('grade_id', $class->grade_id) == $grade->id)>{{ $grade->name }}</option>
        @endforeach
    </select>
    <div class="form-text">Quyết định đề, bài học và câu hỏi nào hiện ra khi giao bài cho lớp.</div>
</div>

<div>
    <label for="description" class="form-label">Mô tả</label>
    <textarea id="description" name="description" rows="2" maxlength="500"
              class="form-control">{{ old('description', $class->description) }}</textarea>
</div>
