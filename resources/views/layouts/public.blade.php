{{--
    Layout cho các trang công khai (landing, gói học, pháp lý, hỗ trợ...) — thêm nút chat
    nổi Zalo/Facebook + chia sẻ trang so với layouts.base. KHÔNG dùng cho layouts.app
    (học sinh/giáo viên/phụ huynh/quản trị đang thao tác trong ứng dụng) hay layouts.guest
    (đăng nhập/đăng ký) — nút nổi ở đó dễ che mất nội dung trên màn hình nhỏ.
--}}
@extends('layouts.base')

@push('widgets')
    @include('public.partials.floating-contact')
@endpush
