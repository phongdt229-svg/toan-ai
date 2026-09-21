<?php

/*
|--------------------------------------------------------------------------
| Nội dung Trung tâm hướng dẫn (/huong-dan)
|--------------------------------------------------------------------------
| Để nội dung ở đây (dữ liệu có cấu trúc) thay vì mỗi bài một file Blade:
| một khuôn hiển thị duy nhất → mọi bài cùng bố cục, và tìm kiếm chỉ cần
| quét mảng này. Thêm bài = thêm một phần tử, không phải sửa view.
|
| Mỗi bài:
|   audience : student | teacher | parent | all  (nhóm hiển thị ở trang chủ hướng dẫn)
|   steps    : [[tiêu đề bước, mô tả], ...]
|   tips     : mẹo / lưu ý thêm
|   warning  : cảnh báo quan trọng (hiện khung vàng)
|   links    : [[nhãn, tên route], ...] — view tự gọi route(), KHÔNG gọi route() trong config
|              (config nạp trước route nên sẽ lỗi).
*/

return [

    'audiences' => [
        'student' => ['label' => 'Học sinh', 'icon' => 'bi-mortarboard', 'tone' => 'primary'],
        'teacher' => ['label' => 'Giáo viên', 'icon' => 'bi-person-video3', 'tone' => 'success'],
        'parent' => ['label' => 'Phụ huynh', 'icon' => 'bi-house-heart', 'tone' => 'warning'],
        'all' => ['label' => 'Tài khoản & thanh toán', 'icon' => 'bi-gear', 'tone' => 'secondary'],
    ],

    'articles' => [

        // --- Học sinh ------------------------------------------------------------------

        'bat-dau-voi-toan-ai' => [
            'audience' => 'student',
            'icon' => 'bi-rocket-takeoff',
            'title' => 'Bắt đầu với TOÁN AI',
            'summary' => 'Tạo tài khoản học sinh, chọn lớp và biết mỗi mục trong menu dùng để làm gì.',
            'steps' => [
                ['Tạo tài khoản học sinh', 'Vào trang Đăng ký → chọn "Học sinh", điền họ tên, email, mật khẩu (ít nhất 8 ký tự, có cả chữ và số) và chọn lớp đang học.'],
                ['Chọn đúng lớp', 'Lớp quyết định chương trình em nhìn thấy. Chọn nhầm thì vào Cài đặt để đổi, tiến độ đã học vẫn được giữ.'],
                ['Xem trang chủ', 'Trang chủ hiển thị: gợi ý học hôm nay, bài được giao còn hạn, tiến độ lộ trình và những chủ đề em còn yếu.'],
                ['Đi theo menu bên trái', 'Học (bài giảng) · Bài tập (luyện tập theo chủ đề) · AI (hỏi bài) · Đề kiểm tra · Bài được giao · Lộ trình · Gói của tôi.'],
            ],
            'tips' => [
                'Trên điện thoại, các mục chính nằm ở thanh dưới màn hình.',
                'Cài TOÁN AI như một ứng dụng: mở trình duyệt → menu → "Thêm vào màn hình chính".',
            ],
            'links' => [
                ['Đăng ký tài khoản học sinh', 'register.student'],
                ['Kiểm tra đầu vào để nhận lộ trình', 'guides.show:kiem-tra-dau-vao-va-lo-trinh'],
            ],
        ],

        'kiem-tra-dau-vao-va-lo-trinh' => [
            'audience' => 'student',
            'icon' => 'bi-compass',
            'title' => 'Kiểm tra đầu vào và lộ trình học riêng',
            'summary' => 'Làm bài kiểm tra đầu vào khoảng 20 phút để hệ thống xếp lộ trình đúng sức học của em.',
            'steps' => [
                ['Mở kiểm tra đầu vào', 'Trang chủ có thẻ mời làm kiểm tra đầu vào. Em cũng có thể vào mục Lộ trình để bắt đầu.'],
                ['Làm bài trong thời gian cho phép', 'Đề gồm các câu tự chấm, trải đều các chủ đề của lớp. Đồng hồ chạy trên máy chủ nên đóng trình duyệt giữa chừng vẫn tính giờ.'],
                ['Xem kết quả', 'Sau khi nộp, em thấy điểm quy về thang 10, mức học lực, tốc độ làm bài và những chủ đề còn yếu.'],
                ['Nhận lộ trình', 'Hệ thống xếp lộ trình 4 giai đoạn: Nền tảng → Củng cố → Nâng cao → Luyện đề, chia thành từng buổi học ngắn.'],
                ['Học theo buổi', 'Mỗi buổi gồm vài mục (học bài, luyện tập). Xong các mục thì làm bài kiểm tra cuối buổi 5 câu.'],
            ],
            'tips' => [
                'Làm nghiêm túc, đừng tra đáp án — lộ trình dựa trên kết quả này.',
                'Lộ trình tự cập nhật: học xong bài, luyện đủ câu hoặc làm đề là các mục tự đánh dấu hoàn thành.',
                'Chủ đề nào tụt xuống mức yếu sẽ được chèn lại vào buổi kế tiếp để ôn.',
            ],
            'links' => [
                ['Bắt đầu kiểm tra đầu vào', 'student.placement.intro'],
                ['Xem lộ trình của tôi', 'student.path.show'],
            ],
        ],

        'hoc-bai-va-luyen-tap' => [
            'audience' => 'student',
            'icon' => 'bi-journal-text',
            'title' => 'Học bài và luyện tập',
            'summary' => 'Cách đọc bài giảng, đánh dấu hoàn thành và luyện tập theo chủ đề, theo độ khó.',
            'steps' => [
                ['Chọn bài để học', 'Vào mục Học → chọn chương → chủ đề → bài học. Bài nào thuộc gói cao hơn sẽ có biểu tượng khoá.'],
                ['Đọc theo từng phần', 'Một bài gồm: Lý thuyết → Ví dụ → Hiểu bản chất → Công thức → Lỗi thường gặp. Menu bên phải cho biết em đã đọc tới đâu.'],
                ['Đánh dấu hoàn thành', 'Đọc hết thì bấm "Hoàn thành bài học" — tiến độ và lộ trình cập nhật ngay.'],
                ['Luyện tập', 'Vào mục Bài tập → chọn chủ đề, độ khó và số câu. Hệ thống chấm ngay từng câu kèm lời giải.'],
                ['Xem lại chỗ sai', 'Ở trang kết quả, mỗi câu sai đều có nút hỏi AI: "Em sai ở đâu?" hoặc "Cho em bài tương tự".'],
            ],
            'tips' => [
                'Gói Free giới hạn số câu luyện tập mỗi ngày; hết lượt em vẫn học bài bình thường.',
                'Làm ít nhất 5 câu một chủ đề thì hệ thống mới đánh giá được mức thành thạo của em.',
            ],
            'links' => [
                ['Vào mục Học', 'student.learn.index'],
                ['Luyện tập ngay', 'student.practice.index'],
            ],
        ],

        'dung-ai-tutor' => [
            'audience' => 'student',
            'icon' => 'bi-robot',
            'title' => 'Dùng AI Tutor cho đúng',
            'summary' => 'AI gợi ý và giải thích để em tự làm được, chứ không đưa sẵn đáp án.',
            'steps' => [
                ['Mở AI Tutor', 'Bấm nút tròn ở góc dưới bên phải trên mọi trang học, hoặc vào mục AI trong menu.'],
                ['Chọn đúng kiểu hỏi', 'Gợi ý (không lộ đáp án) · Giải thích · Kiểm tra đáp án của em · Bài tương tự · Em sai ở đâu.'],
                ['Hỏi kèm bài làm', 'Dán cách làm của em vào. AI chỉ ra sai ở bước nào, thay vì chỉ nói kết quả đúng.'],
                ['Đối chiếu lại', 'Đọc xong lời giải thích, tự làm lại một lượt rồi mới xem đáp án.'],
            ],
            'tips' => [
                'Số lượt hỏi AI mỗi ngày phụ thuộc gói học; xem số lượt còn lại ở mục "Gói của tôi".',
                '"Phân tích lỗi sai" và "Bài tương tự" thuộc gói Premium.',
            ],
            'warning' => 'Đang làm bài kiểm tra thì AI bị khoá — để điểm phản ánh đúng sức học. '
                .'Với bài đang được giao hoặc đề chưa công bố đáp án, AI chỉ gợi ý chứ không giải.',
            'links' => [
                ['Mở AI Tutor', 'student.ai.index'],
            ],
        ],

        'bai-duoc-giao-va-de-kiem-tra' => [
            'audience' => 'student',
            'icon' => 'bi-clipboard-check',
            'title' => 'Làm bài được giao và đề kiểm tra',
            'summary' => 'Cách nộp bài đúng hạn, quy tắc bấm giờ và xem lại bài sau khi nộp.',
            'steps' => [
                ['Xem bài được giao', 'Mục Bài được giao liệt kê bài theo hạn nộp, bài quá hạn được đánh dấu đỏ.'],
                ['Làm bài', 'Bài giao có thể là: học một bài giảng, làm bộ câu hỏi hoặc làm một đề kiểm tra.'],
                ['Đề có bấm giờ', 'Khi bắt đầu đề, thời gian đếm trên máy chủ. Mất mạng hay đóng trình duyệt cũng không dừng đồng hồ; mở lại là làm tiếp.'],
                ['Nộp bài', 'Bấm Nộp bài. Hết giờ mà chưa nộp thì hệ thống tự nộp phần em đã làm.'],
                ['Xem lại', 'Sau khi nộp, xem điểm từng câu và lời giải. Câu tự luận chờ giáo viên chấm tay.'],
            ],
            'tips' => [
                'Câu trả lời được lưu tự động trong lúc làm, không lo mất bài khi lỡ thoát ra.',
            ],
            'links' => [
                ['Bài được giao', 'student.assignments.index'],
                ['Đề kiểm tra', 'student.exams.index'],
            ],
        ],

        'lien-ket-voi-phu-huynh' => [
            'audience' => 'student',
            'icon' => 'bi-house-heart',
            'title' => 'Liên kết tài khoản với phụ huynh',
            'summary' => 'Chia sẻ mã liên kết để bố mẹ theo dõi kết quả học — và gỡ liên kết khi cần.',
            'steps' => [
                ['Mở mục Phụ huynh', 'Trong menu học sinh, mục Phụ huynh hiển thị mã liên kết, link và mã QR của em.'],
                ['Đưa mã cho bố mẹ', 'Bố mẹ đăng nhập tài khoản phụ huynh → Liên kết con → nhập mã, hoặc quét mã QR.'],
                ['Kiểm tra danh sách', 'Sau khi liên kết, tên phụ huynh xuất hiện trong danh sách của em.'],
                ['Đổi mã hoặc gỡ liên kết', 'Bấm "Đổi mã" nếu lỡ chia sẻ nhầm; bấm gỡ để thu hồi quyền xem của một phụ huynh.'],
            ],
            'tips' => [
                'Phụ huynh xem được tiến độ, điểm và báo cáo học tập — không xem được nội dung em trò chuyện riêng với AI.',
            ],
            'links' => [
                ['Mở mục Phụ huynh', 'student.parents.index'],
            ],
        ],

        // --- Giáo viên -----------------------------------------------------------------

        'giao-vien-bat-dau' => [
            'audience' => 'teacher',
            'icon' => 'bi-person-badge',
            'title' => 'Giáo viên: đăng ký và tạo lớp',
            'summary' => 'Từ lúc đăng ký, chờ duyệt đến khi có lớp và học sinh trong lớp.',
            'steps' => [
                ['Đăng ký tài khoản giáo viên', 'Chọn "Giáo viên" khi đăng ký, khai báo trường và môn dạy.'],
                ['Chờ quản trị viên duyệt', 'Trong lúc chờ, bạn vẫn đăng nhập được nhưng chỉ thấy trang "Chờ duyệt". Bị từ chối sẽ có lý do kèm theo.'],
                ['Tạo lớp', 'Vào Lớp học → Tạo lớp mới, đặt tên và chọn khối lớp. Hệ thống sinh mã lớp gồm 6 ký tự.'],
                ['Thêm học sinh', 'Đưa mã lớp cho học sinh tự tham gia, hoặc thêm bằng email trong trang chi tiết lớp.'],
                ['Thêm giáo viên phụ', 'Một lớp có thể có giáo viên phụ để cùng theo dõi và giao bài.'],
            ],
            'tips' => [
                'Đổi mã lớp bất cứ lúc nào nếu mã bị chia sẻ ra ngoài.',
                'Lớp không dùng nữa thì lưu trữ thay vì xoá, để giữ lại điểm và lịch sử.',
            ],
            'links' => [
                ['Danh sách lớp', 'teacher.classes.index'],
            ],
        ],

        'soan-bai-hoc' => [
            'audience' => 'teacher',
            'icon' => 'bi-pencil-square',
            'title' => 'Soạn bài học và chèn công thức toán',
            'summary' => 'Dùng trình soạn thảo trực quan, chèn công thức bằng bảng ký hiệu, xuất bản khi hoàn thiện.',
            'steps' => [
                ['Tạo bài học', 'Bài học → Tạo mới: đặt tiêu đề, chọn chủ đề, độ khó, thời lượng và gói truy cập (Free / Pro / Premium).'],
                ['Thêm từng phần nội dung', 'Mỗi bài gồm nhiều phần theo thứ tự: Lý thuyết → Ví dụ → Hiểu bản chất → Công thức → Lỗi thường gặp.'],
                ['Soạn như soạn Word', 'Thanh công cụ có đậm, nghiêng, tiêu đề, danh sách, bảng, liên kết. Không cần biết HTML.'],
                ['Chèn công thức', 'Bấm "∑ Công thức" để mở bảng nhập: gõ 1/2 thành phân số, x^2 thành luỹ thừa, hoặc bấm ký hiệu có sẵn. Bấm vào công thức đã chèn để sửa lại.'],
                ['Xem như học sinh', 'Nút "Xem như học sinh" hiển thị đúng cách học sinh nhìn thấy trước khi lưu.'],
                ['Xuất bản', 'Bài chỉ hiện với học sinh sau khi bấm Xuất bản. Bài chưa có phần nội dung nào thì không xuất bản được.'],
            ],
            'tips' => [
                'Gõ nhanh: $x^2$ ngay trong ô soạn cũng tự thành công thức.',
                'Cần dán nội dung có sẵn dạng HTML thì bật chế độ "Xem / sửa mã HTML".',
            ],
            'links' => [
                ['Danh sách bài học', 'teacher.lessons.index'],
                ['Nhờ AI soạn nháp', 'guides.show:ai-soan-bai'],
            ],
        ],

        'ngan-hang-cau-hoi' => [
            'audience' => 'teacher',
            'icon' => 'bi-question-circle',
            'title' => 'Ngân hàng câu hỏi và nhập từ file',
            'summary' => 'Tạo câu hỏi đủ 6 dạng, gắn chủ đề và độ khó, hoặc nhập hàng loạt từ file mẫu.',
            'steps' => [
                ['Tạo câu hỏi', 'Câu hỏi → Tạo mới: chọn dạng (một đáp án, nhiều đáp án, đúng/sai, điền số, trả lời ngắn, tự luận).'],
                ['Gắn chủ đề và độ khó', 'Chủ đề quyết định câu hỏi xuất hiện ở phần luyện tập nào; độ khó dùng để xếp đề và tính mức thành thạo.'],
                ['Viết lời giải thích', 'Lời giải hiện cho học sinh sau khi chấm — nên viết ngắn, nêu rõ bước dễ sai.'],
                ['Nhập hàng loạt', 'Câu hỏi → Nhập từ file: tải file mẫu, điền theo cột rồi tải lên. Hệ thống báo rõ dòng nào lỗi.'],
                ['Xuất bản', 'Chỉ câu hỏi đã xuất bản mới được dùng cho luyện tập và đề kiểm tra.'],
            ],
            'tips' => [
                'Câu tự luận cần người chấm nên không đưa vào luyện tập tự động.',
                'Mỗi chủ đề nên có ít nhất 5–6 câu để phần luyện tập không bị lặp.',
            ],
            'links' => [
                ['Ngân hàng câu hỏi', 'teacher.questions.index'],
                ['Nhập câu hỏi từ file', 'teacher.questions.import'],
            ],
        ],

        'de-kiem-tra-va-giao-bai' => [
            'audience' => 'teacher',
            'icon' => 'bi-send-check',
            'title' => 'Tạo đề kiểm tra và giao bài',
            'summary' => 'Ra đề từ ngân hàng câu hỏi, đặt thời gian làm bài, giao cho lớp hoặc từng học sinh.',
            'steps' => [
                ['Tạo đề', 'Đề kiểm tra → Tạo mới: đặt tiêu đề, thời gian làm bài, mô tả.'],
                ['Thêm câu hỏi', 'Chọn thủ công từng câu, hoặc bốc ngẫu nhiên theo chủ đề và độ khó. Điểm từng câu chỉnh được.'],
                ['Xuất bản đề', 'Đề chỉ giao được sau khi xuất bản.'],
                ['Giao bài', 'Giao bài → Tạo mới: chọn lớp, chọn loại (bộ câu hỏi / học bài / đề kiểm tra), đặt hạn nộp.'],
                ['Chọn người nhận', 'Giao cho cả lớp hoặc chọn riêng vài học sinh cần củng cố.'],
                ['Chấm bài tự luận', 'Câu tự luận vào mục chấm tay; chấm xong điểm tổng mới hoàn tất.'],
            ],
            'tips' => [
                'Đặt hạn nộp để hệ thống tự đánh dấu bài quá hạn và nhắc trên trang của học sinh.',
                'Cho phép làm lại nếu muốn học sinh luyện đến khi đạt.',
            ],
            'links' => [
                ['Đề kiểm tra', 'teacher.exams.index'],
                ['Giao bài', 'teacher.assignments.index'],
            ],
        ],

        'theo-doi-va-bao-cao' => [
            'audience' => 'teacher',
            'icon' => 'bi-bar-chart',
            'title' => 'Theo dõi học sinh và báo cáo lớp',
            'summary' => 'Biết ai chưa làm bài, ai điểm thấp, lớp yếu chủ đề nào — và xuất bảng điểm.',
            'steps' => [
                ['Xem dashboard', 'Trang chủ giáo viên tóm tắt: số lớp, số học sinh, bài đang giao, điểm trung bình, học sinh cần hỗ trợ.'],
                ['Lọc học sinh', 'Mục Học sinh có các bộ lọc: Cần hỗ trợ · Chưa làm bài · Điểm thấp · Đang tiến bộ.'],
                ['Đọc báo cáo lớp', 'Mục Báo cáo: tỉ lệ nộp bài, điểm trung bình, tiến độ từng bài giao và biểu đồ chủ đề cả lớp còn yếu.'],
                ['Xuất bảng điểm', 'Nút "Xuất bảng điểm CSV" tải file mở được bằng Excel.'],
                ['Ghi nhận xét', 'Trong trang chi tiết học sinh, thêm nhận xét để lưu lại quá trình theo dõi.'],
            ],
            'tips' => [
                'Ngưỡng đánh dấu "cần hỗ trợ": từ 2 bài quá hạn chưa nộp, hoặc từ 2 chủ đề ở mức yếu.',
            ],
            'links' => [
                ['Báo cáo lớp', 'teacher.reports.index'],
                ['Danh sách học sinh', 'teacher.students.index'],
            ],
        ],

        'ai-soan-bai' => [
            'audience' => 'teacher',
            'icon' => 'bi-stars',
            'title' => 'Nhờ AI soạn nháp nội dung',
            'summary' => 'AI tạo bản nháp câu hỏi hoặc bài giảng; bạn duyệt từng mục trước khi xuất bản.',
            'steps' => [
                ['Mở AI soạn bài', 'Menu giáo viên → AI soạn bài.'],
                ['Đặt yêu cầu', 'Chọn chủ đề, độ khó, số lượng câu hỏi hoặc phần bài giảng cần soạn.'],
                ['Chờ xử lý', 'Kết quả chạy nền, xong sẽ hiện trong danh sách bản nháp.'],
                ['Duyệt từng mục', 'Với mỗi câu: Chấp nhận (đưa vào ngân hàng), Sửa lại, hoặc Bỏ.'],
                ['Kiểm tra trước khi xuất bản', 'Đọc kỹ đáp án và lời giải — AI có thể sai.'],
            ],
            'warning' => 'AI không bao giờ tự xuất bản nội dung. Mọi thứ AI tạo ra đều là bản nháp cho đến khi giáo viên duyệt.',
            'links' => [
                ['AI soạn bài', 'teacher.ai.index'],
            ],
        ],

        // --- Phụ huynh -----------------------------------------------------------------

        'phu-huynh-theo-doi-con' => [
            'audience' => 'parent',
            'icon' => 'bi-people',
            'title' => 'Phụ huynh: liên kết và theo dõi con',
            'summary' => 'Liên kết bằng mã của con, đọc báo cáo học tập và nhận email tổng kết mỗi tuần.',
            'steps' => [
                ['Tạo tài khoản phụ huynh', 'Đăng ký → chọn "Phụ huynh".'],
                ['Lấy mã từ con', 'Con mở mục Phụ huynh trong tài khoản học sinh để lấy mã, link hoặc mã QR.'],
                ['Liên kết', 'Vào Liên kết con → nhập mã. Quét QR thì chỉ cần xác nhận.'],
                ['Xem báo cáo', 'Mỗi con có một thẻ: tiến độ, điểm trung bình, thời gian học, bài chưa làm. Bấm vào để xem chi tiết.'],
                ['Nhận email hằng tuần', 'Tối Chủ nhật hệ thống gửi email tổng kết tuần. Bật/tắt trong Cài đặt.'],
            ],
            'tips' => [
                'Một học sinh có thể liên kết với nhiều phụ huynh (bố, mẹ, người giám hộ).',
                'Báo cáo nâng cao (biểu đồ từng ngày, đề xuất học riêng) thuộc gói Premium của con.',
            ],
            'links' => [
                ['Liên kết với con', 'parent.children.link'],
                ['Con của tôi', 'parent.dashboard'],
            ],
        ],

        'doc-bao-cao-cua-con' => [
            'audience' => 'parent',
            'icon' => 'bi-clipboard-data',
            'title' => 'Đọc báo cáo học tập của con',
            'summary' => 'Hiểu từng con số trong báo cáo: tiến độ, điểm trung bình, chủ đề mạnh - yếu và lộ trình.',
            'steps' => [
                ['Mở báo cáo', 'Trang "Con của tôi" → bấm vào thẻ của con để xem báo cáo đầy đủ.'],
                ['Tiến độ chương trình', 'Phần trăm bài học con đã hoàn thành trong chương trình của lớp — cho biết con đi được bao xa.'],
                ['Điểm trung bình', 'Tính trên các bài kiểm tra đã chấm xong, quy về thang 10. Chưa làm bài nào thì hiện dấu gạch ngang.'],
                ['Chủ đề mạnh và chủ đề yếu', 'Mức thành thạo theo từng chủ đề, tính từ kết quả làm bài thật. Chủ đề dưới 60% được xếp vào nhóm yếu.'],
                ['Lộ trình học', 'Cho biết con đang ở giai đoạn nào và đã học bao nhiêu buổi.'],
                ['Bài kiểm tra gần đây', 'Danh sách các bài con vừa làm kèm điểm, để biết con đang tiến bộ hay chững lại.'],
            ],
            'tips' => [
                'Mức thành thạo chỉ đáng tin khi con đã làm ít nhất 5 câu của chủ đề đó.',
                'Biểu đồ số câu làm mỗi ngày và phần đề xuất học riêng thuộc gói Premium của con.',
            ],
            'links' => [
                ['Con của tôi', 'parent.dashboard'],
                ['Tìm hiểu các gói học', 'guides.show:goi-hoc-va-thanh-toan'],
            ],
        ],

        'phu-huynh-nhan-thong-bao' => [
            'audience' => 'parent',
            'icon' => 'bi-envelope-paper',
            'title' => 'Email báo cáo tuần và thông báo',
            'summary' => 'Bật hoặc tắt email tổng kết mỗi tuần và chọn loại thông báo muốn nhận.',
            'steps' => [
                ['Mở Cài đặt', 'Menu phụ huynh → Cài đặt.'],
                ['Bật email báo cáo tuần', 'Tích "Nhận báo cáo tuần qua email". Thư gửi vào tối Chủ nhật, tóm tắt việc học của từng con trong tuần.'],
                ['Chọn loại thông báo', 'Chọn những việc muốn được báo trong ứng dụng, ví dụ khi con có kết quả bài kiểm tra mới.'],
                ['Kiểm tra hộp thư', 'Không thấy thư thì xem mục spam / quảng cáo và đánh dấu "không phải spam" để các thư sau vào đúng hộp.'],
            ],
            'tips' => [
                'Tuần nào con không học thì hệ thống không gửi thư, tránh làm phiền.',
                'Thông báo trong ứng dụng nằm ở biểu tượng chuông trên thanh trên cùng.',
            ],
            'links' => [
                ['Cài đặt phụ huynh', 'parent.settings'],
            ],
        ],

        // --- Chung: tài khoản, gói học, thanh toán --------------------------------------

        'goi-hoc-va-thanh-toan' => [
            'audience' => 'all',
            'icon' => 'bi-gem',
            'title' => 'Gói học và thanh toán MoMo',
            'summary' => 'So sánh các gói, mua cho mình hoặc cho con, và xử lý khi đã trừ tiền mà chưa lên gói.',
            'steps' => [
                ['Xem bảng giá', 'Trang Gói học liệt kê Free, Pro, Premium kèm quyền lợi và các kỳ hạn.'],
                ['Chọn gói', 'Bấm kỳ hạn muốn mua để tới trang xác nhận. Phụ huynh chọn thêm con được nhận gói.'],
                ['Kiểm tra trước khi trả tiền', 'Trang xác nhận ghi rõ: ai được dùng gói, giá, và ngày bắt đầu — hiệu lực ngay hay nối tiếp gói cũ.'],
                ['Thanh toán qua MoMo', 'Bấm "Thanh toán bằng MoMo" và làm theo hướng dẫn trên ứng dụng MoMo.'],
                ['Gói được kích hoạt', 'Ngay khi MoMo xác nhận, gói có hiệu lực. Bạn nhận email xác nhận kèm mã đơn.'],
            ],
            'tips' => [
                'Mua thêm gói cùng hạng khi gói cũ còn hạn thì thời hạn được cộng nối tiếp, không mất phần còn lại.',
                'Gói không tự động gia hạn. Hết hạn, tài khoản trở về Free và dữ liệu học vẫn được giữ.',
                'Xem lại mọi giao dịch ở mục "Lịch sử thanh toán".',
            ],
            'warning' => 'Đã trừ tiền mà chưa lên gói? Mở lại trang kết quả giao dịch — hệ thống tự hỏi lại MoMo. '
                .'Sau 10 phút vẫn chưa được, hãy gửi yêu cầu hỗ trợ kèm mã đơn.',
            'links' => [
                ['Xem bảng giá', 'packages.index'],
                ['Gửi yêu cầu hỗ trợ', 'support.create'],
            ],
        ],

        'tai-khoan-va-bao-mat' => [
            'audience' => 'all',
            'icon' => 'bi-shield-lock',
            'title' => 'Tài khoản, mật khẩu và bảo mật',
            'summary' => 'Đổi thông tin cá nhân, lấy lại mật khẩu và giữ tài khoản an toàn.',
            'steps' => [
                ['Cập nhật hồ sơ', 'Vào Cài đặt để đổi họ tên, số điện thoại, lớp đang học và tuỳ chọn thông báo.'],
                ['Quên mật khẩu', 'Ở trang đăng nhập bấm "Quên mật khẩu?", nhập email và mở link trong hộp thư (kiểm tra cả mục spam).'],
                ['Đặt mật khẩu mới', 'Link dùng được một lần trong 60 phút. Mật khẩu tối thiểu 8 ký tự, có cả chữ và số.'],
                ['Đăng xuất thiết bị khác', 'Đổi mật khẩu sẽ đăng xuất mọi thiết bị đang đăng nhập.'],
            ],
            'tips' => [
                'Mỗi tài khoản dành cho một người. Dùng chung tài khoản làm sai lệch tiến độ và có thể bị khoá.',
                'Tài khoản bị khoá không lấy lại mật khẩu được — hãy liên hệ hỗ trợ.',
            ],
            'links' => [
                ['Quên mật khẩu', 'password.request'],
            ],
        ],

        'cai-dat-ung-dung-va-dung-offline' => [
            'audience' => 'all',
            'icon' => 'bi-phone',
            'title' => 'Cài như ứng dụng và dùng khi mất mạng',
            'summary' => 'Đưa TOÁN AI ra màn hình chính điện thoại và đọc lại bài đã mở khi không có mạng.',
            'steps' => [
                ['Android / Chrome', 'Mở trang web → menu ba chấm → "Thêm vào màn hình chính".'],
                ['iPhone / Safari', 'Bấm nút Chia sẻ → "Thêm vào MH chính".'],
                ['Máy tính', 'Chrome/Edge hiện biểu tượng cài đặt ở thanh địa chỉ.'],
                ['Dùng khi mất mạng', 'Những bài học em đã mở vẫn đọc lại được. Trang offline liệt kê các bài đã lưu.'],
            ],
            'warning' => 'Làm bài, hỏi AI và thanh toán luôn cần mạng — những việc này phải qua máy chủ.',
            'tips' => [
                'Máy dùng chung: nhớ đăng xuất, hệ thống sẽ xoá các bài đã lưu offline.',
            ],
        ],

    ],
];
