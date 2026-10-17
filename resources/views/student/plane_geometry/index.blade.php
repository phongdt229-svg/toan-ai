@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Hình học phẳng — TOÁN AI')
@section('page_title', 'Hình học phẳng')

@section('content')
    <p class="text-secondary small mb-3">
        Chọn công cụ rồi bấm thẳng lên lưới để vẽ — kéo điểm để chỉnh hình, cuộn để zoom. Công cụ
        tự học — không phải bài kiểm tra chấm điểm.
    </p>

    <div data-plane-geometry>
        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="plane-geometry__canvas" data-plane-canvas></div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card border">
                    <div class="card-body">
                        <h2 class="h6 fw-bold mb-2">Công cụ — bấm thẳng lên lưới</h2>
                        <div class="btn-group w-100 mb-2 flex-wrap" role="group" aria-label="Chọn công cụ">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-mode="point" aria-pressed="false">
                                Thêm điểm
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-mode="segment" aria-pressed="false">
                                Đoạn thẳng
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-mode="triangle" aria-pressed="false">
                                Tam giác
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-mode="circle" aria-pressed="false">
                                Đường tròn
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-mode="angle" aria-pressed="false">
                                Góc
                            </button>
                        </div>
                        <p class="text-secondary small mb-2" data-mode-hint></p>

                        <div class="mb-2">
                            <label class="form-label small mb-1" for="plane-color">Màu nét vẽ</label>
                            <input type="color" id="plane-color" value="#2563eb"
                                   data-plane-color class="form-control form-control-color">
                        </div>

                        <hr>
                        <h2 class="h6 fw-bold mb-2">Kết quả</h2>
                        <div data-plane-results></div>

                        <button type="button" data-clear-all
                                class="btn btn-sm btn-outline-danger w-100 mt-2 d-none">
                            Xoá tất cả điểm và hình đã vẽ
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/plane-geometry.js')
@endpush
