@extends('errors.layout')

@section('code', 'Lỗi 403')
@section('art', '🔒')
@section('title', 'Bạn không có quyền vào đây')

@section('message')
    <p>Trang này dành cho vai trò khác (giáo viên, phụ huynh hoặc quản trị), hoặc phần nội dung này thuộc gói học bạn chưa đăng ký.</p>
    <p>Nếu bạn nghĩ đây là nhầm lẫn, hãy đăng nhập lại bằng đúng tài khoản.</p>
@endsection

@section('actions')
                <a class="btn primary" href="/">Về trang chủ</a>
                <a class="btn ghost" href="/dang-nhap">Đăng nhập lại</a>
@endsection
