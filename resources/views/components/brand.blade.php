@props([
    'size' => 'md',        // sm (thanh điều hướng mobile) · md · lg
    'variant' => 'dark',   // dark = trên nền sáng · light = trên nền tối (chữ xanh đậm của logo chìm trên nền tối nên đặt trong ô trắng)
])

{{-- Dùng public/images/logo.png (nền trong suốt, 19 KB), không dùng public/icons/logo.jpg gốc 209 KB: logo nằm trên mọi trang. --}}
<span {{ $attributes->merge(['class' => "brand brand--{$size} brand--{$variant}"]) }}>
    <img class="brand__logo" src="{{ asset('images/logo.png') }}" alt="Học Toán Online" width="406" height="110" decoding="async">
</span>
