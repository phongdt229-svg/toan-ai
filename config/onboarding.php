<?php

/*
|--------------------------------------------------------------------------
| Hướng dẫn người mới (OB-01, OB-02) — nội dung, không có bảng DB (như config/guides.php)
|--------------------------------------------------------------------------
| checklists: việc "Bắt đầu" theo vai trò. `key` phải có điều kiện tương ứng trong OnboardingService::DONE —
|   mục tự tích khi dữ liệu thật thoả (không có nút đánh dấu tay). `route` chỉ ghi TÊN route vì config nạp trước route.
| tours: theo tên route của trang. `target` = giá trị thuộc tính data-tour="..." gắn trong view —
|   không dùng class CSS, đổi giao diện không làm gãy tour. Phần tử không có trên màn hình thì bước đó tự bỏ qua.
| OnboardingTest kiểm: route tồn tại, key có điều kiện, target có mặt trong view.
*/
return [

    'checklists' => [
        'student' => [
            ['key' => 'placement', 'label' => 'Làm bài kiểm tra đầu vào', 'hint' => '8 câu, 20 phút — để hệ thống xếp lộ trình vừa sức em.', 'route' => 'student.placement.intro'],
            ['key' => 'schedule', 'label' => 'Đặt lịch học trong tuần', 'hint' => 'Chọn ngày và giờ em ngồi vào học — học đều quan trọng hơn học dồn.', 'route' => 'student.schedule.edit'],
            ['key' => 'first_session', 'label' => 'Học xong buổi đầu tiên', 'hint' => 'Làm hết các mục của buổi 1 và bài kiểm tra ngắn cuối buổi.', 'route' => 'student.path.show'],
            ['key' => 'ai_tutor', 'label' => 'Hỏi AI Tutor một câu', 'hint' => 'Bấm nút robot ở góc màn hình, hoặc chụp ảnh đề bài.', 'route' => 'student.ai.index'],
            ['key' => 'link_parent', 'label' => 'Liên kết với phụ huynh', 'hint' => 'Gửi mã cho bố mẹ để bố mẹ theo dõi và động viên em.', 'route' => 'student.parents.index'],
        ],
        'parent' => [
            ['key' => 'link_child', 'label' => 'Liên kết tài khoản của con', 'hint' => 'Nhập mã con gửi, hoặc quét mã QR trong máy của con.', 'route' => 'parent.children.link'],
            ['key' => 'child_schedule', 'label' => 'Đặt lịch học cho con', 'hint' => 'Có lịch thì hệ thống mới biết con vào học đúng giờ hay vắng.', 'route' => 'parent.dashboard'],
            ['key' => 'view_child', 'label' => 'Xem báo cáo của con lần đầu', 'hint' => 'Thời gian học thật, điểm danh, chỉ số rủi ro và gợi ý phụ huynh nên làm gì.', 'route' => 'parent.dashboard'],
            ['key' => 'daily_report', 'label' => 'Bật báo cáo cuối ngày', 'hint' => 'Mỗi tối một thư tóm tắt — không bật thì vẫn có báo cáo tuần.', 'route' => 'parent.settings'],
        ],
        'teacher' => [
            ['key' => 'teacher_profile', 'label' => 'Hoàn thiện hồ sơ (trường, môn dạy)', 'hint' => 'Học sinh và phụ huynh thấy thông tin này khi vào lớp.', 'route' => 'teacher.settings'],
            ['key' => 'create_class', 'label' => 'Tạo lớp học đầu tiên', 'hint' => 'Mỗi lớp có mã tham gia để học sinh tự vào.', 'route' => 'teacher.classes.create'],
            ['key' => 'class_students', 'label' => 'Có học sinh vào lớp', 'hint' => 'Gửi mã lớp cho học sinh, hoặc thêm học sinh bằng email.', 'route' => 'teacher.classes.index'],
            ['key' => 'questions', 'label' => 'Soạn hoặc nhập câu hỏi', 'hint' => 'Tự soạn, nhập từ file CSV, hoặc nhờ AI soạn nháp rồi duyệt.', 'route' => 'teacher.questions.index'],
            ['key' => 'assignment', 'label' => 'Giao bài đầu tiên', 'hint' => 'Giao bộ câu hỏi, bài học hoặc đề kiểm tra cho cả lớp.', 'route' => 'teacher.assignments.create'],
        ],
    ],

    'tours' => [
        'student.dashboard' => [
            ['target' => 'nav-learn', 'title' => 'Học theo chương trình', 'text' => 'Chọn lớp → chương → bài học. Bài nào học xong sẽ được đánh dấu.'],
            ['target' => 'today-suggestion', 'title' => 'Gợi ý học hôm nay', 'text' => 'Mỗi ngày hệ thống chọn sẵn buổi học vừa sức em. Bấm vào là học, không phải tự tìm.'],
            ['target' => 'streak', 'title' => 'Chuỗi ngày học', 'text' => 'Mỗi ngày học thực từ 10 phút là giữ được chuỗi 🔥. Đừng để đứt nhé!'],
            ['target' => 'nav-path', 'title' => 'Lộ trình của em', 'text' => 'Lộ trình 4 giai đoạn xếp từ kết quả kiểm tra đầu vào, tự điều chỉnh theo bài em làm.'],
            ['target' => 'nav-schedule', 'title' => 'Lịch học', 'text' => 'Đặt giờ học cố định trong tuần. Bố mẹ cũng xem được lịch này.'],
            ['target' => 'ai-fab', 'title' => 'Hỏi AI Tutor', 'text' => 'Vướng bài thì hỏi ở đây — hoặc bấm 📷 để chụp đề. AI gợi ý từng bước, không làm bài hộ.'],
        ],
        'parent.dashboard' => [
            ['target' => 'child-card', 'title' => 'Con của bạn', 'text' => 'Bấm vào thẻ của con để xem thời gian học thật, điểm danh và báo cáo chi tiết.'],
            ['target' => 'nav-link-child', 'title' => 'Liên kết thêm con', 'text' => 'Nhập mã con gửi để theo dõi. Một phụ huynh theo dõi được nhiều con.'],
            ['target' => 'nav-parent-settings', 'title' => 'Thông báo & báo cáo', 'text' => 'Bật báo cáo cuối ngày, báo khi con bắt đầu / học xong buổi.'],
        ],
        'parent.children.show' => [
            ['target' => 'schedule-card', 'title' => 'Lịch học & điểm danh', 'text' => 'Đặt giờ học cho con. Đến giờ mà con chưa vào học, bạn sẽ được báo sau 15 phút.'],
            ['target' => 'risk-score', 'title' => 'Chỉ số rủi ro', 'text' => 'Xanh / vàng / đỏ tính từ 7 ngày gần nhất. Bấm "Vì sao?" để xem lý do.'],
            ['target' => 'interventions', 'title' => 'Phụ huynh có thể làm gì', 'text' => 'Gợi ý cụ thể theo tình hình của con tuần này.'],
            ['target' => 'activity-week', 'title' => 'Thời gian học thật', 'text' => '"Học thực" chỉ tính lúc con có thao tác — mở ứng dụng để đó không được tính.'],
        ],
        'teacher.dashboard' => [
            ['target' => 'nav-classes', 'title' => 'Lớp học', 'text' => 'Tạo lớp, lấy mã tham gia cho học sinh, xem báo cáo cả lớp.'],
            ['target' => 'nav-questions', 'title' => 'Ngân hàng câu hỏi', 'text' => 'Soạn câu hỏi có công thức toán, nhập từ file CSV, gắn chủ đề và độ khó.'],
            ['target' => 'nav-assign', 'title' => 'Giao bài', 'text' => 'Giao bộ câu hỏi, bài học hoặc đề kiểm tra, đặt hạn nộp. Hệ thống tự chấm câu trắc nghiệm.'],
            ['target' => 'nav-students', 'title' => 'Học sinh & rủi ro', 'text' => 'Cột "Rủi ro" cho biết em nào đang vắng nhiều, học dở dang hoặc điểm đi xuống.'],
            ['target' => 'nav-teacher-ai', 'title' => 'AI soạn bài', 'text' => 'Nhờ AI soạn nháp câu hỏi / bài học. Chỉ câu bạn duyệt mới được dùng.'],
        ],
    ],

];
