{{--
    Trang bảo trì. Dùng cho cả hai trường hợp:
    - `php artisan down --render="errors::503"` khi deploy (Laravel chụp sẵn HTML này, không chạy app nữa)
    - lỗi 503 thông thường (hết chỗ queue, service tạm ngừng)

    Vì Laravel chụp HTML lúc chạy `down`, trang KHÔNG được phụ thuộc session/DB/route.
    Thời điểm dự kiến xong lấy từ biến môi trường DEPLOY_ETA (vd DEPLOY_ETA="15 phút") nếu có.
--}}
@extends('errors.layout')

@section('code', 'Bảo trì')
@section('art', '🔧')
@section('title', 'Hệ thống đang được nâng cấp')

@section('message')
    <p>
        Chúng tôi đang cập nhật {{ config('app.name') }} để chạy tốt hơn.
        Dự kiến xong sau <strong>{{ env('DEPLOY_ETA', '15 phút') }}</strong>.
    </p>
    <p>
        Bài đang làm dở đã được lưu — bạn quay lại sau ít phút là học tiếp được bình thường.
        Các giao dịch thanh toán trong thời gian này sẽ được xử lý ngay khi hệ thống trở lại.
    </p>
@endsection

@section('actions')
    {{-- Không dẫn sang /huong-dan hay /ho-tro: lúc bảo trì mọi đường dẫn đều trả về chính trang này. --}}
    <a class="btn primary" href="javascript:location.reload()">Tải lại trang</a>
@endsection

@section('foot')
    Cần gấp? Email <a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a>
    @if (config('site.hotline'))
        hoặc gọi {{ config('site.hotline') }}
    @endif
@endsection
