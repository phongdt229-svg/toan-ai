{{-- Khung công cụ hình 3D dùng chung: trang độc lập, trình soạn bài, trang xem bài/bài tập (qua route geometry.solid-markup). --}}
<div data-solid-geometry>
    <div data-edit-only class="d-flex gap-2 mb-3 overflow-auto pb-1" role="group" aria-label="Chọn hình">
        <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="box" aria-pressed="false">Hình hộp chữ nhật</button>
        <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="cube" aria-pressed="false">Hình lập phương</button>
        <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="pyramid3" aria-pressed="false">Chóp tam giác đều</button>
        <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="pyramid4" aria-pressed="false">Chóp tứ giác đều</button>
        <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="pyramidRect" aria-pressed="false">Chóp S.ABCD đáy chữ nhật</button>
        <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="prismRight" aria-pressed="false">Lăng trụ đứng</button>
        <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="tetraRight" aria-pressed="false">Tứ diện vuông</button>
        <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="cylinder" aria-pressed="false">Hình trụ</button>
        <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="cone" aria-pressed="false">Hình nón</button>
        <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-shape="sphere" aria-pressed="false">Hình cầu</button>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-8" data-canvas-col>
            <div class="solid-geometry__canvas" data-solid-canvas></div>
        </div>
        <div class="col-12 col-lg-4" data-edit-only>
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
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-mode="line" aria-pressed="false">
                            Nối đường
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-mode="point" aria-pressed="false">
                            Điểm trên đoạn
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-mode="region" aria-pressed="false">
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

                    <button type="button" data-clear-all
                            class="btn btn-sm btn-outline-danger w-100 mt-2 d-none">
                        Xoá tất cả đường/điểm/vùng đã thêm
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
