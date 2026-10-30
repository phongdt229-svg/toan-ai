{{-- Hội thoại mẫu với AI Tutor — dùng chung ở trang chủ và trang Học Toán với AI. --}}
<div class="chat-demo">
    <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
        <span class="feature-card__icon mb-0" style="width:34px;height:34px;font-size:1rem">
            <i class="bi bi-robot"></i>
        </span>
        <div class="small">
            <div class="fw-semibold">AI Tutor</div>
            <div class="text-success"><i class="bi bi-circle-fill" style="font-size:.5rem"></i> đang trực tuyến</div>
        </div>
    </div>

    {{-- MathML viết sẵn thay data-math — cùng lý do với hero: trang chủ không tải KaTeX. --}}
    <div class="d-flex flex-column gap-2">
        <div class="chat-demo__bubble chat-demo__bubble--me">Em ra 2/5 ạ, sai chỗ nào vậy?</div>
        <div class="chat-demo__bubble chat-demo__bubble--ai">
            Em đang cộng tử với tử, mẫu với mẫu. Nhưng <math class="math-static"><mfrac><mn>1</mn><mn>2</mn></mfrac></math>
            và <math class="math-static"><mfrac><mn>1</mn><mn>3</mn></mfrac></math>
            là hai phần bánh to nhỏ khác nhau — phải chia lại cho bằng nhau đã.
            Thử tìm mẫu số chung của 2 và 3 xem?
        </div>
        <div class="chat-demo__bubble chat-demo__bubble--me">Là 6 phải không ạ?</div>
        <div class="chat-demo__bubble chat-demo__bubble--ai">
            Chuẩn rồi. Giờ đổi cả hai phân số về mẫu 6 rồi cộng tử thử nhé.
        </div>
        <div class="chat-demo__bubble chat-demo__bubble--ai chat-demo__typing" aria-hidden="true">
            <span></span><span></span><span></span>
        </div>
    </div>
</div>
