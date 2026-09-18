{{--
    Điều khoản sử dụng. Mô tả đúng cách hệ thống đang vận hành (gói học §18–19, thanh toán MoMo §20–25,
    AI Tutor §10). Đổi chính sách giá / hoàn tiền thì sửa trang này và config('site.legal_updated_at').
    Trước khi phát hành thật: nhờ bộ phận pháp lý rà lại và điền thông tin đơn vị vận hành trong config/site.php.
--}}
@extends('layouts.base')

@section('title', 'Điều khoản sử dụng — TOÁN AI')
@section('meta_description', 'Điều khoản sử dụng dịch vụ học Toán trực tuyến TOÁN AI: tài khoản, gói học, thanh toán, quyền và nghĩa vụ.')

@section('body')
    @include('public.partials.header')

    <main class="legal">
        <div class="container">
            <div class="legal__paper">
                <span class="badge text-bg-light border mb-2">Cập nhật {{ config('site.legal_updated_at') }}</span>
                <h1 class="h2 fw-bold mb-3">Điều khoản sử dụng</h1>
                <p class="text-secondary">
                    Khi tạo tài khoản hoặc sử dụng TOÁN AI, bạn đồng ý với các điều khoản dưới đây.
                    Nếu người dùng là học sinh chưa đủ 15 tuổi, cha mẹ hoặc người giám hộ là người đồng ý thay.
                </p>

                <h2>1. Dịch vụ</h2>
                <p>
                    TOÁN AI cung cấp bài học, bài luyện tập, đề kiểm tra, lộ trình học cá nhân hoá và trợ lý AI cho môn Toán
                    lớp 1–12; kèm công cụ cho giáo viên (soạn bài, giao bài, theo dõi lớp) và cho phụ huynh (theo dõi việc học của con).
                    Chúng tôi có thể thêm, sửa hoặc ngừng một tính năng; thay đổi ảnh hưởng tới gói đang trả phí sẽ được báo trước.
                </p>

                <h2>2. Tài khoản</h2>
                <ul>
                    <li>Bạn chịu trách nhiệm về tính chính xác của thông tin đăng ký và về việc giữ bí mật mật khẩu.</li>
                    <li>Mỗi tài khoản dành cho <strong>một người</strong>. Không chia sẻ tài khoản trả phí cho nhiều người dùng chung.</li>
                    <li>Tài khoản giáo viên phải được quản trị viên duyệt trước khi dùng được các chức năng giảng dạy.</li>
                    <li>Hãy báo ngay cho chúng tôi nếu bạn nghi ngờ tài khoản bị người khác sử dụng.</li>
                </ul>

                <h2>3. Những việc không được làm</h2>
                <ul>
                    <li>Sao chép, tải hàng loạt hoặc phát tán lại bài học, câu hỏi, đề kiểm tra của hệ thống.</li>
                    <li>Dùng công cụ tự động để cào dữ liệu, dò mã lớp, dò mã liên kết phụ huynh hoặc gây quá tải hệ thống.</li>
                    <li>Gian lận trong kiểm tra: nhờ người khác làm hộ, dùng nhiều tài khoản, can thiệp vào bài làm đang chấm.</li>
                    <li>Đăng nội dung vi phạm pháp luật, xúc phạm người khác hoặc không phù hợp với môi trường giáo dục.</li>
                    <li>Tìm cách truy cập dữ liệu của người dùng khác hoặc phần quản trị khi không được cấp quyền.</li>
                </ul>
                <p>Vi phạm có thể dẫn tới khoá tài khoản. Với tài khoản bị khoá do vi phạm, chúng tôi không hoàn tiền phần gói còn lại.</p>

                <h2>4. Nội dung và bản quyền</h2>
                <ul>
                    <li>Bài học, câu hỏi, đề kiểm tra và giao diện thuộc quyền sở hữu của {{ config('site.company') }} hoặc đối tác cấp phép.</li>
                    <li>Bạn được dùng nội dung cho mục đích học tập cá nhân hoặc giảng dạy trong lớp của mình, không dùng cho mục đích thương mại khác.</li>
                    <li>Nội dung do giáo viên tự soạn trên hệ thống vẫn thuộc về giáo viên đó; giáo viên cho phép chúng tôi lưu trữ và hiển thị nội dung đó cho học sinh của mình.</li>
                </ul>

                <h2>5. Về trợ lý AI</h2>
                <ul>
                    <li>AI Tutor hỗ trợ gợi ý và giải thích, <strong>không thay thế giáo viên</strong>.</li>
                    <li>Câu trả lời của AI <strong>có thể sai</strong>. Hãy đối chiếu với bài học và hỏi lại giáo viên khi cần.</li>
                    <li>Đúng/sai của bài làm luôn do hệ thống chấm theo đáp án trong ngân hàng câu hỏi, không do AI quyết định.</li>
                    <li>Nội dung do AI tạo cho giáo viên là bản nháp; giáo viên phải đọc và duyệt trước khi cho học sinh dùng.</li>
                    <li>Số lượt dùng AI mỗi ngày phụ thuộc gói bạn đang dùng.</li>
                </ul>

                <h2>6. Gói học và thanh toán</h2>
                <ul>
                    <li>Giá của từng gói hiển thị tại trang <a href="{{ route('packages.index') }}">Gói học</a> và là giá đã bao gồm thuế (nếu có).</li>
                    <li>Thanh toán qua ví MoMo. Gói được kích hoạt ngay khi MoMo xác nhận giao dịch thành công.</li>
                    <li>Phụ huynh có thể mua gói cho con đã liên kết; quyền lợi gói thuộc về tài khoản học sinh.</li>
                    <li>Mua thêm gói cùng hạng khi gói cũ còn hạn thì thời hạn được <strong>cộng nối tiếp</strong>, không mất phần còn lại.</li>
                    <li>Gói <strong>không tự động gia hạn</strong>. Hết hạn, tài khoản trở về gói Free và dữ liệu học tập vẫn được giữ.</li>
                    <li>Đổi giá không ảnh hưởng tới gói bạn đã mua trước đó.</li>
                </ul>

                <h2>7. Hoàn tiền</h2>
                <ul>
                    <li>Nếu bị trừ tiền mà gói không được kích hoạt, chúng tôi kiểm tra lại với MoMo và kích hoạt hoặc hoàn tiền đầy đủ.</li>
                    <li>Yêu cầu hoàn tiền trong vòng <strong>7 ngày</strong> kể từ khi thanh toán và khi bạn dùng dưới 20% thời hạn gói sẽ được xem xét.</li>
                    <li>Gửi yêu cầu kèm mã đơn tới <a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a>; chúng tôi phản hồi trong 7 ngày làm việc.</li>
                </ul>

                <h2>8. Tạm ngừng và chấm dứt</h2>
                <p>
                    Bạn có thể ngừng sử dụng và yêu cầu xoá tài khoản bất cứ lúc nào. Chúng tôi có thể tạm khoá tài khoản
                    khi phát hiện vi phạm hoặc dấu hiệu gian lận, và sẽ nêu lý do khi khoá.
                </p>

                <h2>9. Giới hạn trách nhiệm</h2>
                <p>
                    Chúng tôi cố gắng để dịch vụ hoạt động liên tục nhưng không cam kết không bao giờ gián đoạn
                    (bảo trì, sự cố nhà cung cấp hạ tầng, thiên tai, mất điện, mất mạng). Chúng tôi không chịu trách nhiệm
                    cho kết quả thi cử, điểm số hay các thiệt hại gián tiếp phát sinh từ việc sử dụng dịch vụ.
                    Trong mọi trường hợp, trách nhiệm tối đa của chúng tôi không vượt quá số tiền bạn đã trả trong 12 tháng gần nhất.
                </p>

                <h2>10. Luật áp dụng</h2>
                <p>
                    Điều khoản này được điều chỉnh bởi pháp luật Việt Nam. Tranh chấp được ưu tiên giải quyết bằng thương lượng;
                    nếu không đạt được thoả thuận, vụ việc sẽ do toà án có thẩm quyền tại Việt Nam giải quyết.
                </p>

                <h2>11. Thay đổi điều khoản</h2>
                <p>
                    Chúng tôi có thể cập nhật điều khoản và sẽ đổi ngày ở đầu trang. Tiếp tục sử dụng dịch vụ sau khi
                    điều khoản thay đổi nghĩa là bạn đồng ý với bản mới.
                </p>

                <h2>12. Liên hệ</h2>
                <p class="mb-0">
                    {{ config('site.company') }}<br>
                    Email: <a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a>
                    @if (config('site.hotline'))<br>Hotline: {{ config('site.hotline') }}@endif
                    @if (config('site.address'))<br>Địa chỉ: {{ config('site.address') }}@endif
                </p>

                <hr class="my-4">
                <p class="small text-secondary mb-0">
                    Xem thêm: <a href="{{ route('legal.privacy') }}">Chính sách bảo mật</a>
                </p>
            </div>
        </div>
    </main>

    @include('public.partials.footer')
@endsection
