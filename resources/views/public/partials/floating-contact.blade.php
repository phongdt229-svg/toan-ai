{{--
    Nút nổi góc màn hình: chat Zalo/Facebook (mở app/web thật, không nhúng SDK — khỏi đụng
    CSP) + chia sẻ trang hiện tại. Zalo/Facebook ẩn nếu chưa cấu hình (config/site.php).
--}}
@php
    $zaloUrl = config('site.zalo_url');
    $facebookUrl = config('site.facebook_url');
@endphp

<div class="floating-contact">
    @if ($facebookUrl)
        <a href="{{ $facebookUrl }}" target="_blank" rel="noopener noreferrer"
           class="floating-contact__btn floating-contact__btn--facebook"
           aria-label="Chat hỗ trợ qua Messenger">
            <i class="bi bi-messenger" aria-hidden="true"></i>
        </a>
    @endif

    @if ($zaloUrl)
        <a href="{{ $zaloUrl }}" target="_blank" rel="noopener noreferrer"
           class="floating-contact__btn floating-contact__btn--zalo"
           aria-label="Chat hỗ trợ qua Zalo">
            <span aria-hidden="true">Zalo</span>
        </a>
    @endif

    <button type="button" id="share-page-btn"
            class="floating-contact__btn floating-contact__btn--share"
            data-share-url="{{ url()->current() }}"
            data-share-title="{{ config('site.brand') }}"
            aria-label="Chia sẻ trang này">
        <i class="bi bi-share-fill" aria-hidden="true"></i>
    </button>
</div>

@push('scripts')
<script>
document.getElementById('share-page-btn')?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const url = btn.dataset.shareUrl;
    const title = btn.dataset.shareTitle;

    // Điện thoại: mở khay chia sẻ của hệ điều hành — đã có sẵn Zalo/Messenger/... nếu cài.
    if (navigator.share) {
        try {
            await navigator.share({ title, url });
        } catch {
            /* người dùng bấm huỷ hộp thoại chia sẻ — không phải lỗi */
        }
        return;
    }

    // Desktop không có Web Share API: chép link để người dùng tự dán vào Zalo/Messenger/...
    try {
        await navigator.clipboard.writeText(url);
    } catch {
        const tmp = document.createElement('textarea');
        tmp.value = url;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        tmp.remove();
    }

    const original = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2" aria-hidden="true"></i>';
    btn.setAttribute('aria-label', 'Đã sao chép liên kết');
    setTimeout(() => {
        btn.innerHTML = original;
        btn.setAttribute('aria-label', 'Chia sẻ trang này');
    }, 2000);
});
</script>
@endpush
