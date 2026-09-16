@php
    $hasRoute = ! empty($item['route']) && Route::has($item['route']);
    $url = $hasRoute ? route($item['route']) : '#';
    $active = $hasRoute && request()->routeIs($item['route']);
    $classes = $style === 'bottom' ? 'bottom-nav__item' : 'sidebar__item';
@endphp

<a href="{{ $url }}"
   class="{{ $classes }} {{ $active ? 'is-active' : '' }} {{ $hasRoute ? '' : 'opacity-50' }}"
   @unless ($hasRoute) aria-disabled="true" title="Sắp ra mắt" @endunless>
    <i class="bi {{ $item['icon'] }}"></i>
    <span>{{ $item['label'] }}</span>
</a>
