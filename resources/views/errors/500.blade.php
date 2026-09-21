@extends('errors.layout')

@section('code', 'Lỗi 500')
@section('title', 'Hệ thống đang gặp sự cố')

@section('message')
    <p>Lỗi nằm ở phía chúng tôi, không phải do bạn làm sai. Sự cố đã được ghi lại và đội kỹ thuật sẽ xử lý.</p>
    <p>Bạn thử tải lại sau ít phút; nếu vẫn vậy thì báo giúp chúng tôi qua trang hỗ trợ.</p>
@endsection

@section('actions')
                <a class="btn primary" href="javascript:location.reload()">Tải lại trang</a>
                <a class="btn ghost" href="/">Về trang chủ</a>
@endsection
