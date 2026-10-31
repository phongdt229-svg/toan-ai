@props([
    'size' => 'md',        // sm (thanh điều hướng mobile) · md · lg
    'variant' => 'dark',   // dark = chữ đậm trên nền sáng · light = chữ trắng trên nền tối
])

@php
    // Mỗi logo trên trang có gradient riêng: id trùng nhau sẽ khiến logo thứ hai ăn màu của logo đầu.
    $gradientId = 'brand-mark-'.uniqid();
@endphp

{{-- Logo theo giao diện mới (mẫu V6): ô gradient tím→xanh chứa dấu ∑ trắng + chữ "Học Toán Online A.I". --}}
<span {{ $attributes->merge(['class' => "brand brand--{$size} brand--{$variant}"]) }}>
    <svg class="brand__mark" viewBox="0 0 40 40" role="img" aria-label="{{ config('site.brand') }}" focusable="false">
        <defs>
            <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#5743e5"/>
                <stop offset="1" stop-color="#2781e6"/>
            </linearGradient>
        </defs>

        <rect width="40" height="40" rx="13" fill="url(#{{ $gradientId }})"/>

        {{-- ∑ vẽ bằng nét (không dùng ký tự) để không phụ thuộc font của máy người xem. --}}
        <path d="M26.5 11 H14 L21.5 20 L14 29 H26.5" fill="none" stroke="#fff" stroke-width="3.2"
              stroke-linecap="round" stroke-linejoin="round"/>
    </svg>

    <span class="brand__text">Học Toán Online<span class="brand__ai">A.I</span></span>
</span>
