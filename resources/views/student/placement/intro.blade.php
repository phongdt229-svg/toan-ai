@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Kiểm tra đầu vào — TOÁN AI')
@section('page_title', 'Kiểm tra đầu vào')

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card border-primary mb-4">
                <div class="card-body p-4 text-center">
                    <i class="bi bi-compass text-primary" style="font-size:3rem"></i>
                    <h2 class="h4 fw-bold mt-3 mb-2">Kiểm tra đầu vào</h2>
                    <p class="text-secondary mb-4">
                        {{ $questionCount }} câu · {{ $minutes }} phút · {{ $grade?->name ?? 'chưa chọn lớp' }}.
                        Không tính điểm vào đâu cả — bài này giúp hệ thống biết em đang vững phần nào, yếu phần nào,
                        rồi xếp <strong>lộ trình học riêng</strong> cho em.
                    </p>

                    <div class="row g-2 text-start small mb-4">
                        @foreach ([
                            ['bi-bar-chart-steps', 'Độ khó theo học lực em chọn lúc đăng ký'],
                            ['bi-stopwatch', 'Có đồng hồ — hết giờ bài tự nộp'],
                            ['bi-signpost-split', 'Làm xong nhận ngay lộ trình 4 giai đoạn'],
                        ] as [$icon, $text])
                            <div class="col-12 col-md-4">
                                <div class="border rounded-3 p-2 h-100"><i class="bi {{ $icon }} text-primary me-1"></i>{{ $text }}</div>
                            </div>
                        @endforeach
                    </div>

                    @if (! $grade)
                        <div class="alert alert-warning mb-0">Em cần chọn lớp trong hồ sơ trước khi làm bài.</div>
                    @elseif ($latest?->isInProgress())
                        <a href="{{ route('student.placement.take', $latest) }}" class="btn btn-warning btn-lg btn-touch">
                            <i class="bi bi-play-fill me-1"></i>Làm tiếp bài đang dở
                        </a>
                    @else
                        <form method="POST" action="{{ route('student.placement.start') }}"
                              @if ($latest) onsubmit="return confirm('Làm lại sẽ tạo lộ trình mới thay cho lộ trình hiện tại. Tiếp tục?')" @endif>
                            @csrf
                            <button class="btn btn-primary btn-lg btn-touch">
                                <i class="bi bi-play-fill me-1"></i>{{ $latest ? 'Làm lại kiểm tra đầu vào' : 'Bắt đầu làm bài' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if ($latest && ! $latest->isInProgress())
                <a href="{{ route('student.placement.result', $latest) }}" class="card border text-decoration-none text-body">
                    <div class="card-body d-flex align-items-center gap-3">
                        <i class="bi bi-clipboard-data text-primary fs-3"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">Kết quả lần trước: {{ \App\Support\Score::format($latest->score) }}/10 · {{ $latest->levelLabel() }}</div>
                            <div class="text-secondary small">{{ $latest->submitted_at?->format('H:i d/m/Y') }}</div>
                        </div>
                        <i class="bi bi-chevron-right text-secondary"></i>
                    </div>
                </a>
            @endif
        </div>
    </div>
@endsection
