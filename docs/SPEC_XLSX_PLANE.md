# Đặc tả chức năng & Logic — tổng hợp để đưa lên Plane

Nguồn: `Đặc tả chức năng & Logic.xlsx` (3 sheet: **Task**, **Các module**, **Logic**), đối chiếu với code ngày **07/10/2026**.

> ⚠️ **Sheet "Task" không mô tả repo này.** Nó nói về một bản khác (runtime MongoDB/Mongoose, "token", "AI provider registry/CRUD",
> "Mobile app 0%", "Subscription/billing 0%"...). Repo `toan-ai` là Laravel 12 + MariaDB, đã có gói học + MoMo + hoàn tiền,
> quên mật khẩu, báo cáo tuần phụ huynh. **Đừng nhập nguyên sheet Task lên Plane** — dùng danh sách việc ở mục 3 bên dưới.

---

## 1. Tóm tắt đặc tả

**Hệ thống:** nền tảng học Toán online cá nhân hoá bằng AI, giúp học sinh cải thiện tư duy và điểm số qua lộ trình riêng.

**5 việc chính:** lưu hồ sơ học sinh chi tiết · đánh giá đầu vào · tạo giáo trình cá nhân hoá ·
theo dõi tiến độ + kiểm tra sau mỗi buổi · chatbot thầy/cô hỏi đáp, giải bài từng bước.

**User flow:** Đăng ký → Nhập thông tin → Test đầu vào → AI phân tích → Tạo giáo trình → Dashboard →
Học bài → Test 15 phút → AI gợi ý tiếp → Lặp lại.

### 11 module trong đặc tả

| # | Module | Yêu cầu chính |
|---|---|---|
| 1 | Đăng ký | Họ tên, ngày sinh, địa chỉ, email, SĐT, trường, khối lớp, học lực tự khai, điểm TB Toán, chọn thầy/cô, màu yêu thích, sở thích |
| 2 | Kiểm tra đầu vào | Đề 5–10 câu (trắc nghiệm + tự luận) theo lớp + học lực + điểm TB; chấm + phân tích lỗi; xếp loại ≤5 TB · ≤8 Khá · >8 Giỏi; xác định nhóm kiến thức yếu, tốc độ, mức độ hiểu |
| 3 | Tạo giáo trình (core) | Lý thuyết theo chuyên đề; bài tập Dễ→TB→Khó tự điều chỉnh; lộ trình 4 giai đoạn (Nền tảng · Củng cố · Nâng cao · Luyện đề); AI Tutor thầy/cô |
| 4 | Dashboard tiến độ | % hoàn thành, buổi đã học/còn lại, điểm TB, nhóm kiến thức yếu, "Gợi ý học hôm nay" (bài học + bài tập) |
| 5 | Kiểm tra cuối buổi | **15 phút**, theo bài hôm đó, chấm tự động → điểm, phân tích lỗi, đề xuất học tiếp / ôn lại |
| 6 | Gợi ý học tiếp | Điểm cao → bài mới · TB → luyện thêm · thấp → ôn lại |
| 7 | AI Solver | Nhập đề **text / ảnh**, giải từng bước, gợi ý không lộ đáp án ngay, bài tương tự |
| 8 | Trang cá nhân | Theme theo màu yêu thích, avatar AI thầy/cô, dashboard theo học lực; nâng cao: nội dung theo sở thích, microcopy theo tính cách |
| 9 | Thông báo | HS: nhắc học hằng ngày, cảnh báo sắp quên bài, gợi ý AI, **streak**. PH: nhắc lịch học, **báo vắng mặt**, % hoàn thành, buổi đã/còn, điểm TB, kiến thức yếu |
| 10 | User flow | (như trên) |
| 11 | Thanh toán | **VNPAY**, MoMo |

### Sheet "Logic" — các luật thuật toán

1. **Phân loại học lực 2 tầng:** rule cứng từ điểm TB + AI hiệu chỉnh theo bài test thật (điểm test, thời gian, lỗi theo chủ đề).
   Điểm TB chỉ là *tín hiệu tham khảo*. Đầu ra nên là: năng lực tổng quát, năng lực theo chuyên đề, mức độ tự học, tốc độ xử lý.
2. **Tạo đề đầu vào:** input lớp, học lực tự khai, điểm TB, trường, *kết quả các câu trước* (đề thích ứng); 5–10 câu, phủ chủ đề nền tảng, đủ phân loại.
3. **Tạo giáo trình:** input lớp, học lực, kỹ năng mạnh/yếu, thời lượng học dự kiến, mục tiêu → giáo trình, module, lesson theo buổi, bài tập, quiz 15 phút.
4. **Gợi ý bài hôm nay:** quiz ≥ 8 → mở bài mới · 5 ≤ quiz < 8 → bài mới + 30% ôn · quiz < 5 → ôn trước.
5. **Adaptive thật:** chấm theo nhiều tín hiệu (điểm quiz, số lần xin gợi ý, sai rồi sửa đúng, thời gian, lỗi lặp, mức quên, độ ổn định 3–5 buổi).
   Mỗi buổi: **20% ôn phần dễ quên · 60% bài mới · 20% củng cố lỗi sai gần đây.**
6. **Solver chống lệ thuộc:** gợi mở trước, chỉ bung lời giải đầy đủ khi cần, theo dõi học sinh đang học hay chỉ xin đáp án.
7. **Thời gian học chủ động:** `effective_study_time` = tổng thời gian có tương tác hợp lệ (vào lesson, scroll, click làm bài, trả lời,
   chat, xin gợi ý, nộp quiz); trừ idle / rời tab. Báo "online 90 phút, học thực 8 phút, tập trung thấp".
8. **Điểm danh 3 trạng thái:** Present (active ≥ 70% chuẩn + có quiz) · Partial (< 70% hoặc bỏ quiz) · Absent.
9. **Giám sát phụ huynh (6 mục):** lịch học hôm nay · trạng thái tham gia (vào chưa, trễ, vắng) · thời gian học thật + mức tập trung ·
   kết quả buổi · cảnh báo bất thường · gợi ý can thiệp.
10. **Luật cảnh báo PH:** chưa vào học sau 15' → báo PH · vào nhưng không active 10' → nguy cơ bỏ buổi · không nộp quiz → cảnh báo ·
    vắng 2 buổi liên tiếp → cảnh báo cao · điểm quiz giảm 3 buổi liên tiếp → đề xuất theo dõi. Thêm báo cáo cuối ngày, cuối tuần.
11. **Learning Risk Score** = 0.30·vắng + 0.20·buổi dở dang + 0.20·tương tác thấp + 0.15·điểm quiz giảm + 0.15·bỏ qua gợi ý →
    0–30 xanh (ổn định) · 31–60 vàng (cần theo dõi) · 61–100 đỏ (nguy cơ cao).
12. **Flow vắng mặt:** quá X phút → `late` · quá Y phút → `absent_pending` · hết khung giờ không đủ chuẩn → `absent` →
    tự sinh thông báo PH + lesson bù + gợi ý giờ học lại + cập nhật risk score.
13. **Bảng gợi ý thêm:** `student_attendance_sessions`, `student_activity_logs`, `parent_accounts`, `parent_notifications`.

---

## 2. Đối chiếu với code hiện tại

✅ có · 🟡 có một phần · ❌ chưa có

| Module | Trạng thái | Hiện có trong repo | Còn thiếu so với đặc tả |
|---|---|---|---|
| 1. Đăng ký | 🟡 | Đủ các trường ở form đăng ký (`StudentRegisterRequest`, `student_profiles`) | `favorite_color`, `interests`, `tutor_persona` **không sửa được sau đăng ký** (không có trong `UpdateProfileRequest`) |
| 2. Kiểm tra đầu vào | ✅ | `PlacementTestService`: 8 câu, 20', đề từ ngân hàng theo học lực, AI sinh bù; xếp loại từ **điểm test** (không từ điểm TB tự khai); mức hiểu, tốc độ, chủ đề yếu, thời gian từng câu | Đề chưa thích ứng theo câu trước; chưa dùng điểm TB làm tín hiệu phụ |
| 3. Giáo trình | ✅ | `LearningPathService`: 4 giai đoạn, chia buổi 3 mục, tự chèn mục ôn khi chủ đề tụt | Chưa có input "thời lượng học dự kiến / mục tiêu"; chưa theo cấu trúc 20/60/20 |
| AI Tutor | ✅ | `TutorService`, giọng thầy/cô, theo lớp/học lực/sở thích | — |
| 4. Dashboard | ✅ | % lộ trình, buổi đã/còn, điểm TB, kiến thức yếu, gợi ý hôm nay | — |
| 5. Kiểm tra cuối buổi | 🟡 | 5 câu, đạt ≥ 50% (`LearningPathService::QUIZ_*`) | **Không giới hạn 15 phút**; chưa phân tích lỗi + đề xuất "học tiếp / ôn lại" rõ ràng |
| 6. Gợi ý học tiếp | 🟡 | `RecommendationService` theo mastery; buổi sau thêm mục ôn chủ đề sai | Chưa theo luật 3 ngưỡng ≥8 / 5–8 (+30% ôn) / <5 |
| 7. AI Solver | 🟡 | Text: Gợi ý / Giải thích / Phân tích lỗi / Bài tương tự; `TutorAccessGuard` chặn lộ đáp án | **Nhập đề bằng ảnh** chưa có; chưa đo "đang học hay chỉ xin đáp án" |
| 8. Trang cá nhân | 🟡 | Avatar thầy/cô, ảnh đại diện (`AvatarService`) | **Theme theo màu yêu thích** chưa áp (cột có, giao diện chưa đọc); microcopy theo tính cách |
| 9. Thông báo | 🟡 | Push, email, nhắc bài giao, nhắc gia hạn, báo cáo tuần PH | Nhắc học hằng ngày, cảnh báo sắp quên bài, **streak**, báo vắng mặt cho PH, báo cáo cuối ngày |
| 11. Thanh toán | 🟡 | MoMo đầy đủ (IPN, hoàn tiền, voucher) | **VNPAY** |
| Logic 7–12 | ❌ | `study_sessions` chỉ là buổi tuần tự trong lộ trình, không gắn giờ | Activity log, thời gian học thật, điểm danh 3 trạng thái, flow vắng mặt, risk score, cảnh báo PH theo luật |
| Bảng `parent_accounts` / `parent_notifications` | ✅ (tương đương) | `parent_profiles` + `parent_children` + bảng `notifications` của Laravel | Không cần bảng mới |

---

## 3. Backlog cho Plane

Quy ước: **Module** = Module trên Plane · **Ưu tiên** Urgent/High/Medium/Low · **Ước lượng** theo buổi làm (≈ 4h).
Mỗi mục là một Work item; copy tiêu đề + mô tả + tiêu chí nghiệm thu.

### ⛔ Cần chốt trước (Label: `decision`)

- **D-01 · Có "lịch học" theo giờ hay không?** — Phase 7B đã chốt học sinh học *theo nhịp riêng*, buổi không gắn ngày.
  Điểm danh / trễ / vắng / "chưa vào sau 15'" (Logic 8–12) **bắt buộc phải có lịch** (ai đặt: PH hay HS? mấy buổi/tuần? khung giờ?).
  Chặn: TA-09 → TA-13.
- **D-02 · Đối tượng lớp 6→12 hay 1→12?** — Tổng quan ghi 6→12, module đăng ký ghi 1→12, repo đang hỗ trợ 1–12.
- **D-03 · Giải bài bằng ảnh dùng model vision nào** (chi phí/lượt, quota gói nào được dùng).
- **D-04 · Streak tính theo gì** (ngày có ≥ N phút học thật? xong 1 buổi?) — phụ thuộc TA-07.

### Module: Lộ trình thích ứng

**TA-01 · Kiểm tra cuối buổi 15 phút, server bấm giờ** — High · 1 buổi
- Thêm `quiz_started_at` / `quiz_expires_at` vào `study_sessions`; hết giờ tự nộp (chung lệnh `exams:finalize-expired`).
- Client chỉ hiện đồng hồ, server quyết.
- ✅ Nộp sau `expires_at` bị từ chối; test tự nộp khi hết giờ.

**TA-02 · Luật gợi ý buổi sau theo điểm quiz (thang 10)** — High · 1 buổi
- ≥ 8: mở bài mới · 5 ≤ x < 8: bài mới + ~30% mục ôn · < 5: buổi ôn trước rồi mới học tiếp.
- Ngưỡng để hằng số/ cấu hình, không rải trong code. Cập nhật qua listener `SyncLearningPath`, không đánh dấu tay.
- ✅ 3 test cho 3 nhánh; dashboard "Gợi ý hôm nay" phản ánh đúng.

**TA-03 · Cấu trúc buổi 20% ôn dễ quên · 60% mới · 20% lỗi gần đây** — Medium · 2 buổi
- "Dễ quên": chủ đề đã đạt nhưng lâu không luyện (cần `last_practiced_at` ở `student_topic_mastery`).
- "Lỗi gần đây": câu sai trong 3–5 buổi gần nhất.
- ✅ Buổi sinh ra có đủ 3 nhóm (khi có dữ liệu), test tỉ lệ.

**TA-04 · Tín hiệu đa chiều cho đề xuất** — Medium · 2 buổi · phụ thuộc TA-07, TA-16
- Đưa vào `RecommendationService`: số lần xin gợi ý, sai rồi sửa đúng, thời gian làm, lỗi lặp, độ ổn định 3–5 buổi.
- Vẫn là thuật toán quy tắc, giải thích được (không gọi LLM).

**TA-05 · Hồ sơ năng lực sau đầu vào** — Low · 1 buổi
- Ngoài nhãn TB/Khá/Giỏi: hiện năng lực theo từng chuyên đề + tốc độ trên trang kết quả và báo cáo PH.
- Điểm TB tự khai chỉ là tín hiệu phụ (ví dụ chênh lệch lớn → gợi ý làm lại đầu vào).

**TA-06 · Input "thời lượng học/tuần + mục tiêu" khi tạo lộ trình** — Low · 1 buổi
- Dùng để chia số buổi và độ dài buổi.

### Module: Thời gian học thật & điểm danh

**TA-07 · Ghi hành vi học (`student_activity_logs`)** — High · 2 buổi
- Sự kiện: `lesson_open`, `section_view`, `exercise_start`, `answer_submit`, `hint_request`, `chat_message`, `tab_inactive`, `quiz_submit`.
- JS gửi theo lô (`sendBeacon`), throttle; **service worker không cache** endpoint này.
- Dữ liệu cá nhân → thêm vào `anonymise()` **và** `DataExportService`.
- ✅ Test endpoint + giới hạn tần suất; test ẩn danh/xuất dữ liệu.

**TA-08 · Tính thời gian học chủ động** — High · 1 buổi · phụ thuộc TA-07
- `effective_study_minutes`, `idle_minutes` theo phiên; ngưỡng idle cấu hình được.
- Hiện cho HS + PH: "Online X phút · học thực Y phút · mức tập trung".

**TA-09 · Điểm danh 3 trạng thái Present / Partial / Absent** — Medium · 1 buổi · chặn bởi D-01
- Present: active ≥ 70% chuẩn + có quiz · Partial: < 70% hoặc bỏ quiz · Absent: không vào / gần như không tương tác.

**TA-10 · Flow vắng mặt late → absent_pending → absent** — Medium · 2 buổi · chặn bởi D-01
- Lệnh định kỳ; khi chốt `absent`: thông báo PH, chèn buổi bù, gợi ý giờ học lại, cập nhật risk score.

### Module: Giám sát phụ huynh & thông báo

**TA-11 · Learning Risk Score (xanh/vàng/đỏ)** — Medium · 1 buổi · phụ thuộc TA-08, TA-09
- Công thức ở Logic §11; trọng số để config. Hiện ở báo cáo PH và báo cáo lớp của GV.

**TA-12 · Cảnh báo PH theo luật** — Medium · 2 buổi · phụ thuộc TA-10
- 5 luật ở Logic §10; mỗi loại là một `NotificationType`, PH tắt/bật được ở Cài đặt; không gửi trùng trong ngày.

**TA-13 · Báo PH khi bắt đầu/hoàn thành buổi + báo cáo cuối ngày** — Low · 1 buổi
- Mặc định tắt "bắt đầu buổi" để tránh spam; báo cáo cuối ngày gom một thư/push.

**TA-14 · Nhắc học hằng ngày + cảnh báo sắp quên bài (HS)** — Medium · 1 buổi
- Lệnh định kỳ, qua push/email đã có; "sắp quên" dùng `last_practiced_at` (TA-03).

**TA-15 · Streak** — Low · 1 buổi · chặn bởi D-04
- Sau khi có mới được nhắc tới ở trang chủ (`FooterSocialLinksTest` đang canh việc quảng cáo streak).

**TA-16 · Trang giám sát PH đủ 6 mục** — Medium · 1 buổi · phụ thuộc TA-08…TA-11
- Lịch hôm nay · trạng thái tham gia · thời gian học thật · kết quả buổi · cảnh báo · gợi ý can thiệp.

### Module: AI Solver

**TA-17 · Giải bài từ ảnh** — High · 2–3 buổi · chặn bởi D-03
- Upload ảnh → vẽ lại/giảm cỡ, bỏ EXIF (như `AvatarService`) → model vision qua `AiProviderInterface` → hiện đề đã nhận dạng cho HS **xác nhận/sửa** trước khi giải.
- Quota riêng trong `package_features` (khoá mới vào `KEYS` + `KEY_TYPES`), throttle; `FakeProvider` cho test.
- Vẫn tuân `TutorAccessGuard` (đang làm đề → khoá).

**TA-18 · Đo "đang học hay chỉ xin đáp án"** — Low · 1 buổi
- Tỉ lệ xin lời giải đầy đủ / tự làm lại đúng sau gợi ý; cảnh báo nhẹ cho HS, đưa vào TA-04.

### Module: Cá nhân hoá

**TA-19 · Sửa sở thích, màu yêu thích, thầy/cô trong hồ sơ** — High · nửa buổi
- Thêm vào `UpdateProfileRequest` + form hồ sơ HS.

**TA-20 · Theme theo màu yêu thích** — Medium · 1 buổi
- Chỉ đổi màu nhấn (accent) của portal học sinh qua CSS variable, giới hạn bảng màu đã kiểm tương phản; không đụng trang công khai.

**TA-21 · Microcopy / ví dụ bài học theo sở thích** — Low · 1 buổi

### Module: Thanh toán

**TA-22 · Cổng VNPAY** — High · 2–3 buổi
- Gateway mới cạnh `MomoGateway` (contract `Payment/Contracts`), IPN + kiểm chữ ký, `FakeVnpayGateway` cho local/test.
- Chỉ `PaymentService` đổi trạng thái đơn; không kích hoạt gói từ return URL; hoàn tiền qua `PaymentService::refund()`.
- Ngoại lệ CSRF cho route IPN mới → cập nhật checklist bảo mật §11.
- ✅ Test IPN sai chữ ký / trùng / sai số tiền.

---

## 4. Thứ tự gợi ý

1. Chốt **D-01 → D-04**.
2. Nhanh, ít rủi ro: **TA-19, TA-01, TA-02, TA-20**.
3. Doanh thu: **TA-22 (VNPAY)**, **TA-17 (giải bài từ ảnh)**.
4. Nền dữ liệu: **TA-07 → TA-08**, rồi TA-03, TA-14.
5. Sau khi có lịch học: **TA-09 → TA-10 → TA-11 → TA-12 → TA-16**, TA-15.
6. Phần còn lại: TA-04, TA-05, TA-06, TA-13, TA-18, TA-21.
