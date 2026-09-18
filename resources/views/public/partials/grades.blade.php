@php
    // Nhóm theo cấp học để phụ huynh tìm nhanh lớp của con.
    $levels = [
        ['Tiểu học', 'Lớp 1 → 5', 'grade-group__dot', $grades->where('level', '<=', 5)],
        ['THCS', 'Lớp 6 → 9', 'grade-group__dot--mid', $grades->whereBetween('level', [6, 9])],
        ['THPT', 'Lớp 10 → 12', 'grade-group__dot--high', $grades->where('level', '>=', 10)],
    ];
    $target = auth()->check() ? auth()->user()->homeRoute() : route('login');
@endphp

<section class="section section--muted" id="chuong-trinh">
    <div class="container">
        <div class="text-center mb-4">
            <span class="section__eyebrow"><i class="bi bi-diagram-3"></i>Chương trình</span>
            <h2 class="section__title mb-2">Chương trình Toán lớp 1 → 12</h2>
            <p class="section__subtitle mx-auto">
                Mỗi lớp được chia theo chương → chủ đề → bài học, đi từ dễ đến khó.
            </p>
        </div>

        <div class="row g-3">
            @foreach ($levels as [$name, $range, $dotClass, $items])
                <div class="col-12 col-lg-4">
                    <div class="grade-group">
                        <div class="grade-group__label">
                            <span class="grade-group__dot {{ $dotClass }}"></span>
                            {{ $name }}
                            <span class="ms-auto small fw-normal text-secondary">{{ $range }}</span>
                        </div>

                        <div class="row row-cols-3 row-cols-sm-5 row-cols-lg-3 g-2">
                            @foreach ($items as $grade)
                                <div class="col">
                                    {{-- Chưa đăng nhập mà bấm vào bài học → yêu cầu đăng nhập (§6). --}}
                                    <a href="{{ $target }}" class="grade-pill">{{ $grade->name }}</a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @guest
            <p class="text-secondary small mt-3 mb-0 text-center">
                <i class="bi bi-lock me-1"></i>Vui lòng đăng nhập để bắt đầu học.
            </p>
        @endguest
    </div>
</section>
