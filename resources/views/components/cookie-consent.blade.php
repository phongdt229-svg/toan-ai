{{--
    Hai trường hợp khác nhau, đừng gộp làm một:

    1. CÓ công cụ đo lường (GA/GTM) → phải HỎI. Hai nút, không có nút X: im lặng không phải đồng ý.
       Chưa chọn thì không một dòng script đo lường nào được gửi xuống trình duyệt.

    2. KHÔNG có đo lường → chỉ THÔNG BÁO là trang dùng cookie (giữ đăng nhập, ghi nhớ lựa chọn).
       Không có gì để xin phép nên chỉ cần một nút "Đã hiểu", và lưu ở cookie RIÊNG:
       bấm "Đã hiểu" không phải là đồng ý cho đo lường, sau này bật GA vẫn phải hỏi lại.

    Cố ý không phải modal chặn màn hình: người dùng phần lớn là trẻ em, chắn đường vào bài học
    để nói chuyện cookie là đánh đổi sai. Gửi bằng form POST nên chạy cả khi tắt JS.
--}}
@php
    use App\Http\Controllers\Web\CookieConsentController as Consent;

    $hasTracking = config('site.google_analytics_id') || config('site.google_tag_manager_id');
    $chosen = request()->cookie(Consent::COOKIE);
    $noticeSeen = request()->cookie(Consent::NOTICE_COOKIE);

    $askConsent = $hasTracking && ! in_array($chosen, [Consent::ACCEPTED, Consent::REJECTED], true);
    $showNotice = ! $hasTracking && ! $noticeSeen;
@endphp

@if ($askConsent || $showNotice)
    <div class="cookie-consent" role="region" aria-label="Thông báo cookie">
        <div class="cookie-consent__text">
            @if ($askConsent)
                <strong>Cookie đo lường.</strong>
                Chúng tôi muốn dùng Google Analytics để biết trang nào khó dùng mà sửa. Không có quảng cáo,
                không bán dữ liệu. Cookie giữ đăng nhập thì luôn cần nên không hỏi.
            @else
                <strong>Trang này dùng cookie.</strong>
                Chỉ những cookie cần thiết để giữ phiên đăng nhập và ghi nhớ lựa chọn của bạn.
                Không có cookie quảng cáo, không theo dõi bạn sang website khác.
            @endif
            <a href="{{ route('legal.privacy') }}">Xem chính sách</a>
        </div>

        <div class="cookie-consent__actions">
            @if ($askConsent)
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
            @else
                <form method="POST" action="{{ route('cookie.store') }}">
                    @csrf
                    <input type="hidden" name="choice" value="{{ Consent::SEEN }}">
                    <button class="btn btn-sm btn-primary">Đã hiểu</button>
                </form>
            @endif
        </div>
    </div>
@endif
