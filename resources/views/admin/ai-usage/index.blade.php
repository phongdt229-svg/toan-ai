@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'AI usage — TOÁN AI')
@section('page_title', 'AI usage')

@php
    $features = [
        'chat' => 'Hỏi đáp', 'hint' => 'Gợi ý', 'explain' => 'Giải thích', 'check_answer' => 'Kiểm tra đáp án',
        'similar_exercise' => 'Bài tương tự', 'analyze_mistake' => 'Phân tích lỗi',
        'generate_questions' => 'GV: tạo câu hỏi', 'generate_lesson' => 'GV: tạo bài học', 'rewrite' => 'GV: viết lại',
    ];
    $usd = fn ($v) => '$' . number_format((float) $v, (float) $v < 1 ? 4 : 2);
@endphp

@section('content')
    <h2 class="h5 fw-bold mb-3">Sử dụng & chi phí AI</h2>

    {{-- Trạng thái provider --}}
    @if ($isFake)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Đang dùng <strong>FakeProvider</strong> — học sinh nhận câu trả lời mẫu, không phải AI thật.
            Đặt <code>AI_PROVIDER=openai</code> và <code>OPENAI_API_KEY</code> trong <code>.env</code> để chạy thật.
        </div>
    @elseif (! $keyConfigured)
        <div class="alert alert-danger">
            <i class="bi bi-x-octagon me-1"></i>
            <code>AI_PROVIDER=openai</code> nhưng chưa có <code>OPENAI_API_KEY</code> — mọi yêu cầu AI đang báo lỗi.
        </div>
    @else
        <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>Provider: <code>{{ $providerName }}</code></div>
    @endif

    @if ($stuckDrafts > 0)
        <div class="alert alert-warning">
            <i class="bi bi-hourglass-split me-1"></i>
            {{ $stuckDrafts }} yêu cầu AI soạn bài của giáo viên chờ quá 5 phút — kiểm tra <code>php artisan queue:work</code> có đang chạy.
        </div>
    @endif

    @if ($offTopicSuspected > 0)
        <div class="alert alert-warning">
            <i class="bi bi-shield-exclamation me-1"></i>
            {{ $offTopicSuspected }} lượt chat trong 30 ngày có tin nhắn ngoài lề mà AI không từ chối đúng cách —
            <a href="{{ route('admin.audit-logs.index', ['action' => 'ai.off_topic_suspected']) }}">xem lại trong nhật ký thao tác</a>.
        </div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([['Hôm nay', $today], ['7 ngày', $week], ['30 ngày', $month]] as [$label, $t])
            <div class="col-12 col-md-4">
                <div class="stat-card">
                    <div class="stat-card__label">{{ $label }}</div>
                    <div class="stat-card__value">{{ number_format($t->requests) }} <span class="fs-6 fw-normal text-secondary">lượt</span></div>
                    <div class="small text-secondary">
                        ≈ {{ $usd($t->cost) }} · {{ number_format($t->tokens_in + $t->tokens_out) }} token
                        @if ($t->failed > 0) · <span class="text-danger">{{ $t->failed }} lỗi</span> @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <h3 class="h6 fw-bold mb-2">Theo tính năng (30 ngày)</h3>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Tính năng</th><th class="text-end">Lượt</th><th class="text-end">Lỗi</th><th class="text-end">Chi phí</th></tr></thead>
                    <tbody>
                        @forelse ($byFeature as $row)
                            <tr>
                                <td>{{ $features[$row->feature] ?? $row->feature }}</td>
                                <td class="text-end">{{ number_format($row->requests) }}</td>
                                <td class="text-end {{ $row->failed > 0 ? 'text-danger' : 'text-secondary' }}">{{ $row->failed }}</td>
                                <td class="text-end">{{ $usd($row->cost) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-secondary">Chưa có dữ liệu.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <h3 class="h6 fw-bold mb-2">Dùng nhiều nhất (30 ngày)</h3>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Người dùng</th><th class="text-end">Lượt</th><th class="text-end">Chi phí</th></tr></thead>
                    <tbody>
                        @forelse ($topUsers as $u)
                            <tr>
                                <td>{{ $u->name }}<div class="small text-secondary">{{ $u->email }}</div></td>
                                <td class="text-end">{{ number_format($u->requests) }}</td>
                                <td class="text-end">{{ $usd($u->cost) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-secondary">Chưa có dữ liệu.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h3 class="h6 fw-bold mt-4 mb-2">14 ngày gần nhất</h3>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Ngày</th><th class="text-end">Lượt</th><th class="text-end">Lỗi</th><th class="text-end">Chi phí</th></tr></thead>
                    <tbody>
                        @forelse ($daily as $d)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($d->usage_date)->format('d/m') }}</td>
                                <td class="text-end">{{ $d->requests }}</td>
                                <td class="text-end">{{ $d->failed }}</td>
                                <td class="text-end">{{ $usd($d->cost) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-secondary">Chưa có dữ liệu.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <p class="small text-secondary mt-3">
        Chi phí là ước tính theo bảng giá trong <code>config/ai.php</code>, không phải hoá đơn — đối chiếu trang billing của nhà cung cấp.
        Giới hạn/ngày: học sinh Free {{ config('ai.daily_limits.student.free') }} · Pro {{ config('ai.daily_limits.student.pro') }}
        · Premium {{ config('ai.daily_limits.student.premium') }} · giáo viên {{ config('ai.daily_limits.teacher') }}.
    </p>
@endsection
