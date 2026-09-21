@extends('layouts.public')

@section('title', 'Hướng dẫn sử dụng — TOÁN AI')
@section('meta_description', 'Hướng dẫn sử dụng TOÁN AI cho học sinh, giáo viên và phụ huynh: học bài, giao bài, theo dõi con, gói học và thanh toán.')

@section('body')
    @include('public.partials.header')

    <main class="section">
        <div class="container">
            <div class="text-center mb-4">
                <span class="section__eyebrow"><i class="bi bi-book"></i>Hướng dẫn</span>
                <h1 class="section__title mb-2">Hướng dẫn <span class="hl">sử dụng</span></h1>
                <p class="section__subtitle mx-auto">
                    Chọn vai trò của bạn, hoặc gõ điều bạn đang muốn làm — ví dụ "giao bài", "mua gói", "quên mật khẩu".
                </p>
            </div>

            <form method="GET" class="guide-search mx-auto mb-4">
                <label for="q" class="visually-hidden">Tìm trong hướng dẫn</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-secondary"></i></span>
                    <input id="q" name="q" value="{{ $search }}" class="form-control border-start-0 ps-0"
                           placeholder="Bạn đang muốn làm gì?" autocomplete="off">
                    <button class="btn btn-primary px-4">Tìm</button>
                </div>
            </form>

            {{-- Lọc nhanh theo vai trò; đang lọc thì hiện nút bỏ lọc. --}}
            <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                <a href="{{ route('guides.index', array_filter(['q' => $search])) }}"
                   class="btn btn-sm {{ $audience ? 'btn-outline-secondary' : 'btn-primary' }}">Tất cả</a>
                @foreach ($audiences as $key => $meta)
                    <a href="{{ route('guides.index', array_filter(['q' => $search, 'doi-tuong' => $key])) }}"
                       class="btn btn-sm {{ $audience === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                        <i class="bi {{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
                    </a>
                @endforeach
            </div>

            @if ($search !== '')
                <p class="text-center text-secondary small">
                    {{ $total }} bài viết khớp với “{{ $search }}”.
                    @if ($total === 0)
                        Thử từ khoá khác, hoặc <a href="{{ route('support.create') }}">gửi yêu cầu hỗ trợ</a>.
                    @endif
                </p>
            @endif

            @foreach ($audiences as $key => $meta)
                @php $articles = $grouped[$key] ?? collect(); @endphp

                @if ($articles->isNotEmpty())
                    <div class="d-flex align-items-center gap-2 mt-4 mb-3">
                        <span class="feature-card__icon mb-0" style="width:38px;height:38px;font-size:1.125rem">
                            <i class="bi {{ $meta['icon'] }}"></i>
                        </span>
                        <h2 class="h5 fw-bold mb-0">{{ $meta['label'] }}</h2>
                        <span class="badge text-bg-light border">{{ $articles->count() }} bài</span>
                    </div>

                    <div class="row g-3">
                        @foreach ($articles as $article)
                            <div class="col-12 col-md-6 col-xl-4">
                                <a href="{{ route('guides.show', $article['slug']) }}" class="guide-card">
                                    <div class="feature-card__icon mb-0"><i class="bi {{ $article['icon'] }}"></i></div>
                                    <div>
                                        <div class="fw-semibold mb-1">{{ $article['title'] }}</div>
                                        <p class="text-secondary small mb-0">{{ $article['summary'] }}</p>
                                    </div>
                                    <i class="bi bi-chevron-right text-secondary ms-auto align-self-center"></i>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endforeach

            <div class="card border-primary mt-5">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <i class="bi bi-life-preserver text-primary" style="font-size:2rem"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Không tìm thấy điều bạn cần?</div>
                        <div class="text-secondary small">Gửi yêu cầu hỗ trợ, chúng tôi trả lời qua email trong vòng 7 ngày làm việc.</div>
                    </div>
                    <a href="{{ route('support.create') }}" class="btn btn-primary btn-touch">Gửi yêu cầu hỗ trợ</a>
                </div>
            </div>
        </div>
    </main>

    @include('public.partials.footer')
@endsection
