{{--
    Hàng biểu tượng mạng xã hội ở footer. Link nào chưa khai trong config/site.php thì ẩn luôn
    biểu tượng đó — dẫn người dùng tới một trang không tồn tại còn tệ hơn là không có link.
    Cả khối biến mất khi chưa khai link nào, nên bản demo không lòi ra dãy icon chết.
--}}
@php
    $socials = collect([
        ['key' => 'facebook', 'icon' => 'bi-facebook', 'label' => 'Facebook'],
        ['key' => 'youtube', 'icon' => 'bi-youtube', 'label' => 'YouTube'],
        ['key' => 'tiktok', 'icon' => 'bi-tiktok', 'label' => 'TikTok'],
        ['key' => 'x', 'icon' => 'bi-twitter-x', 'label' => 'X'],
        ['key' => 'google', 'icon' => 'bi-google', 'label' => 'Google'],
    ])->map(fn ($item) => $item + ['url' => config("site.social.{$item['key']}")])
      ->filter(fn ($item) => filled($item['url']));
@endphp

@if ($socials->isNotEmpty())
    <div class="social-links mt-3">
        @foreach ($socials as $item)
            {{--
                rel="noopener": tab mới không sờ được vào window.opener của mình.
                nofollow: không chuyển uy tín SEO sang trang ngoài.
                aria-label: nút chỉ có icon, trình đọc màn hình cần tên để đọc.
            --}}
            <a href="{{ $item['url'] }}" target="_blank" rel="noopener nofollow"
               class="social-links__item" aria-label="{{ $item['label'] }}" title="{{ $item['label'] }}">
                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
            </a>
        @endforeach
    </div>
@endif
