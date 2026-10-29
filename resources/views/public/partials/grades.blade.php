@php
    // Nhóm theo cấp học để phụ huynh tìm nhanh lớp của con.
    $levels = [
        ['Tiểu học', 'Lớp 1 → 5', 'grade-group__dot', $grades->where('level', '<=', 5)],
        ['THCS', 'Lớp 6 → 9', 'grade-group__dot--mid', $grades->whereBetween('level', [6, 9])],
        ['THPT', 'Lớp 10 → 12', 'grade-group__dot--high', $grades->where('level', '>=', 10)],
    ];
    // D-02: cấp học nằm ngoài phạm vi nhận học sinh (vd GRADE_MIN=6 → bỏ Tiểu học) thì không hiện.
    $levels = array_values(array_filter($levels, fn ($l) => $l[3]->isNotEmpty()));
@endphp

<section class="section section--muted" id="chuong-trinh">
    <div class="container">
        <div class="text-center mb-4">
            <span class="section__eyebrow"><i class="bi bi-diagram-3"></i>Chương trình</span>
            <h2 class="section__title mb-2">Chương trình Toán <span class="hl">lớp {{ \App\Models\Grade::rangeLabel() }}</span></h2>
            <p class="section__subtitle mx-auto">
                Mỗi lớp được chia theo chương → chủ đề → bài học, đi từ dễ đến khó.
            </p>
        </div>

        <div class="row g-3">
            @foreach ($levels as [$name, $range, $dotClass, $items])
                <div class="col-12 col-lg-{{ 12 / max(1, count($levels)) }}">
                    <div class="grade-group">
                        <div class="grade-group__label">
                            <span class="grade-group__dot {{ $dotClass }}"></span>
                            {{ $name }}
                            <span class="ms-auto small fw-normal text-secondary">{{ $range }}</span>
                        </div>

                        <div class="row row-cols-3 row-cols-sm-5 row-cols-lg-3 g-2">
                            @foreach ($items as $grade)
                                <div class="col">
                                    {{-- Trỏ tới chương trình công khai của lớp (GEO). Yêu cầu đăng nhập của §6
                                         nằm ở bước bấm vào BÀI HỌC — trang tổng quan bài hiện đúng câu đó. --}}
                                    <a href="{{ route('curriculum.grade', $grade) }}" class="grade-pill">{{ $grade->name }}</a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-center mt-3 mb-0">
            <a href="{{ route('curriculum.index') }}" class="small">
                Xem chi tiết chương trình từng lớp <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </p>
    </div>
</section>
