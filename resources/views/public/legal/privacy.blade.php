{{--
    Chính sách bảo mật. Nội dung: bản admin soạn ở Quản trị → Chính sách ($custom, đã lọc HTML lúc lưu),
    không có thì bản mặc định ở _privacy-default (tự đổi theo cấu hình). Xem LegalPageService.
    Trước khi phát hành thật: nhờ bộ phận pháp lý rà lại và điền thông tin đơn vị vận hành.
--}}
@extends('layouts.public')

@section('title', 'Chính sách bảo mật — Math AI')
@section('meta_description', 'Math AI thu thập, sử dụng và bảo vệ dữ liệu của học sinh, phụ huynh, giáo viên như thế nào.')

@section('body')
    @include('public.partials.header')

    <main class="legal">
        <div class="container">
            <div class="legal__paper">
                @include('public.partials.breadcrumb', ['items' => [['Chính sách bảo mật', route('legal.privacy')]]])
                <span class="badge text-bg-light border mb-2">Cập nhật {{ $updatedLabel }}</span>
                <h1 class="h2 fw-bold mb-3">Chính sách bảo mật</h1>

                @if ($custom)
                    {!! $custom->content !!}
                @else
                    @include('public.legal._privacy-default')
                @endif

                <hr class="my-4">
                <p class="small text-secondary mb-0">
                    Xem thêm: <a href="{{ route('legal.terms') }}">Điều khoản sử dụng</a>
                </p>
            </div>
        </div>
    </main>

    @include('public.partials.footer')
@endsection
