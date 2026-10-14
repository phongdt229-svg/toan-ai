@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Hình học không gian — TOÁN AI')
@section('page_title', 'Hình học không gian')

@section('content')
    <p class="text-secondary small mb-3">
        Chọn hình, nhập số liệu rồi kéo để xoay, cuộn (hoặc chụm hai ngón trên điện thoại) để zoom.
        Cần thêm điểm, vẽ đoạn thẳng hay đo một vùng thì dùng thanh công cụ phía trên khung hình
        (biểu tượng Điểm, Đoạn thẳng, Khoảng cách, Diện tích…). Công cụ tự học — không phải bài
        kiểm tra chấm điểm.
    </p>

    <div data-solid-geometry>
        <div class="d-flex gap-2 mb-3 overflow-auto pb-1">
            <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="box">Hình hộp chữ nhật</button>
            <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="cube">Hình lập phương</button>
            <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="pyramid3">Chóp tam giác đều</button>
            <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="pyramid4">Chóp tứ giác đều</button>
            <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="cylinder">Hình trụ</button>
            <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="cone">Hình nón</button>
            <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="sphere">Hình cầu</button>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div id="solid-geometry-canvas" class="solid-geometry__canvas" data-solid-canvas></div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card border">
                    <div class="card-body">
                        <h2 class="h6 fw-bold mb-2">Số liệu</h2>
                        <div data-solid-inputs></div>
                        <hr>
                        <h2 class="h6 fw-bold mb-2">Kết quả</h2>
                        <div data-solid-results></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('head')
    {{--
        GeoGebra 3D (chính thức, miễn phí) — dựng hình/đo đạc thay cho WebGL tự viết trước đó.
        CSP hiện không khai script-src nên không bị chặn (đúng quy ước đã dùng cho script GA ở
        layouts/base.blade.php) — xem App\Http\Middleware\SecurityHeaders.
    --}}
    <script src="https://www.geogebra.org/apps/deployggb.js"></script>
@endpush

@push('scripts')
    @vite('resources/js/geogebra-solid.js')
@endpush
