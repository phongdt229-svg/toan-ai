@extends('layouts.app', ['portal' => 'admin'])

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

    <div class="card border mb-3">
        <div class="card-body">
            <div class="fw-semibold mb-2">Báo cáo truy cập</div>
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
