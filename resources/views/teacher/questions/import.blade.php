@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Nhập câu hỏi từ CSV — TOÁN AI')
@section('page_title', 'Nhập câu hỏi')

@section('content')
    <a href="{{ route('teacher.questions.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Ngân hàng câu hỏi
    </a>

    <h2 class="h5 fw-bold mt-2 mb-3">Nhập câu hỏi từ tệp CSV</h2>

    @if (session('import_errors'))
        @php $errs = session('import_errors'); @endphp
        @if (count($errs) > 0)
            <div class="alert alert-warning">
                <div class="fw-semibold mb-2">Một số dòng bị bỏ qua:</div>
                <ul class="small mb-0">
                    @foreach ($errs as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    <div class="card border mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('teacher.questions.import.store') }}"
                  enctype="multipart/form-data">
                @csrf

                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <label for="grade_id" class="form-label">Nhập vào lớp <span class="text-danger">*</span></label>
                        <select id="grade_id" name="grade_id" class="form-select" required>
                            @foreach ($grades as $g)
                                <option value="{{ $g->id }}" @selected($g->id === $grade->id)>{{ $g->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="status" class="form-label">Trạng thái sau khi nhập</label>
                        <select id="status" name="status" class="form-select">
                            <option value="draft">Nháp (nên duyệt lại trước khi dùng)</option>
                            <option value="published">Xuất bản ngay</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="file" class="form-label">Tệp CSV <span class="text-danger">*</span></label>
                        <input type="file" id="file" name="file" accept=".csv,text/csv"
                               class="form-control @error('file') is-invalid @enderror" required>
                        @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Tối đa 2MB, mã hoá UTF-8.</div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-touch">Nhập câu hỏi</button>
                    <a href="{{ route('teacher.questions.import.template') }}" class="btn btn-outline-secondary btn-touch">
                        <i class="bi bi-download me-1"></i>Tải tệp mẫu
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border">
        <div class="card-body">
            <h3 class="h6 fw-bold mb-2">Cách điền tệp</h3>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr><th>Cột</th><th>Ý nghĩa</th></tr>
                    </thead>
                    <tbody class="small">
                        <tr><td><code>type</code></td><td>{{ implode(', ', array_keys(\App\Models\Question::TYPES)) }}</td></tr>
                        <tr><td><code>difficulty</code></td><td>easy, medium, hard</td></tr>
                        <tr><td><code>points</code></td><td>Điểm của câu, mặc định 1</td></tr>
                        <tr><td><code>content</code></td><td>Nội dung câu hỏi, công thức dùng <code>$...$</code></td></tr>
                        <tr><td><code>explanation</code></td><td>Giải thích, hiện sau khi học sinh nộp</td></tr>
                        <tr><td><code>topic_id</code></td><td>Id chủ đề (xem bảng bên dưới)</td></tr>
                        <tr><td><code>option_1..6</code></td><td>Lựa chọn, chỉ dùng cho câu trắc nghiệm</td></tr>
                        <tr>
                            <td><code>correct</code></td>
                            <td>
                                Trắc nghiệm: số thứ tự đáp án đúng (<code>1</code> hoặc <code>1,3</code>) ·
                                Đúng/Sai: <code>true</code>/<code>false</code> ·
                                Điền chỗ trống: các chỗ cách nhau bằng <code>;</code>, đáp án thay thế bằng <code>|</code>
                            </td>
                        </tr>
                        <tr><td><code>accepted</code></td><td>Trả lời ngắn: các đáp án chấp nhận, cách nhau bằng <code>|</code></td></tr>
                    </tbody>
                </table>
            </div>

            <h3 class="h6 fw-bold mt-4 mb-2">Id chủ đề của {{ $grade->name }}</h3>

            @if ($topics->isEmpty())
                <p class="text-secondary small mb-0">
                    {{ $grade->name }} chưa có chủ đề nào. Nhờ quản trị viên thêm vào cây chương trình trước.
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th style="width:6rem">topic_id</th><th>Chủ đề</th></tr></thead>
                        <tbody class="small">
                            @foreach ($topics as $topic)
                                <tr>
                                    <td><code>{{ $topic->id }}</code></td>
                                    <td>{{ $topic->chapter->name }} · {{ $topic->name }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
