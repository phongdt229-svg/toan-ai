@php
    $modes = [
        ['bi-lightbulb', 'Gợi ý từng bước — không lộ kết quả'],
        ['bi-chat-square-text', 'Giải thích lại theo cách dễ hiểu hơn'],
        ['bi-check2-square', 'Kiểm tra đáp án và chỉ ra lỗi sai'],
        ['bi-arrow-repeat', 'Tạo bài tương tự để luyện thêm'],
        ['bi-search-heart', 'Phân tích lỗi để tìm kiến thức còn hổng'],
    ];
@endphp

<section class="section section--muted" id="ai-tutor">
    <div class="container">
        <div class="row g-4 g-lg-5 align-items-center">
            <div class="col-12 col-lg-6">
                <span class="section__eyebrow"><i class="bi bi-robot"></i>AI Tutor</span>
                <h2 class="section__title mb-2">AI <span class="hl--mark">không đưa đáp án</span>. AI <span class="hl">giúp hiểu</span>.</h2>
                <p class="section__subtitle mb-4">
                    Khi học sinh làm sai, AI phân tích sai ở bước nào, chỉ ra kiến thức bị hổng,
                    giải thích lại rồi cho một bài tương tự để luyện đúng chỗ đó.
                </p>

                <div class="row g-2">
                    @foreach ($modes as [$icon, $line])
                        <div class="col-12 col-sm-6">
                            <div class="d-flex align-items-start gap-2 bg-white border rounded-3 px-3 py-2 h-100">
                                <i class="bi {{ $icon }} text-primary"></i>
                                <span class="small">{{ $line }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="small text-secondary mt-3 mb-0">
                    <i class="bi bi-shield-check me-1"></i>Đang làm bài kiểm tra thì AI bị khoá — để điểm phản ánh đúng sức học.
                </p>
            </div>

            <div class="col-12 col-lg-6">
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

                    <div class="d-flex flex-column gap-2">
                        <div class="chat-demo__bubble chat-demo__bubble--me">Em ra 2/5 ạ, sai chỗ nào vậy?</div>
                        <div class="chat-demo__bubble chat-demo__bubble--ai">
                            Em đang cộng tử với tử, mẫu với mẫu. Nhưng <span class="katex"><span class="katex-html" aria-hidden="true"><span class="base"><span class="strut" style="height:1.1901em;vertical-align:-0.345em;"></span><span class="mord"><span class="mopen nulldelimiter"></span><span class="mfrac"><span class="vlist-t vlist-t2"><span class="vlist-r"><span class="vlist" style="height:0.8451em;"><span style="top:-2.655em;"><span class="pstrut" style="height:3em;"></span><span class="sizing reset-size6 size3 mtight"><span class="mord mtight"><span class="mord mtight">2</span></span></span></span><span style="top:-3.23em;"><span class="pstrut" style="height:3em;"></span><span class="frac-line" style="border-bottom-width:0.04em;"></span></span><span style="top:-3.394em;"><span class="pstrut" style="height:3em;"></span><span class="sizing reset-size6 size3 mtight"><span class="mord mtight"><span class="mord mtight">1</span></span></span></span></span><span class="vlist-s">​</span></span><span class="vlist-r"><span class="vlist" style="height:0.345em;"><span></span></span></span></span></span><span class="mclose nulldelimiter"></span></span></span></span></span> và <span class="katex"><span class="katex-html" aria-hidden="true"><span class="base"><span class="strut" style="height:1.1901em;vertical-align:-0.345em;"></span><span class="mord"><span class="mopen nulldelimiter"></span><span class="mfrac"><span class="vlist-t vlist-t2"><span class="vlist-r"><span class="vlist" style="height:0.8451em;"><span style="top:-2.655em;"><span class="pstrut" style="height:3em;"></span><span class="sizing reset-size6 size3 mtight"><span class="mord mtight"><span class="mord mtight">3</span></span></span></span><span style="top:-3.23em;"><span class="pstrut" style="height:3em;"></span><span class="frac-line" style="border-bottom-width:0.04em;"></span></span><span style="top:-3.394em;"><span class="pstrut" style="height:3em;"></span><span class="sizing reset-size6 size3 mtight"><span class="mord mtight"><span class="mord mtight">1</span></span></span></span></span><span class="vlist-s">​</span></span><span class="vlist-r"><span class="vlist" style="height:0.345em;"><span></span></span></span></span></span><span class="mclose nulldelimiter"></span></span></span></span></span>
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
            </div>
        </div>
    </div>
</section>
