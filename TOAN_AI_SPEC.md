# TOÁN AI — Đặc tả Project

> Nền tảng học Toán trực tuyến lớp 1–12, kết hợp nội dung lý thuyết, luyện tập, đề kiểm tra, AI Tutor cá nhân hóa, quản lý giáo viên/phụ huynh và hệ thống thanh toán gói học.

---

## 1. Mục tiêu project

Xây dựng nền tảng học Toán từ lớp 1–12, kết hợp:

- Nội dung lý thuyết
- Ví dụ minh họa
- Bài tập / luyện tập
- Đề kiểm tra
- AI Tutor
- Cá nhân hóa lộ trình học
- Giáo viên quản lý lớp và nội dung
- Phụ huynh theo dõi việc học của con
- Gói học miễn phí / trả phí
- Thanh toán online, trước mắt tích hợp MoMo

**Định hướng sản phẩm:** Không chỉ đưa đáp án — AI giúp học sinh hiểu.

---

## 2. Công nghệ sử dụng

### Backend
- Laravel
- PHP
- MySQL
- Redis
- Laravel Queue
- Laravel Scheduler

### Frontend
- Blade
- HTML / CSS
- Vanilla JavaScript
- Bootstrap 5
- Mobile-first

> Không ưu tiên React/Vue ở giai đoạn đầu.

### Công nghệ bổ sung
- KaTeX / MathJax — hiển thị công thức Toán
- Chart.js — biểu đồ tiến độ
- PWA — triển khai ở giai đoạn sau

---

## 3. Mobile-first

Ưu tiên thiết kế theo thứ tự: **Mobile → Tablet → Desktop**

Giao diện học sinh:
- Đơn giản, ít thông tin thừa
- Nút lớn, dễ thao tác
- Đọc lý thuyết dễ, làm bài dễ
- AI Tutor truy cập nhanh

- **Mobile:** bottom navigation — `Trang chủ | Học | Bài tập | AI`
- **Desktop:** sidebar

---

## 4. Các loại tài khoản (4 role)

### Student
Xem lý thuyết, ví dụ, làm bài tập/đề, hỏi AI, xem tiến độ/điểm, nhận đề xuất học tập.

### Teacher
Tạo bài học/lý thuyết/ví dụ/câu hỏi/đề, giao bài, quản lý lớp và học sinh, theo dõi tiến độ, nhận xét học sinh, dùng AI tạo nội dung/câu hỏi.

### Parent
Liên kết với con, xem tiến độ/điểm/thời gian học/chủ đề yếu, xem nhận xét giáo viên và đề xuất AI, quản lý gói học.

### Admin
Quản lý User, Teacher, Student, Parent, nội dung, câu hỏi, đề thi, package, subscription, payment, AI, hệ thống.

> Không cho người dùng tự đăng ký Admin.

---

## 5. Đăng ký tài khoản

```
Đăng ký
   ↓
Bạn là ai?
   ├── Học sinh
   ├── Giáo viên
   └── Phụ huynh
```

**Student:** Họ tên, Email, Password, Lớp, Năm sinh

**Teacher:** Họ tên, Email, Số điện thoại, Trường/đơn vị, Password
→ Sau đăng ký: trạng thái `pending` → Admin duyệt

**Parent:** Họ tên, Email, Số điện thoại, Password
→ Liên kết con bằng: mã / link / QR

---

## 6. Website public (Landing page)

```
Header
↓
Hero
↓
Tính năng
↓
AI Tutor
↓
Học sinh
↓
Giáo viên
↓
Phụ huynh
↓
Chương trình lớp 1–12
↓
Gói học
↓
CTA
↓
Footer
```

**Hero:** "Học Toán thông minh cùng AI"
**CTA:** "Bắt đầu học miễn phí" / "Xem chương trình"

> Nếu người chưa đăng nhập click vào bài học: *"Vui lòng đăng nhập để bắt đầu học."*

---

## 7. Cấu trúc chương trình Toán

```
Grade → Subject → Chapter → Topic → Lesson
```

Ví dụ: `Lớp 6 → Toán → Phân số → Phép cộng phân số → Cộng hai phân số khác mẫu số`

---

## 8. Cấu trúc một Lesson

```
Lý thuyết → Ví dụ → Hiểu bản chất → Công thức/Ghi nhớ
→ Lỗi thường gặp → Quiz nhanh → Luyện tập → Bài nâng cao → Kiểm tra
```

**Chu trình học:** Học lý thuyết → Ví dụ → Luyện tập → AI chữa lỗi → Ôn tập → Kiểm tra

---

## 9. Theo dõi tiến độ

Theo dõi: bài học đã học, lý thuyết hoàn thành, thời gian học, quiz, bài tập, đề thi, điểm, chủ đề mạnh/yếu.

Ví dụ hiển thị:
| Chủ đề | Tiến độ |
|---|---|
| Số học | 91% |
| Đại số | 85% |
| Hình học | 64% |
| Phân số | 52% |

---

## 10. AI Tutor

AI không chỉ trả lời đáp án mà phải hướng dẫn học sinh **hiểu**.

**Các chế độ:** Gợi ý · Giải thích · Kiểm tra đáp án · Bài tương tự · Phân tích lỗi

```
Học sinh làm sai → AI phân tích lỗi → Xác định kiến thức sai
→ Giải thích → Đưa gợi ý → Cho bài tương tự
```

**API dự kiến:**
```
POST /api/v1/ai/chat
POST /api/v1/ai/hint
POST /api/v1/ai/explain
POST /api/v1/ai/check-answer
POST /api/v1/ai/similar-exercise
POST /api/v1/ai/analyze-mistake
```

**Giới hạn phạm vi (bắt buộc, mọi provider):** AI chỉ được trả lời nội dung liên quan tới học Toán.
Câu hỏi ngoài lề → từ chối nhẹ nhàng, quay lại bài học — quy tắc này nằm trong system prompt
(`PromptBuilder::system()`), không phải cấu hình có thể tắt. Vì model vẫn có thể bị dẫn lạc đề dù đã
dặn trước, `ScopeGuard` (`app/Services/AI/ScopeGuard.php`) là rào chắn thứ hai: nhận diện tin nhắn có vẻ
ngoài lề mà AI không từ chối đúng cách, ghi audit log `ai.off_topic_suspected` để quản trị xem lại — không
tự chặn câu trả lời (tránh chặn nhầm câu hỏi Toán hợp lệ). Đổi provider hay nâng cấp model phải giữ nguyên
quy tắc này trong system prompt.

---

## 11. AI cá nhân hóa

AI theo dõi: chủ đề, câu hỏi, độ khó, đúng/sai, thời gian, số lần làm.

```
Phát hiện điểm yếu → Tìm kiến thức nền → Đề xuất bài học
→ Đề xuất bài tập → Tăng dần độ khó → Kiểm tra lại
```

---

## 12. AI cho giáo viên

**Tạo câu hỏi** — ví dụ input: Lớp 6, Chủ đề Phân số, 10 câu (Dễ 30% / Trung bình 50% / Khó 20%)
→ AI tạo draft → Giáo viên: Chấp nhận / Sửa / Tạo lại / Xóa

**Tạo lesson** — AI hỗ trợ tạo lý thuyết, ví dụ, quiz, viết lại dễ hiểu, tóm tắt.

> AI không tự động publish nội dung — Teacher/Admin phải kiểm duyệt.

**Viết lại/tóm tắt** (`ContentGeneratorService::rewrite()`) cũng áp dụng giới hạn phạm vi ở §10: nội dung
giáo viên dán vào không liên quan Toán học → AI trả về đúng một câu báo không thuộc phạm vi, không xử lý tiếp.

---

## 13. Teacher Portal

**Menu:** Dashboard · Lớp học · Bài học · Ngân hàng câu hỏi · Đề kiểm tra · Bài tập · Học sinh · Báo cáo

**Dashboard:** Số lớp, Số học sinh, Bài tập đang giao, Điểm trung bình, Học sinh cần hỗ trợ

**Bộ lọc học sinh:** Tất cả / Cần hỗ trợ / Chưa làm bài / Điểm thấp / Đang tiến bộ

---

## 14. Parent Portal

Ví dụ dashboard:
> **Con của tôi**
> Tiến độ: 72% · Điểm trung bình: 8.1 · Thời gian học: 12h30 · Bài hoàn thành: 32
>
> Chủ đề mạnh / Chủ đề yếu
>
> **AI đề xuất — Ôn lại:** Phân số, Quy đồng mẫu số, So sánh phân số

---

## 15. Bài tập (Question Bank)

**Loại câu hỏi:** Multiple choice · Single choice · True/False · Fill blank · Short answer · Essay

**Mỗi câu gồm:** Khối, Môn, Chương, Chủ đề, Nội dung, Đáp án, Giải thích, Độ khó (Easy / Medium / Hard)

---

## 16. Đề kiểm tra

Teacher: tạo đề thủ công / chọn từ Question Bank / tạo đề bằng AI.
Thông tin đề: Tên, Thời gian, Số câu, Độ khó.

```
Start exam → Answer → Submit → Calculate result → Review
```

---

## 17. Giao bài

```
Chọn lớp → Chọn học sinh → Chọn bài → Deadline → Cho phép làm lại → Giao bài
```

Theo dõi: Đã làm / Chưa làm, Điểm, Thời gian.

---

## 18. Gói học

| Gói | Nội dung |
|---|---|
| **Free** | Một phần bài học, một số bài tập, AI giới hạn |
| **Pro** | Toàn bộ bài học/bài tập, AI Tutor, Đề kiểm tra, Progress |
| **Premium** | Toàn bộ tính năng Pro + AI nâng cao + Cá nhân hóa + Báo cáo nâng cao |

> Giá phải lấy từ database, không hard-code.

---

## 19. Subscription

**Bảng chính:** `packages` · `package_features` · `subscriptions` · `payments`

**Trạng thái subscription:** `pending` → `active` → `expired` / `cancelled`

```
Package → Payment → Payment thành công → Subscription active → User được quyền truy cập
```

---

## 20. Thanh toán MoMo

**Kiến trúc:**
```
Frontend → Laravel → PaymentService → PaymentGatewayInterface → MomoPaymentService → MoMo
```

> Không gọi MoMo trực tiếp từ frontend.

---

## 21. Flow thanh toán MoMo

```
User chọn gói → POST checkout → Laravel lấy package → Lấy giá từ DB
→ Tạo Payment = pending → Tạo Subscription = pending → Gọi MoMo → Nhận payUrl
→ User thanh toán → MoMo IPN → Laravel verify signature → Verify order
→ Verify amount → Payment = paid → Subscription = active
```

---

## 22. Nguyên tắc bảo mật thanh toán

Frontend **không** được quyết định số tiền.

Frontend chỉ gửi:
```json
{ "package_id": 2, "payment_method": "momo" }
```

Backend tự tính giá:
```php
$package = Package::findOrFail($packageId);
$amount = $package->price;
```

Không được nhận `"amount": 1000` từ client và tin theo.

---

## 23. MoMo IPN

```
POST /api/v1/payment/momo/ipn
```

Phải xử lý: Verify signature · Verify order ID · Verify amount · Verify transaction · Idempotency · Database transaction.

> Nếu IPN gửi nhiều lần và Payment đã `paid` → không cấp thêm subscription lần nữa.

---

## 24. Return URL

```
GET /payment/momo/return
```

> Không dùng Return URL làm bằng chứng duy nhất để kích hoạt gói. Nguồn xác nhận phải dựa trên callback/IPN hợp lệ và kiểm tra server-side.

---

## 25. Payment History

**User:** Lịch sử thanh toán — Mã đơn, Gói, Số tiền, Phương thức, Trạng thái, Thời gian.
**Admin:** Màn hình quản lý giao dịch.

---

## 26. Kiến trúc code (Laravel)

```
app/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Middleware/
│
├── Models/
│
├── Services/
│   ├── Learning/
│   ├── AI/
│   ├── Payment/
│   │   ├── PaymentGatewayInterface.php
│   │   ├── PaymentService.php
│   │   └── MomoPaymentService.php
│   └── SubscriptionService.php
│
├── Jobs/
├── Policies/
└── Repositories/
```

> Nguyên tắc: Controller mỏng, business logic nằm trong Service.

---

## 27. Database chính

**User/RBAC:** `users` · `roles` · `permissions` · `role_user` · `permission_role`

**Learning:** `grades` · `subjects` · `chapters` · `topics` · `lessons` · `lesson_sections` · `student_lesson_progress`

**Exercise:** `questions` · `question_options` · `question_tags`

**Exam:** `exams` · `exam_questions` · `exam_attempts` · `student_answers`

**Teacher:** `classes` · `teacher_classes` · `class_students` · `assignments` · `assignment_questions` · `assignment_students` · `assignment_submissions` · `teacher_comments`

**Subscription:** `packages` · `package_features` · `subscriptions` · `payments`

**AI:** `ai_conversations` · `ai_messages` · `ai_usage`

**Khác:** `parent_children` · `notifications` · `audit_logs`

---

## 28. API version

Tất cả API bắt đầu bằng `/api/v1/`

```
/api/v1/auth/login
/api/v1/lessons
/api/v1/exams
/api/v1/ai/chat
/api/v1/subscriptions/checkout
/api/v1/payment/momo/ipn
```

---

## 29. Security

- CSRF protection
- XSS protection
- SQL injection protection
- Form Request validation
- Authorization / Policy
- Rate limiting
- Password hashing
- HTTPS (production)
- Secret trong `.env`
- AI API key không đưa ra frontend
- AI chỉ trả lời nội dung Toán học (system prompt) + `ScopeGuard` giám sát lượt nghi ngờ lạc đề (§10)
- MoMo signature verification
- IPN idempotency
- Audit log

---

## 30. Roadmap phát triển

| Phase | Nội dung |
|---|---|
| **1** | Laravel, MySQL, Bootstrap, Auth, RBAC, Layout |
| **2** | Grade, Subject, Chapter, Topic, Lesson, Theory, Progress |
| **3** | Question, Exercise, Answer, Score |
| **4** | Exam |
| **5** | Teacher, Class, Assignment |
| **6** | Parent, Progress, Report |
| **7** | AI Tutor, AI Question Generator, AI Lesson Generator, Recommendation |
| **8** | Package, Subscription, Access Control |
| **9** | MoMo, Checkout, IPN, Payment History |
| **10** | Redis, Queue, Scheduler, Analytics, PWA, Production |

---

## 31. Luồng hoàn chỉnh của sản phẩm

```
                    TOÁN AI
                       │
                Landing Page
                       │
             ┌─────────┴─────────┐
             │                   │
          Đăng ký             Đăng nhập
             │                   │
       ┌─────┼─────┐             │
       │     │     │             │
    Student Teacher Parent        │
       │     │     │             │
       ▼     ▼     ▼             ▼
    Student Teacher Parent    Dashboard
    Portal  Portal  Portal
       │
       ▼
   Học Toán
       │
       ├── Lý thuyết
       ├── Ví dụ
       ├── Luyện tập
       ├── Đề thi
       └── AI Tutor
              │
              ▼
        Cá nhân hóa học tập
              │
              ▼
        Gợi ý kiến thức yếu
              │
              ▼
          Gói Premium
              │
              ▼
          Thanh toán
              │
              ▼
             MoMo
              │
              ▼
       Payment xác nhận
              │
              ▼
      Subscription Active
              │
              ▼
        Tiếp tục học
```

---

## 32. Kiến trúc tổng thể hệ thống (bổ sung)

**Mục tiêu dữ liệu** — Hệ thống phải làm được 5 việc chính:

1. Lưu hồ sơ học sinh thật chi tiết
2. Đánh giá đầu vào
3. Tạo giáo trình cá nhân hóa
4. Theo dõi tiến độ học và kiểm tra sau mỗi buổi
5. Hỗ trợ hỏi đáp thông qua Chatbot cá nhân hóa thầy/cô, giải bài, giải thích từng bước

### Tổng quan dự án

| Hạng mục | Mô tả |
|---|---|
| Tên hệ thống | Nền tảng học toán online cá nhân hóa bằng A.I |
| Mục tiêu | Giúp học sinh cải thiện tư duy và điểm số thông qua lộ trình cá nhân hóa |
| Công nghệ lõi | A.I tạo bài tập, hướng dẫn/gợi ý giải bài tập, phân tích học lực, gợi ý lộ trình, cá nhân hóa giáo án |
| Đối tượng | Học sinh từ lớp 1 → 12 |

---

## 33. Module: Đăng ký người dùng

**🎯 Mục tiêu:** Thu thập dữ liệu đầu vào để A.I cá nhân hóa ngay từ đầu.

**📋 Thông tin bắt buộc:**

| Trường dữ liệu | Mô tả |
|---|---|
| Họ và tên | Xác định người dùng |
| Ngày sinh | Xác định độ tuổi |
| Địa chỉ nơi ở | Phục vụ cá nhân hóa (vùng miền, chương trình học) |
| Email | Đăng nhập / thông báo |
| Số điện thoại | Xác thực + hỗ trợ |
| Trường học | Ngữ cảnh học tập |
| Khối lớp | Lớp 1 → 12 |
| Học lực | Tự đánh giá ban đầu |
| Điểm trung bình toán | Input cho AI |
| Chọn giáo viên | Chọn "thầy" hoặc "cô" (avatar AI) |
| Màu sắc yêu thích | Cá nhân hóa UI |
| Sở thích | Cá nhân hóa trải nghiệm |

---

## 34. Module: Kiểm tra đầu vào

| Bước | Mô tả |
|---|---|
| 1 | A.I tạo bộ đề dựa trên lớp + học lực + điểm trung bình |
| 2 | Đề gồm 5–10 câu (trắc nghiệm + tự luận) |
| 3 | Học sinh làm bài trực tiếp |
| 4 | A.I chấm điểm + phân tích lỗi |

**Phân loại học lực:**

| Điểm trung bình | Xếp loại |
|---|---|
| ≤ 5 | Trung bình |
| ≤ 8 | Khá |
| > 8 | Giỏi |

**Output** — Xác định:
- Nhóm kiến thức yếu
- Tốc độ làm bài
- Mức độ hiểu

> 👉 Đây là nền tảng để tạo giáo trình.

---

## 35. Module: Tạo giáo trình A.I (Core Engine)

**Mục tiêu:** Tạo lộ trình học riêng cho từng học sinh.

**⚙️ Thành phần:**

**1. Lý thuyết**
- Chia theo chuyên đề
- Tối giản, dễ hiểu
- Phù hợp với học lực

**2. Bài tập thực hành**
- Phân cấp độ: Dễ → Trung bình → Khó
- Tự động điều chỉnh theo kết quả

**3. Lộ trình học**

| Giai đoạn | Nội dung |
|---|---|
| Giai đoạn 1 | Ôn lại nền tảng |
| Giai đoạn 2 | Củng cố kiến thức |
| Giai đoạn 3 | Nâng cao |
| Giai đoạn 4 | Luyện đề |

**Giáo viên ảo (AI Tutor)**

| Chức năng | Mô tả |
|---|---|
| Chatbox | Hỏi đáp trực tiếp |
| Giải thích | Theo từng bước |
| Phong cách | Thầy / Cô tùy chọn |
| Cá nhân hóa | Theo trình độ học sinh |

---

## 36. Module: Tiến độ học (Dashboard)

**Mục tiêu:** Giúp học sinh biết mình đang ở đâu.

**Hiển thị:**

| Thành phần | Mô tả |
|---|---|
| % hoàn thành | Theo lộ trình |
| Buổi học đã học | Tổng số |
| Buổi còn lại | Kế hoạch |
| Điểm trung bình | Theo bài kiểm tra |
| Nhóm kiến thức yếu | AI phân tích |

**Gợi ý học hôm nay** — A.I dựa vào:
- Giáo trình
- Kết quả gần nhất
- Thời gian học

> 👉 Đưa ra: Bài học, Bài tập

---

## 37. Module: Kiểm tra cuối buổi

> ⚠️ Nội dung chi tiết của module này chưa hiển thị đầy đủ trong sheet nguồn (bị cắt do giới hạn hiển thị). Cần bổ sung thêm khi có nội dung đầy đủ.

---

## 38. Tóm tắt yêu cầu cốt lõi

**Toán AI =** Laravel + MySQL + Blade + Bootstrap 5 + Vanilla JS + Mobile-first + AI Tutor + hệ thống học Toán lớp 1–12 + Teacher + Parent + RBAC + Subscription + MoMo

**4 phần quan trọng nhất cần xây chắc ngay từ đầu:**

1. **Learning Architecture** — Grade → Subject → Chapter → Topic → Lesson
2. **AI Tutor + cá nhân hóa** — AI giúp hiểu bài, không chỉ đưa đáp án
3. **Subscription/Access Control** — xác định user được học nội dung nào
4. **Payment architecture** — PaymentService + MoMo + IPN + verification + idempotency

> Đây là nền tảng đủ để chuyển sang bước tiếp theo: tạo `PROJECT_SPEC.md` hoàn chỉnh cho Claude Code, kèm ERD, toàn bộ migration, API specification, RBAC matrix, cấu trúc Laravel và checklist từng task triển khai.
