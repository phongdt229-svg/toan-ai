@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Xem trước đề Word — Math AI')
@section('page_title', 'Nhập câu hỏi')

@section('content')
    <a href="{{ route('teacher.questions.import', ['grade' => $grade->slug]) }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Nhập câu hỏi
    </a>

    <h2 class="h5 fw-bold mt-2 mb-1">Xem trước: {{ $data['file_name'] }}</h2>
    <p class="text-secondary small mb-3">
        {{ $grade->name }}{{ $topic ? ' · '.$topic->chapter->name.' · '.$topic->name : ' · chưa gắn chủ đề' }} ·
        <strong>{{ $valid }}</strong> câu sẽ được nhập
        @if ($rows->count() > $valid)
            · <span class="text-danger">{{ $rows->count() - $valid }} câu lỗi sẽ bị bỏ qua</span>
        @endif
    </p>

    @foreach ($data['warnings'] as $warning)
        <div class="alert alert-warning small">{{ $warning }}</div>
    @endforeach

    @if ($valid > 0)
        <form method="POST" action="{{ route('teacher.questions.import.word.confirm', $token) }}"
              class="card border mb-4" data-import-confirm>
            @csrf
            <div class="card-body d-flex flex-column flex-sm-row gap-2 align-items-sm-center">
                <label for="status" class="form-label mb-0 text-nowrap">Lưu dưới dạng</label>
                <select id="status" name="status" class="form-select" style="max-width:280px">
                    <option value="draft">Nháp (nên duyệt lại trước khi dùng)</option>
                    <option value="published">Xuất bản ngay</option>
                </select>
                <button class="btn btn-primary btn-touch ms-sm-auto">
                    <i class="bi bi-cloud-arrow-up me-1"></i>Nhập {{ $valid }} câu hỏi
                </button>
            </div>
        </form>
    @else
        <div class="alert alert-danger">Không có câu nào nhập được — sửa tệp theo các lỗi bên dưới rồi tải lên lại.</div>
    @endif

    <div class="d-grid gap-3">
        @foreach ($rows as $row)
            <div class="card border {{ $row['errors'] ? 'border-danger' : '' }}" data-preview-question>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-2 small">
                        <span class="fw-bold">Câu {{ $row['number'] }}</span>
                        <span class="badge text-bg-light border">{{ \App\Models\Question::TYPES[$row['type']] ?? $row['type'] }}</span>
                        <span class="badge text-bg-light border">{{ \App\Models\Question::DIFFICULTIES[$row['difficulty']] ?? '' }}</span>
                        <span class="text-secondary">{{ rtrim(rtrim(number_format((float) $row['points'], 2, ',', ''), '0'), ',') }} điểm</span>
                    </div>

                    {{-- Nội dung đã escape từng dòng ở DocxQuestionParser (chỉ còn <br> do server ghép). --}}
                    <div class="mb-2" data-math>{!! $row['content'] !!}</div>

                    @for ($i = 1; $i <= 6; $i++)
                        @isset($row["option_{$i}"])
                            @php $isCorrect = in_array((string) $i, explode(',', $row['correct']), true); @endphp
                            <div class="small d-flex gap-2 {{ $isCorrect ? 'text-success fw-semibold' : '' }}">
                                <span>{{ chr(64 + $i) }}.</span>
                                <span data-math>{!! $row["option_{$i}"] !!}</span>
                                @if ($isCorrect)<i class="bi bi-check-circle-fill" aria-label="Đáp án đúng"></i>@endif
                            </div>
                        @endisset
                    @endfor

                    @if ($row['type'] === 'true_false')
                        <div class="small text-success fw-semibold">Đáp án: {{ $row['correct'] === 'true' ? 'Đúng' : 'Sai' }}</div>
                    @elseif ($row['type'] === 'short_answer')
                        <div class="small text-success fw-semibold">Đáp án chấp nhận: {{ str_replace('|', ' · ', $row['accepted']) }}</div>
                    @endif

                    @if ($row['explanation'] !== '')
                        <div class="border-start border-3 ps-2 mt-2 small text-secondary" data-math>{!! $row['explanation'] !!}</div>
                    @endif

                    @foreach ($row['errors'] as $error)
                        <div class="small text-danger mt-1"><i class="bi bi-x-circle me-1"></i>{{ $error }}</div>
                    @endforeach
                    @foreach ($row['warnings'] as $warning)
                        <div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-triangle me-1"></i>{{ $warning }}</div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endsection
