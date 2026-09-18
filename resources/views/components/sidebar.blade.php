@php
    $portalLabels = [
        'student' => 'Học sinh',
        'teacher' => 'Giáo viên',
        'parent' => 'Phụ huynh',
        'admin' => 'Quản trị',
    ];
@endphp

<aside class="sidebar">
    <a href="{{ route('home') }}" class="sidebar__brand text-decoration-none">
        <x-brand variant="light" size="md" />
    </a>

    <div class="text-uppercase small mb-2" style="letter-spacing:.06em;opacity:.6">
        {{ $portalLabels[$portal] ?? $portal }}
    </div>

    <nav class="d-flex flex-column gap-1">
        @foreach ($items as $item)
            @include('components.nav-item', ['item' => $item, 'style' => 'sidebar'])
        @endforeach
    </nav>
</aside>
