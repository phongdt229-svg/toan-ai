@php
    $points = [
        'Tạo bài học, lý thuyết, ví dụ và câu hỏi ngay trên hệ thống',
        'Quản lý lớp, thêm học sinh bằng mã lớp',
        'Giao bài theo lớp hoặc theo từng học sinh, đặt hạn nộp',
        'Theo dõi ai đã làm, ai chưa, điểm bao nhiêu',
        'Lọc nhanh nhóm học sinh cần hỗ trợ',
        'Nhờ AI soạn nháp câu hỏi — bạn duyệt trước khi xuất bản',
    ];
@endphp

<section class="section section--muted">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-12 col-lg-6">
                <span class="badge text-bg-success mb-2">Dành cho giáo viên</span>
                <h2 class="section__title mb-2">Soạn bài nhanh hơn, nắm lớp rõ hơn</h2>
                <p class="section__subtitle mb-0">
                    AI chỉ tạo bản nháp. Nội dung nào lên hệ thống vẫn do giáo viên quyết định.
                </p>
            </div>

            <div class="col-12 col-lg-6">
                <ul class="list-unstyled d-grid gap-2 mb-0">
                    @foreach ($points as $line)
                        <li class="bg-white border rounded-3 px-3 py-2">
                            <i class="bi bi-check2 text-success me-2"></i>{{ $line }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>
