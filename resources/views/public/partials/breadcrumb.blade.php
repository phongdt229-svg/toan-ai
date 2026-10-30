{{--
    Breadcrumb hiển thị + JSON-LD BreadcrumbList từ CÙNG một mảng — Google phạt trang khai một đằng hiện một nẻo,
    và kết quả tìm kiếm hiện "hoctoanonline.com › Tin tức › …" thay cho URL trần.

    $items: [[tên, url], ...] KHÔNG gồm "Trang chủ" (tự thêm); mục cuối là trang hiện tại.
    $schema = false khi trang đã khai BreadcrumbList trong @graph riêng (bài tin tức, chương trình) — tránh khai hai lần.
--}}
@php
    $crumbs = array_merge([['Trang chủ', route('home')]], $items);
    $last = count($crumbs) - 1;
@endphp

<nav aria-label="breadcrumb">
    <ol class="breadcrumb small {{ $class ?? 'mb-3' }}">
        @foreach ($crumbs as $i => [$name, $url])
            @if ($i === $last)
                <li class="breadcrumb-item active" aria-current="page">{{ $name }}</li>
            @else
                <li class="breadcrumb-item"><a href="{{ $url }}">{{ $name }}</a></li>
            @endif
        @endforeach
    </ol>
</nav>

@if ($schema ?? true)
    @push('head')
        {{-- @@context: xem layouts/base (Blade nuốt "@context"). --}}
        <script type="application/ld+json">
            {!! json_encode([
                '@@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_map(fn ($crumb, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $crumb[0],
                    'item' => $crumb[1],
                ], $crumbs, array_keys($crumbs)),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endpush
@endif
