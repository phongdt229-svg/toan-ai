{{--
    Dải hỏi đồng ý cookie. Chỉ hiện khi người dùng CHƯA chọn, và chỉ khi có công cụ đo lường
    để hỏi — chưa cấu hình GA/GTM thì không có gì để xin phép, hỏi là làm phiền vô ích.

    Cố ý KHÔNG phải modal chặn màn hình: người dùng phần lớn là trẻ em, chắn đường vào bài học
    để hỏi cookie là đánh đổi sai. Cũng cố ý không có nút X — im lặng không phải là đồng ý.

    Gửi bằng form POST nên chạy cả khi tắt JS; lựa chọn lưu trong cookie đã mã hoá của Laravel.
--}}
@php
    use App\Http\Controllers\Web\CookieConsentController as Consent;

    $hasTracking = config('site.google_analytics_id') || config('site.google_tag_manager_id');
    $chosen = request()->cookie(Consent::COOKIE);
@endphp

@if ($hasTracking && ! in_array($chosen, [Consent::ACCEPTED, Consent::REJECTED], true))
    <div class="cookie-consent" role="region" aria-label="Lựa chọn cookie">
        <div class="cookie-consent__text">
            <strong>Cookie đo lường.</strong>
            Chúng tôi muốn dùng Google Analytics để biết trang nào khó dùng mà sửa. Không có quảng cáo,
            không bán dữ liệu. Cookie giữ đăng nhập thì luôn cần nên không hỏi.
            <a href="{{ route('legal.privacy') }}">Xem chính sách</a>
        </div>

        <div class="cookie-consent__actions">
            <form method="POST" action="{{ route('cookie.store') }}">
                @csrf
                <input type="hidden" name="choice" value="{{ Consent::REJECTED }}">
                <button class="btn btn-sm btn-outline-light">Chỉ cookie cần thiết</button>
            </form>
            <form method="POST" action="{{ route('cookie.store') }}">
                @csrf
                <input type="hidden" name="choice" value="{{ Consent::ACCEPTED }}">
                <button class="btn btn-sm btn-primary">Đồng ý</button>
            </form>
        </div>
    </div>
@endif
