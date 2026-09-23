@extends('layouts.app', ['portal' => 'admin'])

@push('head')
    @vite('resources/js/charts.js')
@endpush

@section('title', 'Google Analytics — Quản trị TOÁN AI')
@section('page_title', 'Google Analytics')

@section('content')
    @if (! $tracking)
        <div class="alert alert-secondary small">
            Môi trường này chưa bật đo lường (local/test để trống để không làm bẩn số liệu thật).
            Muốn thử thì đặt <code>GOOGLE_ANALYTICS_ID</code> hoặc <code>GOOGLE_TAG_MANAGER_ID</code> trong <code>.env</code>.
        </div>
    @endif

    @if ($both)
        {{-- Nhắc lại cảnh báo ở config/site.php: khai GA4 ở cả hai chỗ thì mỗi lượt xem bị đếm hai lần. --}}
        <div class="alert alert-warning small">
            Đang khai cả GA4 lẫn Tag Manager. Nếu trong container GTM cũng có thẻ GA4 cùng Measurement ID
            thì mỗi lượt xem trang bị đếm hai lần.
        </div>
    @endif

    @if ($gaReport)
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            @foreach ([7 => '7 ngày', 28 => '28 ngày', 90 => '90 ngày'] as $d => $label)
                <a href="{{ route('admin.analytics.index', ['days' => $d]) }}"
                   class="btn btn-sm {{ $days === $d ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
            <form method="POST" action="{{ route('admin.analytics.refresh') }}" class="ms-auto">
                @csrf
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Làm mới</button>
            </form>
        </div>

        <div class="row g-3 mb-3">
            @foreach ([['Người dùng', $gaReport['totals']['users'], 'primary'], ['Phiên', $gaReport['totals']['sessions'], 'success'], ['Lượt xem trang', $gaReport['totals']['views'], 'secondary']] as [$label, $value, $tone])
                <div class="col-12 col-sm-4">
                    <div class="card border h-100"><div class="card-body py-2">
                        <div class="small text-secondary">{{ $label }}</div>
                        <div class="h5 fw-bold mb-0 text-{{ $tone }}">{{ number_format($value, 0, ',', '.') }}</div>
                    </div></div>
                </div>
            @endforeach
        </div>

        <div class="card border mb-3"><div class="card-body">
            <div class="fw-semibold mb-2">Lượt truy cập theo ngày</div>
            <canvas data-chart-type="ga-daily" data-chart='@json($gaReport['daily'])' role="img" aria-label="Biểu đồ người dùng và lượt xem trang theo ngày"></canvas>
        </div></div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-lg-7">
                <div class="card border h-100"><div class="card-body">
                    <div class="fw-semibold mb-2">Trang xem nhiều nhất</div>
                    <table class="table table-sm small mb-0">
                        <tbody>
                            @forelse ($gaReport['pages'] as $page)
                                <tr><td class="text-break"><code>{{ $page['path'] }}</code></td><td class="text-end">{{ number_format($page['views'], 0, ',', '.') }}</td></tr>
                            @empty
                                <tr><td class="text-secondary">Chưa có dữ liệu.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div></div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="card border h-100"><div class="card-body">
                    <div class="fw-semibold mb-2">Nguồn truy cập (phiên)</div>
                    @if ($gaReport['sources'])
                        <canvas data-chart-type="count-bars" data-chart='@json($gaReport['sources'])' role="img" aria-label="Biểu đồ số phiên theo nguồn truy cập"></canvas>
                    @else
                        <div class="text-secondary small">Chưa có dữ liệu.</div>
                    @endif
                </div></div>
            </div>
        </div>
        <p class="small text-secondary">Số liệu từ Google Analytics, cache 10 phút và Google thường trễ vài giờ.</p>
    @elseif ($gaConfigured)
        <div class="alert alert-warning small">
            Đã khai <code>GA_PROPERTY_ID</code> nhưng chưa đọc được số liệu — kiểm tra service account đã được thêm vào GA4
            với quyền <em>Người xem</em> và đã bật <em>Google Analytics Data API</em>. Chi tiết lỗi nằm trong <code>storage/logs/laravel.log</code>.
        </div>
    @else
        <div class="alert alert-light border small">
            Muốn xem số liệu ngay tại đây: đặt <code>GA_PROPERTY_ID</code> (số, không phải <code>G-…</code>) và
            <code>GA_CREDENTIALS_PATH</code> (đường dẫn file JSON của service account, để ngoài repo) trong <code>.env</code>.
        </div>
    @endif

    <div class="card border mb-3">
        <div class="card-body">
            <div class="fw-semibold mb-2">Báo cáo Looker Studio</div>
            @if ($embedUrl)
                {{-- Đăng nhập Google trong khung nếu báo cáo không công khai; sandbox vẫn cần allow-same-origin để Looker chạy. --}}
                <div class="ratio ratio-4x3" style="max-height:80vh">
                    <iframe src="{{ $embedUrl }}" title="Báo cáo Google Analytics (Looker Studio)" loading="lazy"
                            referrerpolicy="no-referrer"
                            sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>
                </div>
            @else
                <p class="small text-secondary mb-2">Chưa gắn báo cáo. Để hiện ngay tại đây:</p>
                <ol class="small text-secondary mb-0">
                    <li>Vào <a href="https://lookerstudio.google.com/" target="_blank" rel="noopener noreferrer">Looker Studio</a>, tạo báo cáo từ nguồn dữ liệu Google Analytics.</li>
                    <li>Chọn <strong>Chia sẻ → Nhúng báo cáo</strong>, bật <em>Bật nhúng</em>, sao chép <strong>URL nhúng</strong>.</li>
                    <li>Đặt vào <code>.env</code>: <code>LOOKER_STUDIO_EMBED_URL=&lt;URL nhúng&gt;</code> rồi <code>php artisan config:clear</code>.</li>
                </ol>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ($tools as $tool)
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card border h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="fw-semibold mb-1"><i class="bi {{ $tool['icon'] }} me-2"></i>{{ $tool['name'] }}</div>
                        <p class="small text-secondary mb-2">{{ $tool['desc'] }}</p>
                        <div class="small mb-3">
                            @if ($tool['id'] !== '')
                                <i class="bi bi-check-circle-fill text-success me-1"></i><code>{{ $tool['id'] }}</code>
                            @else
                                <i class="bi bi-dash-circle text-secondary me-1"></i><span class="text-secondary">Chưa cấu hình</span>
                            @endif
                        </div>
                        <a href="{{ $tool['url'] }}" target="_blank" rel="noopener noreferrer"
                           class="btn btn-outline-primary btn-sm mt-auto align-self-start">
                            Mở <i class="bi bi-box-arrow-up-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <p class="small text-secondary mb-0">
        Đo lường chỉ nạp khi người dùng đã bấm Đồng ý ở dải cookie, nên số lượt trong Google có thể thấp hơn thực tế.
    </p>
@endsection
