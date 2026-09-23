<?php

/*
|--------------------------------------------------------------------------
| Khung chương trình Toán 1–12 (GDPT 2018)
|--------------------------------------------------------------------------
| Đây là BỘ KHUNG: chương và chủ đề, KHÔNG phải nội dung bài học.
| Mục đích: giáo viên có chỗ để treo bài học và câu hỏi vào — không có chủ đề
| thì màn hình soạn bài không chọn được gì, đó là thứ đang chặn việc nhập nội dung.
|
| ⚠️ CẦN GIÁO VIÊN RÀ LẠI trước khi dùng thật: tên chương/chủ đề bám theo mạch
| kiến thức của chương trình 2018 nhưng mỗi bộ sách (Kết nối tri thức, Chân trời
| sáng tạo, Cánh diều) chia chương khác nhau. Sửa trực tiếp ở Quản trị → Chương trình.
|
| Chạy: php artisan db:seed --class=CurriculumSkeletonSeeder
| Idempotent — chạy lại không tạo trùng, và KHÔNG xoá chương/chủ đề bạn đã tự thêm.
*/

return [

    1 => [
        ['name' => 'Các số đến 10', 'topics' => [
            'Đếm và nhận biết số 0–10', 'So sánh các số trong phạm vi 10',
            'Nhiều hơn, ít hơn, bằng nhau', 'Thứ tự các số',
        ]],
        ['name' => 'Phép cộng, phép trừ trong phạm vi 10', 'topics' => [
            'Phép cộng trong phạm vi 10', 'Phép trừ trong phạm vi 10',
            'Mối quan hệ giữa phép cộng và phép trừ', 'Bài toán có lời văn đơn giản',
        ]],
        ['name' => 'Các số đến 100', 'topics' => [
            'Chục và đơn vị', 'Đọc, viết các số đến 100', 'So sánh các số đến 100',
        ]],
        ['name' => 'Phép cộng, phép trừ trong phạm vi 100', 'topics' => [
            'Cộng không nhớ trong phạm vi 100', 'Trừ không nhớ trong phạm vi 100',
        ]],
        ['name' => 'Hình học và Đo lường', 'topics' => [
            'Hình vuông, hình tròn, hình tam giác, hình chữ nhật',
            'Điểm, đoạn thẳng', 'Đo độ dài bằng xăng-ti-mét', 'Xem giờ đúng', 'Các ngày trong tuần',
        ]],
    ],

    2 => [
        ['name' => 'Các số đến 1000', 'topics' => [
            'Trăm, chục, đơn vị', 'Đọc, viết các số đến 1000', 'So sánh và xếp thứ tự các số',
        ]],
        ['name' => 'Phép cộng, phép trừ trong phạm vi 1000', 'topics' => [
            'Cộng có nhớ trong phạm vi 100', 'Trừ có nhớ trong phạm vi 100',
            'Cộng, trừ trong phạm vi 1000', 'Tìm thành phần chưa biết',
        ]],
        ['name' => 'Phép nhân, phép chia', 'topics' => [
            'Ý nghĩa phép nhân', 'Bảng nhân 2, 5', 'Ý nghĩa phép chia', 'Bảng chia 2, 5',
        ]],
        ['name' => 'Hình học và Đo lường', 'topics' => [
            'Đường thẳng, đường cong, đường gấp khúc', 'Hình tứ giác',
            'Đề-xi-mét, mét, ki-lô-mét', 'Ki-lô-gam, lít', 'Xem đồng hồ, xem lịch',
        ]],
        ['name' => 'Thống kê và Xác suất', 'topics' => [
            'Thu thập, phân loại số liệu', 'Biểu đồ tranh', 'Chắc chắn, có thể, không thể',
        ]],
    ],

    3 => [
        ['name' => 'Các số đến 100 000', 'topics' => [
            'Đọc, viết các số đến 10 000', 'Đọc, viết các số đến 100 000',
            'So sánh và làm tròn số', 'Số La Mã',
        ]],
        ['name' => 'Phép nhân, phép chia', 'topics' => [
            'Bảng nhân, bảng chia đã học', 'Nhân số có nhiều chữ số với số có một chữ số',
            'Chia số có nhiều chữ số cho số có một chữ số', 'Tìm thành phần chưa biết',
        ]],
        ['name' => 'Biểu thức và tính giá trị', 'topics' => [
            'Biểu thức số', 'Thứ tự thực hiện phép tính', 'Tính nhẩm và ước lượng',
        ]],
        ['name' => 'Hình học và Đo lường', 'topics' => [
            'Góc vuông, góc không vuông', 'Hình chữ nhật, hình vuông',
            'Chu vi hình chữ nhật, hình vuông', 'Diện tích một hình',
            'Mi-li-mét, gam, mi-li-lít', 'Tháng, năm và xem đồng hồ',
        ]],
        ['name' => 'Thống kê và Xác suất', 'topics' => [
            'Bảng số liệu', 'Biểu đồ tranh, biểu đồ cột', 'Khả năng xảy ra của một sự kiện',
        ]],
    ],

    4 => [
        ['name' => 'Số tự nhiên', 'topics' => [
            'Các số đến lớp triệu', 'So sánh và xếp thứ tự số tự nhiên',
            'Làm tròn số', 'Dãy số tự nhiên',
        ]],
        ['name' => 'Bốn phép tính với số tự nhiên', 'topics' => [
            'Cộng, trừ số có nhiều chữ số', 'Nhân với số có hai chữ số',
            'Chia cho số có hai chữ số', 'Tính chất giao hoán, kết hợp', 'Biểu thức có chứa chữ',
        ]],
        ['name' => 'Phân số', 'topics' => [
            'Khái niệm phân số', 'Phân số bằng nhau, rút gọn phân số',
            'Quy đồng mẫu số', 'So sánh phân số', 'Cộng, trừ phân số', 'Nhân, chia phân số',
        ]],
        ['name' => 'Hình học và Đo lường', 'topics' => [
            'Góc nhọn, góc tù, góc bẹt', 'Hai đường thẳng vuông góc, song song',
            'Hình bình hành, hình thoi', 'Diện tích hình bình hành, hình thoi',
            'Đề-xi-mét vuông, mét vuông', 'Yến, tạ, tấn · giây, thế kỷ',
        ]],
        ['name' => 'Thống kê và Xác suất', 'topics' => [
            'Dãy số liệu và biểu đồ cột', 'Số trung bình cộng', 'Sự kiện chắc chắn, có thể, không thể',
        ]],
    ],

    5 => [
        ['name' => 'Số thập phân', 'topics' => [
            'Khái niệm số thập phân', 'So sánh số thập phân', 'Cộng, trừ số thập phân',
            'Nhân, chia số thập phân', 'Làm tròn số thập phân',
        ]],
        ['name' => 'Tỉ số phần trăm', 'topics' => [
            'Khái niệm tỉ số, tỉ số phần trăm', 'Tìm tỉ số phần trăm của hai số',
            'Giải toán về tỉ số phần trăm',
        ]],
        ['name' => 'Hình học và Đo lường', 'topics' => [
            'Hình tam giác và diện tích', 'Hình thang và diện tích',
            'Hình tròn, chu vi và diện tích hình tròn',
            'Hình hộp chữ nhật, hình lập phương', 'Thể tích và các đơn vị đo thể tích',
        ]],
        ['name' => 'Chuyển động đều', 'topics' => [
            'Vận tốc', 'Quãng đường', 'Thời gian', 'Bài toán chuyển động',
        ]],
        ['name' => 'Thống kê và Xác suất', 'topics' => [
            'Thu thập và biểu diễn số liệu', 'Biểu đồ hình quạt tròn', 'Tỉ số mô tả khả năng xảy ra',
        ]],
    ],

    6 => [
        ['name' => 'Số tự nhiên', 'topics' => [
            'Tập hợp và phần tử', 'Phép tính với số tự nhiên', 'Luỹ thừa với số mũ tự nhiên',
            'Dấu hiệu chia hết', 'Số nguyên tố, hợp số', 'ƯCLN và BCNN',
        ]],
        ['name' => 'Số nguyên', 'topics' => [
            'Số nguyên âm và tập hợp số nguyên', 'Thứ tự trong tập số nguyên',
            'Cộng, trừ số nguyên', 'Nhân, chia số nguyên', 'Bội và ước của số nguyên',
        ]],
        ['name' => 'Phân số và số thập phân', 'topics' => [
            'Mở rộng khái niệm phân số', 'So sánh phân số',
            'Cộng, trừ, nhân, chia phân số', 'Số thập phân và các phép tính',
            'Tỉ số và tỉ số phần trăm', 'Hai bài toán về phân số',
        ]],
        ['name' => 'Hình học trực quan', 'topics' => [
            'Tam giác đều, hình vuông, lục giác đều', 'Hình chữ nhật, hình thoi, hình bình hành, hình thang cân',
            'Chu vi và diện tích các hình', 'Hình có trục đối xứng', 'Hình có tâm đối xứng',
        ]],
        ['name' => 'Hình học phẳng', 'topics' => [
            'Điểm, đường thẳng', 'Tia, đoạn thẳng, độ dài đoạn thẳng',
            'Trung điểm của đoạn thẳng', 'Góc và số đo góc',
        ]],
        ['name' => 'Thống kê và Xác suất', 'topics' => [
            'Thu thập và tổ chức dữ liệu', 'Biểu đồ tranh, biểu đồ cột, biểu đồ cột kép',
            'Kết quả có thể và sự kiện', 'Xác suất thực nghiệm',
        ]],
    ],

    7 => [
        ['name' => 'Số hữu tỉ', 'topics' => [
            'Tập hợp số hữu tỉ', 'Cộng, trừ, nhân, chia số hữu tỉ',
            'Luỹ thừa của một số hữu tỉ', 'Thứ tự thực hiện phép tính, quy tắc dấu ngoặc',
        ]],
        ['name' => 'Số thực', 'topics' => [
            'Số vô tỉ, căn bậc hai số học', 'Tập hợp số thực',
            'Giá trị tuyệt đối của một số thực', 'Làm tròn và ước lượng',
        ]],
        ['name' => 'Tỉ lệ thức và đại lượng tỉ lệ', 'topics' => [
            'Tỉ lệ thức', 'Dãy tỉ số bằng nhau',
            'Đại lượng tỉ lệ thuận', 'Đại lượng tỉ lệ nghịch',
        ]],
        ['name' => 'Biểu thức đại số', 'topics' => [
            'Biểu thức đại số', 'Đa thức một biến',
            'Cộng, trừ đa thức một biến', 'Nhân, chia đa thức một biến', 'Nghiệm của đa thức',
        ]],
        ['name' => 'Góc và đường thẳng song song', 'topics' => [
            'Góc ở vị trí đặc biệt', 'Tia phân giác của một góc',
            'Hai đường thẳng song song', 'Định lí và chứng minh định lí',
        ]],
        ['name' => 'Tam giác', 'topics' => [
            'Tổng ba góc của một tam giác', 'Hai tam giác bằng nhau',
            'Tam giác cân', 'Quan hệ giữa góc và cạnh trong tam giác',
            'Đường trung trực, đường trung tuyến, đường cao',
        ]],
        ['name' => 'Hình khối trong thực tiễn', 'topics' => [
            'Hình hộp chữ nhật, hình lập phương', 'Lăng trụ đứng tam giác, tứ giác',
            'Diện tích xung quanh và thể tích',
        ]],
        ['name' => 'Thống kê và Xác suất', 'topics' => [
            'Thu thập và phân loại dữ liệu', 'Biểu đồ hình quạt tròn, biểu đồ đoạn thẳng',
            'Biến cố', 'Xác suất của biến cố',
        ]],
    ],

    8 => [
        ['name' => 'Đa thức nhiều biến', 'topics' => [
            'Đơn thức, đa thức nhiều biến', 'Các phép tính với đa thức nhiều biến',
            'Hằng đẳng thức đáng nhớ', 'Phân tích đa thức thành nhân tử',
        ]],
        ['name' => 'Phân thức đại số', 'topics' => [
            'Phân thức đại số', 'Rút gọn phân thức',
            'Cộng, trừ phân thức', 'Nhân, chia phân thức',
        ]],
        ['name' => 'Phương trình bậc nhất một ẩn', 'topics' => [
            'Phương trình bậc nhất một ẩn', 'Giải bài toán bằng cách lập phương trình',
        ]],
        ['name' => 'Hàm số và đồ thị', 'topics' => [
            'Khái niệm hàm số', 'Mặt phẳng toạ độ',
            'Hàm số bậc nhất y = ax + b', 'Hệ số góc của đường thẳng',
        ]],
        ['name' => 'Tứ giác', 'topics' => [
            'Tứ giác và tổng các góc', 'Hình thang cân',
            'Hình bình hành', 'Hình chữ nhật', 'Hình thoi và hình vuông',
        ]],
        ['name' => 'Định lí Pythagore và tam giác đồng dạng', 'topics' => [
            'Định lí Pythagore', 'Định lí Thalès trong tam giác',
            'Đường trung bình của tam giác', 'Hai tam giác đồng dạng',
            'Các trường hợp đồng dạng của tam giác vuông',
        ]],
        ['name' => 'Hình khối và hình đồng dạng', 'topics' => [
            'Hình chóp tam giác đều, hình chóp tứ giác đều',
            'Diện tích xung quanh và thể tích hình chóp', 'Hình đồng dạng',
        ]],
        ['name' => 'Thống kê và Xác suất', 'topics' => [
            'Thu thập và phân tích dữ liệu', 'Biểu đồ và lựa chọn biểu đồ phù hợp',
            'Xác suất lí thuyết và xác suất thực nghiệm',
        ]],
    ],

    9 => [
        ['name' => 'Phương trình và hệ phương trình', 'topics' => [
            'Phương trình quy về phương trình bậc nhất',
            'Hệ hai phương trình bậc nhất hai ẩn', 'Giải hệ bằng phương pháp thế và cộng đại số',
            'Giải bài toán bằng cách lập hệ phương trình',
        ]],
        ['name' => 'Bất đẳng thức và bất phương trình', 'topics' => [
            'Bất đẳng thức', 'Bất phương trình bậc nhất một ẩn',
        ]],
        ['name' => 'Căn bậc hai và căn bậc ba', 'topics' => [
            'Căn bậc hai', 'Căn thức bậc hai và hằng đẳng thức',
            'Khai phương một tích, một thương', 'Biến đổi biểu thức chứa căn', 'Căn bậc ba',
        ]],
        ['name' => 'Hàm số y = ax² và phương trình bậc hai', 'topics' => [
            'Hàm số y = ax² và đồ thị', 'Phương trình bậc hai một ẩn',
            'Công thức nghiệm', 'Định lí Viète và ứng dụng',
        ]],
        ['name' => 'Hệ thức lượng trong tam giác vuông', 'topics' => [
            'Hệ thức về cạnh và đường cao', 'Tỉ số lượng giác của góc nhọn',
            'Hệ thức về cạnh và góc', 'Ứng dụng thực tế',
        ]],
        ['name' => 'Đường tròn', 'topics' => [
            'Đường tròn và tính chất', 'Vị trí tương đối của đường thẳng và đường tròn',
            'Tiếp tuyến của đường tròn', 'Góc ở tâm, góc nội tiếp',
            'Tứ giác nội tiếp', 'Độ dài cung tròn, diện tích hình quạt',
        ]],
        ['name' => 'Hình khối tròn xoay', 'topics' => [
            'Hình trụ', 'Hình nón', 'Hình cầu',
        ]],
        ['name' => 'Thống kê và Xác suất', 'topics' => [
            'Bảng tần số, tần số tương đối', 'Biểu đồ tần số',
            'Phép thử ngẫu nhiên và không gian mẫu', 'Xác suất của biến cố',
        ]],
    ],

    10 => [
        ['name' => 'Mệnh đề và tập hợp', 'topics' => [
            'Mệnh đề', 'Tập hợp và các phép toán trên tập hợp',
        ]],
        ['name' => 'Bất phương trình và hệ bất phương trình bậc nhất hai ẩn', 'topics' => [
            'Bất phương trình bậc nhất hai ẩn', 'Hệ bất phương trình bậc nhất hai ẩn',
            'Bài toán tối ưu đơn giản',
        ]],
        ['name' => 'Hệ thức lượng trong tam giác', 'topics' => [
            'Giá trị lượng giác của góc từ 0° đến 180°', 'Định lí côsin',
            'Định lí sin', 'Giải tam giác và ứng dụng',
        ]],
        ['name' => 'Vectơ', 'topics' => [
            'Khái niệm vectơ', 'Tổng và hiệu của hai vectơ',
            'Tích của vectơ với một số', 'Tích vô hướng của hai vectơ',
        ]],
        ['name' => 'Hàm số, đồ thị và ứng dụng', 'topics' => [
            'Khái niệm hàm số và đồ thị', 'Hàm số bậc hai',
            'Dấu của tam thức bậc hai', 'Bất phương trình bậc hai một ẩn',
            'Phương trình quy về phương trình bậc hai',
        ]],
        ['name' => 'Phương pháp toạ độ trong mặt phẳng', 'topics' => [
            'Toạ độ của vectơ', 'Đường thẳng trong mặt phẳng toạ độ',
            'Vị trí tương đối, góc và khoảng cách', 'Đường tròn trong mặt phẳng toạ độ',
            'Ba đường conic',
        ]],
        ['name' => 'Đại số tổ hợp', 'topics' => [
            'Quy tắc cộng và quy tắc nhân', 'Hoán vị, chỉnh hợp, tổ hợp',
            'Nhị thức Newton',
        ]],
        ['name' => 'Thống kê và Xác suất', 'topics' => [
            'Số gần đúng và sai số', 'Các số đặc trưng đo xu thế trung tâm',
            'Các số đặc trưng đo độ phân tán', 'Không gian mẫu và biến cố',
            'Xác suất của biến cố',
        ]],
    ],

    11 => [
        ['name' => 'Hàm số lượng giác và phương trình lượng giác', 'topics' => [
            'Góc lượng giác và giá trị lượng giác', 'Công thức lượng giác',
            'Hàm số lượng giác và đồ thị', 'Phương trình lượng giác cơ bản',
        ]],
        ['name' => 'Dãy số, cấp số cộng và cấp số nhân', 'topics' => [
            'Dãy số', 'Cấp số cộng', 'Cấp số nhân',
        ]],
        ['name' => 'Giới hạn và hàm số liên tục', 'topics' => [
            'Giới hạn của dãy số', 'Giới hạn của hàm số', 'Hàm số liên tục',
        ]],
        ['name' => 'Đạo hàm', 'topics' => [
            'Định nghĩa đạo hàm và ý nghĩa hình học', 'Quy tắc tính đạo hàm',
            'Đạo hàm của hàm số lượng giác', 'Đạo hàm cấp hai',
        ]],
        ['name' => 'Hàm số mũ và hàm số lôgarit', 'topics' => [
            'Luỹ thừa với số mũ thực', 'Lôgarit',
            'Hàm số mũ và hàm số lôgarit', 'Phương trình, bất phương trình mũ và lôgarit',
        ]],
        ['name' => 'Quan hệ song song trong không gian', 'topics' => [
            'Đường thẳng và mặt phẳng trong không gian', 'Hai đường thẳng song song',
            'Đường thẳng song song với mặt phẳng', 'Hai mặt phẳng song song', 'Phép chiếu song song',
        ]],
        ['name' => 'Quan hệ vuông góc trong không gian', 'topics' => [
            'Hai đường thẳng vuông góc', 'Đường thẳng vuông góc với mặt phẳng',
            'Hai mặt phẳng vuông góc', 'Khoảng cách trong không gian',
            'Thể tích khối lăng trụ, khối chóp',
        ]],
        ['name' => 'Thống kê và Xác suất', 'topics' => [
            'Mẫu số liệu ghép nhóm', 'Các số đặc trưng của mẫu số liệu ghép nhóm',
            'Biến cố hợp, biến cố giao, biến cố độc lập', 'Công thức cộng và nhân xác suất',
        ]],
    ],

    12 => [
        ['name' => 'Ứng dụng đạo hàm để khảo sát hàm số', 'topics' => [
            'Tính đơn điệu của hàm số', 'Cực trị của hàm số',
            'Giá trị lớn nhất, giá trị nhỏ nhất', 'Đường tiệm cận',
            'Khảo sát và vẽ đồ thị hàm số', 'Ứng dụng đạo hàm trong thực tiễn',
        ]],
        ['name' => 'Nguyên hàm và tích phân', 'topics' => [
            'Nguyên hàm', 'Tích phân', 'Phương pháp tính tích phân',
            'Ứng dụng tích phân tính diện tích và thể tích',
        ]],
        ['name' => 'Vectơ và hệ toạ độ trong không gian', 'topics' => [
            'Vectơ trong không gian', 'Hệ toạ độ Oxyz',
            'Biểu thức toạ độ của các phép toán vectơ',
        ]],
        ['name' => 'Phương pháp toạ độ trong không gian', 'topics' => [
            'Phương trình mặt phẳng', 'Phương trình đường thẳng',
            'Góc và khoảng cách trong không gian', 'Phương trình mặt cầu',
        ]],
        ['name' => 'Số phức', 'topics' => [
            'Khái niệm số phức', 'Các phép toán với số phức',
            'Biểu diễn hình học của số phức', 'Phương trình bậc hai với hệ số thực',
        ]],
        ['name' => 'Thống kê và Xác suất', 'topics' => [
            'Khoảng biến thiên, khoảng tứ phân vị của mẫu ghép nhóm',
            'Phương sai và độ lệch chuẩn của mẫu ghép nhóm',
            'Xác suất có điều kiện', 'Công thức xác suất toàn phần và công thức Bayes',
        ]],
    ],

];
