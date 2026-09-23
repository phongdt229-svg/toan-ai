{{--
    Chính sách bảo mật. Nội dung mô tả ĐÚNG những gì hệ thống đang làm (xem PROJECT_PLAN §29):
    dữ liệu học tập, AI Tutor gửi câu hỏi sang nhà cung cấp AI, thanh toán qua MoMo, audit log, sao lưu.
    Sửa tính năng nào có đụng tới dữ liệu cá nhân thì sửa trang này và cập nhật config('site.legal_updated_at').
    Trước khi phát hành thật: nhờ bộ phận pháp lý rà lại và điền thông tin đơn vị vận hành trong config/site.php.
--}}
@extends('layouts.public')

@section('title', 'Chính sách bảo mật — TOÁN AI')
@section('meta_description', 'TOÁN AI thu thập, sử dụng và bảo vệ dữ liệu của học sinh, phụ huynh, giáo viên như thế nào.')

@section('body')
    @include('public.partials.header')

    <main class="legal">
        <div class="container">
            <div class="legal__paper">
                <span class="badge text-bg-light border mb-2">Cập nhật {{ config('site.legal_updated_at') }}</span>
                <h1 class="h2 fw-bold mb-3">Chính sách bảo mật</h1>
                <p class="text-secondary">
                    TOÁN AI là nền tảng học Toán cho học sinh lớp 1–12. Phần lớn người dùng của chúng tôi là trẻ em,
                    nên việc thu thập và sử dụng dữ liệu được giữ ở mức tối thiểu cần cho việc học.
                    Trang này nói rõ chúng tôi giữ dữ liệu gì, dùng để làm gì và bạn kiểm soát được những gì.
                </p>

                <h2>1. Dữ liệu chúng tôi thu thập</h2>
                <ul>
                    <li><strong>Thông tin tài khoản:</strong> họ tên, email, số điện thoại (nếu bạn điền), mật khẩu đã băm, vai trò (học sinh / giáo viên / phụ huynh), lớp đang học.</li>
                    <li><strong>Dữ liệu học tập:</strong> bài đã học, câu trả lời từng câu hỏi, điểm, thời gian làm bài, mức độ thành thạo theo chủ đề, lộ trình học, kết quả kiểm tra đầu vào.</li>
                    <li><strong>Nội dung trao đổi với AI Tutor:</strong> câu hỏi bạn gửi, bài làm bạn dán vào và câu trả lời của AI.</li>
                    <li><strong>Dữ liệu thanh toán:</strong> gói đã mua, số tiền, mã đơn, trạng thái giao dịch và mã giao dịch do MoMo trả về.
                        Chúng tôi <strong>không</strong> lưu số thẻ, số ví hay mật khẩu thanh toán — phần đó do MoMo xử lý.</li>
                    <li><strong>Dữ liệu kỹ thuật:</strong> địa chỉ IP, loại trình duyệt, thời điểm đăng nhập, nhật ký thao tác quan trọng (duyệt giáo viên, đổi giá gói, khoá tài khoản, thanh toán).</li>
                </ul>

                <h2>2. Dùng để làm gì</h2>
                <ul>
                    <li>Cung cấp việc học: hiển thị bài, chấm bài, tính tiến độ, gợi ý nội dung phù hợp với chỗ còn yếu.</li>
                    <li>Cá nhân hoá lộ trình học và nội dung AI Tutor trả lời.</li>
                    <li>Gửi báo cáo học tập cho phụ huynh đã liên kết và email liên quan tới tài khoản, thanh toán.</li>
                    <li>Vận hành gói học: kích hoạt, gia hạn, xử lý khiếu nại giao dịch.</li>
                    <li>Bảo mật và chống gian lận: giới hạn số lần thử, phát hiện truy cập bất thường, truy vết sự cố.</li>
                </ul>
                <p>Chúng tôi <strong>không bán</strong> dữ liệu cá nhân và không dùng dữ liệu học tập của học sinh để chạy quảng cáo.</p>

                <h2>3. Chia sẻ với bên thứ ba</h2>
                <p>Chỉ chia sẻ ở mức cần thiết để dịch vụ chạy được:</p>
                <ul>
                    <li><strong>Nhà cung cấp mô hình AI:</strong> khi bạn dùng AI Tutor, nội dung câu hỏi và ngữ cảnh bài học được gửi tới nhà cung cấp AI để sinh câu trả lời. Không gửi kèm email, số điện thoại hay thông tin thanh toán.</li>
                    <li><strong>MoMo:</strong> xử lý thanh toán; nhận mã đơn và số tiền.</li>
                    <li><strong>Dịch vụ gửi email và hạ tầng máy chủ:</strong> để gửi thư và lưu trữ hệ thống.</li>
                    <li><strong>Google Analytics / Google Tag Manager:</strong> đo lượt truy cập và cách người dùng di chuyển giữa các trang, để biết chỗ nào khó dùng mà sửa. Google nhận địa chỉ IP (đã rút gọn), loại thiết bị, trình duyệt và trang bạn xem — <strong>không</strong> nhận tên, email, số điện thoại, điểm số hay nội dung bài làm của bạn.</li>
                    <li><strong>Cơ quan nhà nước có thẩm quyền</strong> khi có yêu cầu hợp pháp bằng văn bản.</li>
                </ul>

                <h2>4. Tài khoản của trẻ em và vai trò của phụ huynh</h2>
                <ul>
                    <li>Học sinh dưới 15 tuổi cần được cha mẹ hoặc người giám hộ đồng ý trước khi tạo tài khoản.</li>
                    <li>Phụ huynh liên kết với tài khoản của con bằng mã liên kết do chính học sinh chia sẻ. Học sinh có thể đổi mã hoặc gỡ liên kết bất cứ lúc nào.</li>
                    <li>Phụ huynh đã liên kết xem được tiến độ, điểm và báo cáo học tập của con; <strong>không</strong> xem được nội dung trò chuyện riêng với AI Tutor.</li>
                    <li>Phụ huynh có thể yêu cầu chúng tôi xoá tài khoản và dữ liệu học tập của con.</li>
                </ul>

                <h2>5. Lưu trữ và bảo vệ dữ liệu</h2>
                <ul>
                    <li>Mật khẩu được băm một chiều (bcrypt) — kể cả quản trị viên cũng không đọc được mật khẩu của bạn.</li>
                    <li>Kết nối tới hệ thống dùng HTTPS.</li>
                    <li>Phân quyền theo vai trò: giáo viên chỉ xem được học sinh lớp mình dạy, phụ huynh chỉ xem được con đã liên kết.</li>
                    <li>Thao tác quan trọng được ghi nhật ký (ai làm, lúc nào, từ IP nào) để truy vết khi có sự cố.</li>
                    <li>Dữ liệu được sao lưu định kỳ. Bản sao lưu được lưu giữ có thời hạn rồi xoá tự động.</li>
                    <li>Đổi mật khẩu sẽ đăng xuất mọi thiết bị đang đăng nhập.</li>
                </ul>

                <h2>6. Thời gian lưu giữ</h2>
                <ul>
                    <li>Dữ liệu tài khoản và học tập: lưu trong suốt thời gian tài khoản còn hoạt động.</li>
                    <li>Sau khi bạn yêu cầu xoá tài khoản: dữ liệu học tập được xoá hoặc ẩn danh trong vòng 30 ngày.</li>
                    <li>Hoá đơn và dữ liệu giao dịch: giữ theo thời hạn kế toán mà pháp luật yêu cầu.</li>
                    <li>Nhật ký kỹ thuật: giữ tối đa 12 tháng.</li>
                </ul>

                <h2>7. Quyền của bạn</h2>
                <ul>
                    <li>Xem và sửa thông tin cá nhân trong phần tài khoản.</li>
                    <li>Yêu cầu bản sao dữ liệu học tập của mình (hoặc của con, nếu là phụ huynh đã liên kết).</li>
                    <li>Yêu cầu xoá tài khoản và dữ liệu liên quan.</li>
                    <li>Rút lại sự đồng ý; khi đó một số chức năng sẽ ngừng hoạt động.</li>
                    <li>Khiếu nại nếu cho rằng dữ liệu bị sử dụng sai.</li>
                </ul>
                <p>Gửi yêu cầu tới <a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a>. Chúng tôi phản hồi trong vòng 7 ngày làm việc.</p>

                <h2>8. Cookie</h2>
                <p>Chúng tôi dùng hai nhóm cookie:</p>
                <ul>
                    <li><strong>Cookie bắt buộc:</strong> giữ phiên đăng nhập và ghi nhớ lựa chọn của bạn. Xoá đi sẽ khiến bạn bị đăng xuất.</li>
                    <li><strong>Cookie phân tích (Google Analytics):</strong> đếm lượt truy cập và đường đi giữa các trang. Đây là cookie của bên thứ ba và Google có thể nhận ra cùng một trình duyệt giữa các lần ghé thăm.</li>
                </ul>
                <p>
                    Lần đầu vào trang, bạn được hỏi có đồng ý cho nhóm cookie phân tích hay không. Chọn
                    "Chỉ cookie cần thiết" thì chúng tôi <strong>không nạp</strong> Google Analytics/Tag Manager —
                    không phải nạp rồi mới tắt. Đổi ý lúc nào cũng được bằng link <strong>"Cài đặt cookie"</strong> ở chân trang.
                </p>
                <p>
                    Chúng tôi <strong>không</strong> dùng cookie quảng cáo và không bán dữ liệu cho bên quảng cáo. Muốn chặn phần đo lường,
                    bạn có thể bật "Do Not Track" hoặc cài
                    <a href="https://tools.google.com/dlpage/gaoptout" rel="noopener nofollow" target="_blank">tiện ích từ chối Google Analytics</a>;
                    chặn nhóm này không ảnh hưởng gì tới việc học.
                </p>

                <h2>9. Thay đổi chính sách</h2>
                <p>
                    Khi có thay đổi quan trọng, chúng tôi cập nhật trang này và đổi ngày ở đầu trang; thay đổi lớn sẽ
                    được thông báo qua email hoặc thông báo trong ứng dụng.
                </p>

                <h2>10. Liên hệ</h2>
                <p class="mb-0">
                    {{ config('site.company') }}<br>
                    Email: <a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a>
                    @if (config('site.hotline'))<br>Hotline: {{ config('site.hotline') }}@endif
                    @if (config('site.address'))<br>Địa chỉ: {{ config('site.address') }}@endif
                </p>

                <hr class="my-4">
                <p class="small text-secondary mb-0">
                    Xem thêm: <a href="{{ route('legal.terms') }}">Điều khoản sử dụng</a>
                </p>
            </div>
        </div>
    </main>

    @include('public.partials.footer')
@endsection
