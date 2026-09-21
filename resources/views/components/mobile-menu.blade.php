{{--
    Menu đầy đủ cho màn hình nhỏ. Thanh dưới chỉ chứa 4 mục hay dùng nhất, các mục còn lại
    (Đề kiểm tra, Lộ trình, Gói của tôi, Cài đặt…) vào đây — trước đây trên mobile không có lối nào mở chúng.
--}}
@php
    $portalLabels = [
        'student' => 'Học sinh',
        'teacher' => 'Giáo viên',
        'parent' => 'Phụ huynh',
        'admin' => 'Quản trị',
    ];
@endphp

<div class="offcanvas offcanvas-start mobile-menu d-lg-none" tabindex="-1" id="appMenu" aria-labelledby="appMenuLabel">
    <div class="offcanvas-header border-bottom align-items-start">
        <div>
            <x-brand size="sm" />
            <div class="small text-secondary mt-1" id="appMenuLabel">{{ $portalLabels[$portal] ?? $portal }}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Đóng menu"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column p-2">
        <nav class="d-grid gap-1">
            @foreach ($items as $item)
                @include('components.nav-item', ['item' => $item, 'style' => 'mobile'])
            @endforeach
        </nav>

        <hr class="my-2">

        <a href="{{ route('guides.index', ['doi-tuong' => $portal === 'admin' ? 'all' : $portal]) }}" class="mobile-menu__item">
            <i class="bi bi-book"></i><span>Hướng dẫn sử dụng</span>
        </a>
        <a href="{{ route('support.create') }}" class="mobile-menu__item">
            <i class="bi bi-life-preserver"></i><span>Gửi yêu cầu hỗ trợ</span>
        </a>

        <form method="POST" action="{{ route('logout') }}" class="mt-auto pt-2">
            @csrf
            <button type="submit" class="mobile-menu__item w-100 border-0 bg-transparent text-danger">
                <i class="bi bi-box-arrow-right"></i><span>Đăng xuất</span>
            </button>
        </form>
    </div>
</div>
