{{-- Chọn chủ đề; lớp (grade_id) tự điền theo chủ đề để hai trường không lệch nhau. --}}
<label class="form-label" for="{{ $id }}">Chủ đề <span class="text-danger">*</span></label>
<select id="{{ $id }}" name="topic_id" class="form-select" required data-topic-select>
    <option value="">— Chọn chủ đề —</option>
    @foreach ($grades as $grade)
        @foreach ($grade->subjects as $subject)
            @foreach ($subject->chapters as $chapter)
                @if ($chapter->topics->isNotEmpty())
                    <optgroup label="{{ $grade->name }} · {{ $chapter->name }}">
                        @foreach ($chapter->topics as $topic)
                            <option value="{{ $topic->id }}" data-grade-id="{{ $grade->id }}"
                                    @selected(old('topic_id') == $topic->id)>{{ $topic->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
            @endforeach
        @endforeach
    @endforeach
</select>
<input type="hidden" name="grade_id" value="{{ old('grade_id') }}" data-grade-input>
