{{--
    Trang bảo trì. Dùng cho cả hai trường hợp:
    - `php artisan down --render="errors::503"` khi deploy (Laravel chụp sẵn HTML này, không chạy app nữa)
    - lỗi 503 thông thường (hết chỗ queue, service tạm ngừng)

    Vì Laravel chụp HTML lúc chạy `down`, trang KHÔNG được phụ thuộc session/DB/route.
    Thời điểm dự kiến xong: lấy từ $maintenanceEta (Quản trị -> Bảo trì chia sẻ sang lúc chụp),
    không có thì dùng config('site.maintenance_eta') (env DEPLOY_ETA) cho đường chạy bằng lệnh.
--}}
@extends('errors.layout')

@section('code', 'Bảo trì')
@section('title', 'Hệ thống đang được nâng cấp')

@section('message')
    <p>
        Chúng tôi đang cập nhật {{ config('app.name') }} để chạy tốt hơn.
        Dự kiến xong sau <strong>{{ $maintenanceEta ?? config('site.maintenance_eta') }}</strong>.
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
