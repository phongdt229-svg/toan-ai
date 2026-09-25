{{--
    Khuyến mãi đang chạy, hiện ở trang Gói học.

    Chỉ những mã admin bật "công khai" mới vào đây — mã của một chiến dịch riêng đem khoe
    là ai cũng dùng được. Mã hết lượt cũng bị lọc: quảng cáo một mã mà bấm vào báo "hết lượt"
    còn tệ hơn là không quảng cáo gì.

    Nút "Dùng mã" đi qua link `?ma=` — server tự áp và tự kiểm lại điều kiện,
    kể cả khi người xem chưa đăng nhập.
--}}
@if ($offers->isNotEmpty())
    <div class="row g-3 mb-4">
        @foreach ($offers as $offer)
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card border-primary h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge text-bg-primary font-monospace">{{ $offer->code }}</span>
                            <span class="fw-bold text-primary">Giảm {{ $offer->valueLabel() }}</span>
                        </div>

                        @if ($offer->description)
                            <p class="small text-secondary mb-2">{{ $offer->description }}</p>
                        @endif

                        <ul class="list-unstyled small text-secondary d-grid gap-1 mb-3">
                            @if ($offer->max_discount)
                                <li><i class="bi bi-dot"></i>Giảm tối đa {{ number_format((float) $offer->max_discount, 0, ',', '.') }}₫</li>
                            @endif
                            @if ($offer->min_order_amount)
                                <li><i class="bi bi-dot"></i>Đơn từ {{ number_format((float) $offer->min_order_amount, 0, ',', '.') }}₫</li>
                            @endif
                            <li>
                                <i class="bi bi-dot"></i>
                                {{ $offer->packages->isEmpty() ? 'Áp dụng mọi gói' : 'Chỉ gói '.$offer->packages->pluck('name')->implode(', ') }}
                            </li>
                            @if ($offer->ends_at)
                                <li><i class="bi bi-dot"></i>Đến hết {{ $offer->ends_at->format('d/m/Y') }}</li>
                            @endif
                        </ul>

                        <a href="{{ route('packages.index', ['ma' => $offer->code]) }}"
                           class="btn btn-sm btn-primary mt-auto">Dùng mã này</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
