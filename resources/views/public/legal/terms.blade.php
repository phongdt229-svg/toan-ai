{{--
    Điều khoản sử dụng. Nội dung: bản admin soạn ở Quản trị → Chính sách ($custom, đã lọc HTML lúc lưu),
    không có thì bản mặc định ở _terms-default. Xem LegalPageService.
    Trước khi phát hành thật: nhờ bộ phận pháp lý rà lại và điền thông tin đơn vị vận hành.
--}}
@extends('layouts.public')

@section('title', 'Điều khoản sử dụng — Math AI')
@section('meta_description', 'Điều khoản sử dụng dịch vụ học Toán trực tuyến Math AI: tài khoản, gói học, thanh toán, quyền và nghĩa vụ.')

@section('body')
    @include('public.partials.header')

    <main class="legal">
        <div class="container">
            <div class="legal__paper">
                @include('public.partials.breadcrumb', ['items' => [['Điều khoản sử dụng', route('legal.terms')]]])
                <span class="badge text-bg-light border mb-2">Cập nhật {{ $updatedLabel }}</span>
                <h1 class="h2 fw-bold mb-3">Điều khoản sử dụng</h1>

                @if ($custom)
                    {!! $custom->content !!}
                @else
                    @include('public.legal._terms-default')
                @endif

                <hr class="my-4">
                <p class="small text-secondary mb-0">
                    Xem thêm: <a href="{{ route('legal.privacy') }}">Chính sách bảo mật</a>
                </p>
            </div>
        </div>
    </main>

    @include('public.partials.footer')
@endsection
