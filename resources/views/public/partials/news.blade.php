{{--
    6 bài tin tức mới nhất trên trang chủ. Chỉ hiện khi đã có bài đã xuất bản (Blog vừa thêm
    26/09) — trang chủ không quảng cáo mục trống. Thẻ dùng lại đúng kiểu .feature-card ở
    public.blog.index để nhất quán, chỉ thêm ảnh bìa.
--}}
@if ($latestPosts->isNotEmpty())
    <section class="section" id="tin-tuc">
        <div class="container">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-4">
                <div>
                    <span class="section__eyebrow"><i class="bi bi-newspaper"></i>Tin tức</span>
                    <h2 class="section__title mb-0">Mới nhất từ <span class="hl">TOÁN AI</span></h2>
                </div>
                <a href="{{ route('blog.index') }}" class="btn btn-outline-primary btn-sm">
                    Xem tất cả <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="row g-3">
                @foreach ($latestPosts as $post)
                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="{{ route('blog.show', $post) }}" class="feature-card d-block h-100 text-decoration-none text-body">
                            @if ($post->coverUrl())
                                <img src="{{ $post->coverUrl() }}" alt="" class="w-100 rounded-3 mb-3"
                                     style="aspect-ratio:16/9;object-fit:cover">
                            @endif
                            <span class="badge text-bg-light border mb-2">{{ $post->category->name }}</span>
                            <h3 class="h6 fw-bold">{{ $post->title }}</h3>
                            <div class="text-secondary small">{{ $post->published_at->format('d/m/Y') }}</div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
