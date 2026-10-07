@extends('layouts.public')

@section('title', 'Gói học — TOÁN AI')
@section('meta_description', 'Bảng giá các gói học Toán Free, Pro, Premium của TOÁN AI.')

@push('head')
    {{--
        JSON-LD Product/Offer cho từng gói — giá lấy thẳng từ $tiers (đã nạp từ bảng packages
        trong PackageController, đúng luật "giá luôn đọc từ DB"). Gói Free giá 0 vẫn khai Offer
        bình thường, schema.org không bắt buộc giá dương.
    --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@graph' => $tiers->flatMap->packages->map(fn ($package) => array_filter([
                '@type' => 'Product',
                'name' => $package->name,
                'description' => $package->description ?: null,
                'offers' => [
                    '@type' => 'Offer',
                    // Gói Free không có trang checkout riêng (chặn 404, xem PackageController)
                    // — trỏ về chính trang bảng giá thay vì một URL biết trước sẽ lỗi.
                    'url' => $package->isFree() ? route('packages.index') : route('packages.checkout', $package),
                    'priceCurrency' => $package->currency,
                    'price' => (string) $package->price,
                    'availability' => 'https://schema.org/InStock',
                ],
            ]))->values(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('body')
    @include('public.partials.header')

    <main class="section">
        <div class="container">
            @include('components.flash')

            <h1 class="section__title mb-2">Gói <span class="hl">học</span></h1>
            <p class="section__subtitle mb-4">
                Bắt đầu miễn phí. Nâng cấp khi cần luyện nhiều hơn và dùng AI Tutor thoải mái.
            </p>

            @include('public.partials.voucher-offers')

            @if ($current)
                <div class="alert alert-success d-flex flex-wrap align-items-center gap-2">
                    <i class="bi bi-gem"></i>
                    <span>
                        Em đang dùng <strong>{{ $current->package->name }}</strong>,
                        còn {{ $current->daysLeft() }} ngày (đến {{ $current->ends_at->format('d/m/Y') }}).
                        Mua thêm cùng hạng sẽ được cộng nối tiếp.
                    </span>
                    <a href="{{ route('student.subscription.index') }}" class="ms-auto link-success">Gói của tôi</a>
                </div>
            @endif

            @if ($tiers->isEmpty())
                <div class="card border"><div class="card-body text-secondary text-center p-4">Bảng giá đang được cập nhật.</div></div>
            @else
                <div class="row g-3">
                    @foreach ($tiers as $t)
                        <div class="col-12 col-md-4">
                            @include('public.packages._tier', ['t' => $t, 'compact' => false, 'currentTier' => $currentTier])
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="row g-3 mt-4 small text-secondary">
                <div class="col-12 col-md-4"><i class="bi bi-shield-check me-1 text-success"></i>Thanh toán qua ví MoMo, gói kích hoạt ngay khi MoMo xác nhận.</div>
                <div class="col-12 col-md-4"><i class="bi bi-people me-1 text-primary"></i>Phụ huynh có thể mua gói cho con đã liên kết.</div>
                <div class="col-12 col-md-4"><i class="bi bi-arrow-repeat me-1 text-warning"></i>Không tự gia hạn — hết hạn tự về gói Free, không mất dữ liệu học.</div>
            </div>
        </div>
    </main>

    @include('public.partials.footer')
@endsection
