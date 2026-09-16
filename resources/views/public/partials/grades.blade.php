<section class="section section--muted" id="chuong-trinh">
    <div class="container">
        <h2 class="section__title mb-2">Chương trình Toán lớp 1 → 12</h2>
        <p class="section__subtitle mb-4">
            Mỗi lớp được chia theo chương → chủ đề → bài học, đi từ dễ đến khó.
        </p>

        <div class="row row-cols-3 row-cols-sm-4 row-cols-lg-6 g-2 g-sm-3">
            @foreach ($grades as $grade)
                <div class="col">
                    {{-- Chưa đăng nhập mà bấm vào bài học → yêu cầu đăng nhập (§6). --}}
                    <a href="{{ auth()->check() ? auth()->user()->homeRoute() : route('login') }}"
                       class="grade-pill">{{ $grade->name }}</a>
                </div>
            @endforeach
        </div>

        @guest
            <p class="text-secondary small mt-3 mb-0">
                <i class="bi bi-lock me-1"></i>Vui lòng đăng nhập để bắt đầu học.
            </p>
        @endguest
    </div>
</section>
