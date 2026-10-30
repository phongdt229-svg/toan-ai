{{--
    "Cơ sở nội dung" ở trang chủ (E-E-A-T): ai biên soạn, sửa lúc nào, dựa trên văn bản/nghiên cứu nào.
    Chỉ đặt link đã kiểm tra mở được — trang /hoc-toan-voi-ai giải thích kỹ nghiên cứu PNAS.
--}}
{{-- Không dùng class .section: trang chủ snap theo .section (.landing-snap), khối nhỏ này không nên thành điểm dừng. --}}
<section class="pb-4" aria-label="Cơ sở nội dung">
    <div class="container" style="max-width:52rem">
        @include('public.partials.page-byline', ['updated' => $updated, 'class' => 'mb-2'])
        <p class="small text-secondary mb-0">
            <i class="bi bi-journal-bookmark me-1" aria-hidden="true"></i>Cơ sở nội dung:
            chương trình Toán theo
            <a href="https://caselaw.vn/van-ban-phap-luat/341253-thong-tu-so-32-2018-tt-bgddt-ngay-26-12-2018-cua-bo-truong-bo-giao-duc-va-dao-tao-ve-chuong-trinh-giao-duc-pho-thong" target="_blank" rel="noopener">Thông tư 32/2018/TT-BGDĐT</a>
            của Bộ Giáo dục và Đào tạo; cách AI Tutor gợi ý thay vì đưa đáp án dựa trên
            <a href="https://doi.org/10.1073/pnas.2422633122" target="_blank" rel="noopener">nghiên cứu đăng trên PNAS (2025)</a>
            — <a href="{{ route('ai-learning') }}">xem giải thích</a>.
        </p>
    </div>
</section>
