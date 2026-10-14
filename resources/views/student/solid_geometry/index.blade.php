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
                        <h2 class="h6 fw-bold mb-2">Công cụ — bấm thẳng lên hình</h2>
                        <div class="btn-group w-100 mb-2" role="group" aria-label="Chọn công cụ">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-mode="line">
                                Nối đường
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-mode="point">
                                Điểm trên đoạn
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-mode="region">
                                Đo vùng
                            </button>
                        </div>
                        <p class="text-secondary small mb-2" data-mode-hint></p>

                        <div data-point-ratio-field class="d-none mb-2">
                            <label class="form-label small mb-1">
                                Vị trí điểm mới (% tính từ điểm bấm trước)
                            </label>
                            <input type="number" min="0" max="100" step="1" value="50"
                                   data-point-ratio class="form-control form-control-sm" style="max-width:120px">
                        </div>

                        <div data-region-progress class="d-none mb-2">
                            <div class="small text-secondary mb-1">
                                Đã chọn: <span data-region-progress-list class="fw-bold"></span>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" data-region-finish class="btn btn-sm btn-success" disabled>
                                    Xong
                                </button>
                                <button type="button" data-region-cancel class="btn btn-sm btn-outline-secondary">
                                    Huỷ
                                </button>
                            </div>
                        </div>

                        <div data-solid-line-list></div>
                        <div data-solid-point-list></div>
                        <div data-solid-region-list></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/solid-geometry.js')
@endpush
