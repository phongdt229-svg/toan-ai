@extends('errors.layout')

@section('code', 'Lỗi 419')
@section('title', 'Phiên làm việc đã hết hạn')

@section('message')
    <p>Bạn mở biểu mẫu khá lâu trước khi gửi nên hệ thống không còn nhận ra phiên làm việc.</p>
    <p>Tải lại trang rồi gửi lại là được — dữ liệu bạn vừa nhập sẽ cần nhập lại, mong bạn thông cảm.</p>
@endsection

@section('actions')
                <a class="btn primary" href="javascript:history.back()">Quay lại và thử lại</a>
                <a class="btn ghost" href="/dang-nhap">Đăng nhập lại</a>
@endsection
