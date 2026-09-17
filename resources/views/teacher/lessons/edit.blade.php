@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Sửa: ' . $lesson->title . ' — TOÁN AI')
@section('page_title', 'Sửa bài học')

@section('content')
    <a href="{{ route('teacher.lessons.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Danh sách bài học
    </a>

    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-2 mb-3">
        <h2 class="h5 fw-bold mb-0">{{ $lesson->title }}</h2>

        <div class="d-flex gap-2">
            @if ($lesson->isPublished())
                <a href="{{ route('student.lesson.show', $lesson) }}" target="_blank"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Xem
                </a>
            @endif

            <form method="POST" action="{{ route('teacher.lessons.publish', $lesson) }}">
                @csrf
                <button class="btn btn-sm {{ $lesson->isPublished() ? 'btn-outline-secondary' : 'btn-success' }}">
                    {{ $lesson->isPublished() ? 'Gỡ xuất bản' : 'Xuất bản' }}
                </button>
            </form>
        </div>
    </div>

    <div class="card border mb-4">
        <div class="card-body">
            <h3 class="h6 fw-bold mb-3">Thông tin bài học</h3>

            <form method="POST" action="{{ route('teacher.lessons.update', $lesson) }}" novalidate>
                @csrf
                @method('PUT')
                @include('teacher.lessons.partials.form')

                <button type="submit" class="btn btn-primary mt-4 btn-touch">Lưu thông tin</button>
            </form>
        </div>
    </div>

    <h3 class="h6 fw-bold mb-2">Nội dung bài học</h3>
    <p class="text-secondary small">
        Một bài học nên đi theo thứ tự: Lý thuyết → Ví dụ → Hiểu bản chất → Công thức →
        Lỗi thường gặp → Quiz → Luyện tập → Nâng cao → Kiểm tra.
    </p>

    @forelse ($lesson->sections as $section)
        <div class="card border mb-2">
            <div class="card-body">
                <form method="POST" action="{{ route('teacher.sections.update', [$lesson, $section]) }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-2 mb-2">
                        <div class="col-12 col-sm-4">
                            <select name="type" class="form-select form-select-sm">
                                @foreach (\App\Models\LessonSection::TYPES as $value => $label)
                                    <option value="{{ $value }}" @selected($section->type === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <input type="text" name="title" value="{{ $section->title }}"
                                   class="form-control form-control-sm" placeholder="Tiêu đề (không bắt buộc)">
                        </div>
                        <div class="col-12 col-sm-2">
                            <input type="number" name="sort_order" value="{{ $section->sort_order }}"
                                   class="form-control form-control-sm" min="0" max="999">
                        </div>
                    </div>

                    <textarea name="content" rows="5" class="form-control font-monospace mb-2"
                              data-math-editor>{{ $section->content }}</textarea>

                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button class="btn btn-sm btn-primary">Lưu</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-preview-toggle>
                            Xem trước
                        </button>
                        <div class="btn-group btn-group-sm" role="group" aria-label="AI hỗ trợ">
                            <button type="button" class="btn btn-outline-primary" data-ai-rewrite="simplify">
                                <i class="bi bi-stars me-1"></i>Viết lại dễ hiểu
                            </button>
                            <button type="button" class="btn btn-outline-primary" data-ai-rewrite="summarize">Tóm tắt</button>
                            <button type="button" class="btn btn-outline-secondary" data-ai-undo hidden>Hoàn tác</button>
                        </div>
                        <span class="text-secondary small ms-auto">
                            Công thức: <code>$...$</code> hoặc <code>$$...$$</code>
                        </span>
                    </div>
                </form>

                <div class="border rounded p-3 mt-2 d-none bg-light" data-preview-target data-math></div>

                <form method="POST" action="{{ route('teacher.sections.destroy', [$lesson, $section]) }}"
                      class="mt-2" onsubmit="return confirm('Xoá phần nội dung này?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash me-1"></i>Xoá phần này
                    </button>
                </form>
            </div>
        </div>
    @empty
        <div class="alert alert-light border">
            Bài học chưa có nội dung. Thêm phần đầu tiên bên dưới.
        </div>
    @endforelse

    <div class="card border border-primary mt-3">
        <div class="card-body">
            <h4 class="h6 fw-bold mb-3">Thêm phần nội dung</h4>

            <form method="POST" action="{{ route('teacher.sections.store', $lesson) }}">
                @csrf

                <div class="row g-2 mb-2">
                    <div class="col-12 col-sm-5">
                        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                            @foreach (\App\Models\LessonSection::TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-sm-7">
                        <input type="text" name="title" value="{{ old('title') }}"
                               class="form-control" placeholder="Tiêu đề (không bắt buộc)">
                    </div>
                </div>

                <textarea name="content" rows="5" required data-math-editor
                          class="form-control font-monospace mb-2 @error('content') is-invalid @enderror"
                          placeholder="<p>Nội dung… $$\frac{1}{2}$$</p>">{{ old('content') }}</textarea>
                @error('content') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-touch">Thêm phần</button>
                    <button type="button" class="btn btn-outline-secondary btn-touch" data-preview-toggle>
                        Xem trước
                    </button>
                        <div class="btn-group " role="group" aria-label="AI hỗ trợ">
                            <button type="button" class="btn btn-outline-primary" data-ai-rewrite="simplify">
                                <i class="bi bi-stars me-1"></i>Viết lại dễ hiểu
                            </button>
                            <button type="button" class="btn btn-outline-primary" data-ai-rewrite="summarize">Tóm tắt</button>
                            <button type="button" class="btn btn-outline-secondary" data-ai-undo hidden>Hoàn tác</button>
                        </div>
                </div>
            </form>

            <div class="border rounded p-3 mt-2 d-none bg-light" data-preview-target data-math></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
// AI viết lại / tóm tắt (§12): chỉ thay nội dung trong ô soạn, KHÔNG lưu — giáo viên đọc lại rồi tự bấm Lưu.
document.querySelectorAll('[data-ai-rewrite]').forEach((btn) => {
    btn.addEventListener('click', async () => {
        const card = btn.closest('.card-body');
        const editor = card.querySelector('[data-math-editor]');
        const undo = card.querySelector('[data-ai-undo]');
        if (!editor || !editor.value.trim()) return;

        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const res = await fetch(@json(route('teacher.ai.rewrite')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ content: editor.value, mode: btn.dataset.aiRewrite }),
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'AI chưa xử lý được.');

            undo.dataset.previous = editor.value;
            undo.hidden = false;
            editor.value = json.data.html;
        } catch (e) {
            alert(e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });
});

document.querySelectorAll('[data-ai-undo]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const editor = btn.closest('.card-body').querySelector('[data-math-editor]');
        editor.value = btn.dataset.previous ?? editor.value;
        btn.hidden = true;
    });
});

// Xem trước KaTeX: đổ nội dung textarea vào khối preview rồi render lại công thức.
document.querySelectorAll('[data-preview-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const card = btn.closest('.card-body');
        const editor = card.querySelector('[data-math-editor]');
        const target = card.querySelector('[data-preview-target]');
        if (!editor || !target) return;

        if (!target.classList.contains('d-none')) {
            target.classList.add('d-none');
            btn.textContent = 'Xem trước';
            return;
        }

        target.innerHTML = editor.value;
        delete target.dataset.mathRendered;
        target.classList.remove('d-none');
        btn.textContent = 'Ẩn xem trước';
        window.renderMath(target.parentElement);
    });
});
</script>
@endpush
