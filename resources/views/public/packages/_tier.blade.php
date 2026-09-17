{{--
    Thẻ một hạng gói. $t = phần tử của Package::catalog(); $compact = true trên landing (chỉ giá "từ …").
--}}
@php
    $cheapest = $t['packages']->sortBy('price')->first();
    $isCurrent = isset($currentTier) && $currentTier === $t['tier'];
@endphp

<div class="price-card d-flex flex-column {{ $t['highlighted'] ? 'price-card--featured' : '' }}">
    <div class="d-flex align-items-center gap-2 mb-1">
        <h3 class="h5 fw-bold mb-0">{{ $t['label'] }}</h3>
        @if ($t['highlighted'])
            <span class="badge text-bg-primary">Phổ biến</span>
        @endif
        @if ($isCurrent)
            <span class="badge text-bg-success ms-auto">Gói hiện tại</span>
        @endif
    </div>

    <div class="price-card__price mb-1">
        {{ $cheapest->priceLabel() }}
        @if ($cheapest->durationLabel())
            <span class="fs-6 fw-normal text-secondary">/ {{ $cheapest->durationLabel() }}</span>
        @endif
    </div>
    <p class="text-secondary small">{{ $cheapest->description }}</p>

    <ul class="list-unstyled small d-grid gap-2 mb-4">
        @foreach ($t['features'] as $feature)
            @php $off = $feature->value === '0'; @endphp
            <li class="d-flex gap-2 {{ $off ? 'text-secondary text-decoration-line-through opacity-75' : '' }}">
                <i class="bi {{ $off ? 'bi-x-lg' : 'bi-check-lg text-success' }}"></i>
                <span>{{ $feature->label }}</span>
            </li>
        @endforeach
    </ul>

    <div class="mt-auto d-grid gap-2">
        @if ($compact)
            <a href="{{ route('packages.index') }}" class="btn {{ $t['highlighted'] ? 'btn-primary' : 'btn-outline-primary' }} btn-touch">
                {{ $cheapest->isFree() ? 'Bắt đầu miễn phí' : 'Xem chi tiết' }}
            </a>
        @elseif ($cheapest->isFree())
            @guest
                <a href="{{ route('register') }}" class="btn btn-outline-primary btn-touch">Đăng ký miễn phí</a>
            @endguest
        @else
            @foreach ($t['packages'] as $package)
                <a href="{{ auth()->check() ? route('packages.checkout', $package) : route('login') }}"
                   class="btn {{ $package->is_highlighted ? 'btn-primary' : 'btn-outline-primary' }} btn-touch d-flex justify-content-between">
                    <span>{{ $package->durationLabel() }}</span>
                    <span class="fw-semibold">{{ $package->priceLabel() }}</span>
                </a>
            @endforeach
        @endif
    </div>
</div>
