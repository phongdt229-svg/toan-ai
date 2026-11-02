@props([
    'size' => 'md',        // sm (thanh điều hướng mobile) · md · lg
    'variant' => 'dark',   // dark = trên nền sáng · light = trên nền tối (logo nền trắng nên đặt trong ô trắng)
])

{{-- Dùng public/images/logo.webp (17 KB), không dùng public/icons/logo.jpg gốc 209 KB: logo nằm trên mọi trang. --}}
<span {{ $attributes->merge(['class' => "brand brand--{$size} brand--{$variant}"]) }}>
    <img class="brand__logo" src="{{ asset('images/logo.webp') }}" alt="Học Toán Online" width="620" height="168" decoding="async">
</span>
