@extends('layouts.app', ['portal' => auth()->user()->isTeacher() ? 'teacher' : 'student'])

@section('title', 'Đặt câu hỏi — TOÁN AI')
@section('page_title', 'Đặt câu hỏi')

@section('content')
    <a href="{{ route('student.qa.index') }}" class="small text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>Hỏi đáp
    </a>

    <form method="POST" action="{{ route('student.qa.store') }}" class="card border mt-2" style="max-width:44rem">
        @csrf
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="topic_id">Chủ đề <span class="text-danger">*</span></label>
                <select id="topic_id" name="topic_id" required
                        class="form-select @error('topic_id') is-invalid @enderror">
                    <option value="">— Chọn chủ đề em đang học —</option>
                    @foreach ($chapters as $chapter)
                        <optgroup label="{{ $chapter->name }}">
                            @foreach ($chapter->topics as $topic)
                                <option value="{{ $topic->id }}" @selected(old('topic_id') == $topic->id)>
                                    {{ $topic->name }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('topic_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Chọn đúng chủ đề thì bạn nào đang học phần đó sẽ thấy câu hỏi của em.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="title">Em vướng ở đâu? <span class="text-danger">*</span></label>
                <input id="title" name="title" maxlength="191" required
                       class="form-control @error('title') is-invalid @enderror"
                       value="{{ old('title') }}" placeholder="VD: Quy đồng mẫu số rồi nhưng cộng ra sai">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="body">Nội dung <span class="text-danger">*</span></label>
                <textarea id="body" name="body" rows="7" required maxlength="5000"
                          class="form-control @error('body') is-invalid @enderror"
                          placeholder="Chép đề bài, và nói rõ em làm tới bước nào thì bí.">{{ old('body') }}</textarea>
                @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">
                    Viết công thức giữa hai dấu <code>$</code> — ví dụ <code>$\frac{1}{2}$</code>.
                </div>
            </div>

            <div class="alert alert-light border small mb-3">
                <i class="bi bi-info-circle me-1"></i>
                Câu hỏi hiện công khai cho học sinh và thầy cô. Đừng ghi tên trường, số điện thoại
                hay thông tin cá nhân của em vào đây.
            </div>

            <button class="btn btn-primary btn-touch">Đăng câu hỏi</button>
        </div>
    </form>
@endsection
