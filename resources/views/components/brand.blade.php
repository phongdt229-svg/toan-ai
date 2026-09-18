@props([
    'size' => 'md',        // sm (thanh điều hướng mobile) · md · lg
    'variant' => 'dark',   // dark = chữ đậm trên nền sáng · light = chữ trắng trên nền tối
])

@php
    // Mỗi logo trên trang có gradient riêng: id trùng nhau sẽ khiến logo thứ hai ăn màu của logo đầu.
    $gradientId = 'brand-mark-'.uniqid();
@endphp

<span {{ $attributes->merge(['class' => "brand brand--{$size} brand--{$variant}"]) }}>
    <svg class="brand__mark" viewBox="0 0 40 40" role="img" aria-label="{{ config('site.brand') }}" focusable="false">
        <defs>
            <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#2563eb"/>
                <stop offset="1" stop-color="#22d3ee"/>
            </linearGradient>
        </defs>

        <rect width="40" height="40" rx="12" fill="url(#{{ $gradientId }})"/>

        {{-- Dấu căn: nét trắng bo tròn, kết thúc bằng chấm cam — "tia" AI. --}}
        <path d="M9 21.5 L14 28.5 L21.5 11.5 H29" fill="none" stroke="#fff" stroke-width="3"
              stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="30.5" cy="11.5" r="3" fill="#fb923c"/>
    </svg>

    <span class="brand__text">Math<span class="brand__ai">AI</span></span>
</span>
