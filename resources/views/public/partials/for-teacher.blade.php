@php
    $points = [
        ['bi-journal-plus', 'Tạo bài học, lý thuyết, ví dụ và câu hỏi ngay trên hệ thống'],
        ['bi-people', 'Quản lý lớp, thêm học sinh bằng mã lớp'],
        ['bi-send-check', 'Giao bài theo lớp hoặc theo từng học sinh, đặt hạn nộp'],
        ['bi-list-check', 'Theo dõi ai đã làm, ai chưa, điểm bao nhiêu'],
        ['bi-funnel', 'Lọc nhanh nhóm học sinh cần hỗ trợ'],
        ['bi-stars', 'Nhờ AI soạn nháp câu hỏi — bạn duyệt trước khi xuất bản'],
    ];
@endphp

<section class="section section--muted section--role section--role-teacher" id="giao-vien">
    <span class="section__role-blob section__role-blob--1" aria-hidden="true"></span>
    <span class="section__role-blob section__role-blob--2" aria-hidden="true"></span>

    <div class="container">
        <div class="row g-4 g-lg-5 align-items-center">
            <div class="col-12 col-lg-6">
                <span class="section__eyebrow section__eyebrow--success"><i class="bi bi-person-video3"></i>Dành cho giáo viên</span>
                <h2 class="section__title mb-2">Soạn bài <span class="hl">nhanh hơn</span>, nắm lớp <span class="hl">rõ hơn</span></h2>
                <p class="section__subtitle mb-4">
                    AI chỉ tạo bản nháp. Nội dung nào lên hệ thống vẫn do giáo viên quyết định.
                </p>

                <div class="audience-card audience-card--teacher">
                    <div class="audience-card__art">
                        @include('public.partials.illus.teacher')
                    </div>
                    <div class="audience-card__body pt-3">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge text-bg-light border"><i class="bi bi-bar-chart me-1"></i>Báo cáo lớp</span>
                            <span class="badge text-bg-light border"><i class="bi bi-file-earmark-arrow-down me-1"></i>Xuất CSV</span>
                            <span class="badge text-bg-light border"><i class="bi bi-upload me-1"></i>Nhập câu hỏi từ Excel</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <ul class="list-unstyled d-grid gap-2 mb-4">
                    @foreach ($points as [$icon, $line])
                        <li class="bg-white border rounded-3 px-3 py-2 d-flex align-items-start gap-2">
                            <i class="bi {{ $icon }} text-success mt-1"></i><span>{{ $line }}</span>
                        </li>
                    @endforeach
                </ul>

                <a href="{{ route('register.teacher') }}" class="btn btn-outline-primary btn-touch">
                    <i class="bi bi-person-plus"></i>Đăng ký tài khoản giáo viên
                </a>
            </div>
        </div>
    </div>
</section>
