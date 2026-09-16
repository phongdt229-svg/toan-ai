@php
    $modes = [
        'Gợi ý từng bước — không lộ kết quả',
        'Giải thích lại theo cách dễ hiểu hơn',
        'Kiểm tra đáp án và chỉ ra lỗi sai',
        'Tạo bài tương tự để luyện thêm',
        'Phân tích lỗi để tìm kiến thức còn hổng',
    ];
@endphp

<section class="section section--muted" id="ai-tutor">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-12 col-lg-6">
                <h2 class="section__title mb-2">AI không đưa đáp án. AI giúp hiểu.</h2>
                <p class="section__subtitle mb-4">
                    Khi học sinh làm sai, AI phân tích sai ở bước nào, chỉ ra kiến thức bị hổng,
                    giải thích lại rồi cho một bài tương tự để luyện đúng chỗ đó.
                </p>

                <ul class="list-unstyled d-grid gap-2 mb-0">
                    @foreach ($modes as $line)
                        <li><i class="bi bi-check2-circle text-primary me-2"></i>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>

            <div class="col-12 col-lg-6">
                <div class="bg-white border rounded-4 p-3 p-md-4">
                    <div class="d-flex flex-column gap-3" data-math>
                        <div class="align-self-end bg-primary text-white rounded-3 px-3 py-2" style="max-width:85%">
                            Em ra 2/5 ạ, sai chỗ nào vậy?
                        </div>
                        <div class="align-self-start bg-light border rounded-3 px-3 py-2" style="max-width:90%">
                            Em đang cộng tử với tử, mẫu với mẫu. Nhưng $\frac{1}{2}$ và $\frac{1}{3}$
                            là hai phần bánh to nhỏ khác nhau — phải chia lại cho bằng nhau đã.
                            Thử tìm mẫu số chung của 2 và 3 xem?
                        </div>
                        <div class="align-self-end bg-primary text-white rounded-3 px-3 py-2" style="max-width:85%">
                            Là 6 phải không ạ?
                        </div>
                        <div class="align-self-start bg-light border rounded-3 px-3 py-2" style="max-width:90%">
                            Chuẩn rồi. Giờ đổi cả hai phân số về mẫu 6 rồi cộng tử thử nhé.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
