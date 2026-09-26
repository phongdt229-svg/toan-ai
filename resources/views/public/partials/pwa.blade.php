{{--
    Mục "Cài đặt ứng dụng" tách riêng thành một section nổi bật (nền tối, khác hẳn các khối
    trắng/xám xung quanh) thay vì chỉ một dòng chữ nhỏ trong hero — người đọc lướt nhanh vẫn
    thấy được đây là một tính năng thật, không phải chi tiết phụ.

    4 lợi ích liệt kê đều là thứ sw.js + manifest.webmanifest đang làm thật (network-first cho
    bài học đã mở, standalone display, cài không qua store) — không thêm cái nào chưa có,
    đúng luật "chỉ quảng cáo thứ có thật" (xem FooterSocialLinksTest).

    Nút #pwa-install-btn và #pwa-ios-hint được resources/js/pwa-install.js điều khiển
    (xem landing.blade.php: @vite('resources/js/pwa-install.js') chỉ nạp ở trang chủ).
--}}
@php
    $benefits = [
        ['bi-house-heart', 'Biểu tượng ngay trên màn hình chính, mở nhanh như app thật.'],
        ['bi-wifi-off', 'Bài học đã mở đọc lại được cả khi mất mạng.'],
        ['bi-window', 'Không thanh địa chỉ, giao diện gọn như ứng dụng di động.'],
        ['bi-lightning-charge', 'Cài trong vài giây, không cần qua App Store hay Google Play.'],
    ];
@endphp

<section class="pwa-band" id="cai-dat-ung-dung">
    <span class="pwa-band__glow pwa-band__glow--1" aria-hidden="true"></span>
    <span class="pwa-band__glow pwa-band__glow--2" aria-hidden="true"></span>

    <div class="container">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-12 col-lg-7">
                <span class="pwa-band__eyebrow"><i class="bi bi-phone"></i>Không cần App Store / Google Play</span>
                <h2 class="section__title mb-2">Cài TOÁN AI <span class="hl">như một ứng dụng</span> thật</h2>
                <p class="pwa-band__lead mb-4">
                    Thêm TOÁN AI vào màn hình chính điện thoại hoặc máy tính — mở nhanh như app thật,
                    không cần nhớ địa chỉ web hay mở trình duyệt trước.
                </p>

                <div class="row g-2 mb-4">
                    @foreach ($benefits as [$icon, $line])
                        <div class="col-12 col-sm-6">
                            <div class="pwa-band__benefit">
                                <i class="bi {{ $icon }}"></i>
                                <span class="small">{{ $line }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{--
                    Ẩn mặc định trong markup — pwa-install.js hiện nút ngay khi script chạy (không
                    chờ beforeinstallprompt, xem file JS), tránh loé nút ra rồi biến mất một nhịp.
                --}}
                <button type="button" id="pwa-install-btn" class="btn btn-light btn-lg btn-touch d-none">
                    <i class="bi bi-download me-1"></i>Cài đặt ứng dụng
                </button>
                <div id="pwa-ios-hint" class="pwa-band__hint mt-3 d-none">
                    <i class="bi bi-share me-1"></i>Trên iPhone: mở bằng Safari, bấm <strong class="text-white">Chia sẻ</strong>
                    → <strong class="text-white">Thêm vào MH chính</strong>.
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="pwa-band__phone">
                    <div class="pwa-band__tile">
                        <img src="/icons/icon-192.png" alt="Biểu tượng TOÁN AI" width="84" height="84" loading="lazy">
                    </div>
                    <div class="fw-semibold text-white">TOÁN AI</div>
                    <div class="pwa-band__hint">trên màn hình chính</div>
                </div>
            </div>
        </div>
    </div>
</section>
