@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Hình học không gian — TOÁN AI')
@section('page_title', 'Hình học không gian')

@section('content')
    <p class="text-secondary small mb-3">
        Chọn hình, nhập số liệu rồi kéo để xoay, cuộn (hoặc chụm hai ngón trên điện thoại) để zoom.
        Công cụ tự học — không phải bài kiểm tra chấm điểm.
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
                <div class="solid-geometry__canvas" data-solid-canvas></div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card border">
                    <div class="card-body">
                        <h2 class="h6 fw-bold mb-2">Số liệu</h2>
                        <div data-solid-inputs></div>
                        <hr>
                        <h2 class="h6 fw-bold mb-2">Kết quả</h2>
                        <div data-solid-results></div>

                        <hr>
                        <h2 class="h6 fw-bold mb-2">Vẽ thêm đoạn thẳng</h2>
                        <p class="text-secondary small mb-2">
                            Nối 2 điểm bất kỳ của hình — ví dụ đường chéo đáy, hay hạ đường cao từ đỉnh.
                            Bấm giữ một điểm trên hình rồi kéo sang điểm kia (nhả chuột để nối), hoặc
                            chọn tên điểm ở 2 ô dưới đây cho chính xác hơn.
                        </p>
                        <div class="d-flex align-items-end gap-2 mb-2">
                            <div class="flex-grow-1">
                                <label class="form-label small mb-1">Từ điểm</label>
                                <select data-line-from class="form-select form-select-sm"></select>
                            </div>
                            <div class="flex-grow-1">
                                <label class="form-label small mb-1">Đến điểm</label>
                                <select data-line-to class="form-select form-select-sm"></select>
                            </div>
                            <button type="button" data-add-line class="btn btn-sm btn-outline-secondary flex-shrink-0">
                                Thêm
                            </button>
                        </div>
                        <div data-solid-line-list></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/solid-geometry.js')
@endpush
