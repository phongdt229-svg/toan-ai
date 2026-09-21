@extends('errors.layout')

@section('code', 'Lỗi 429')
@section('art', '🚦')
@section('title', 'Bạn thao tác hơi nhanh')

@section('message')
    <p>Hệ thống tạm chặn để tránh quá tải. Chờ khoảng một phút rồi thử lại giúp chúng tôi nhé.</p>
@endsection

@section('actions')
                <a class="btn primary" href="javascript:location.reload()">Thử lại</a>
                <a class="btn ghost" href="/">Về trang chủ</a>
@endsection
