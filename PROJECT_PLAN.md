# TOÁN AI — Kế hoạch triển khai (PROJECT PLAN)

> Tài liệu thi hành của [TOAN_AI_SPEC.md](TOAN_AI_SPEC.md). Spec nói **làm gì**, tài liệu này nói **làm thế nào, theo thứ tự nào, xong thì trông ra sao**.
>
> Cập nhật: 2026-09-16 · Trạng thái: **Phase 0 + Phase 1 đã xong** · Kế tiếp: Phase 2

---

## 0. Quyết định kỹ thuật đã chốt

| Hạng mục | Quyết định | Lý do |
|---|---|---|
| Framework | **Laravel 12** (PHP 8.2.12 sẵn có) | LTS-ish, PHP 8.2 là mức tối thiểu của L12 |
| Database | **MariaDB 10.4** (XAMPP), DB `toan_ai` | Giống production, tránh sai khác SQL |
| Charset | `utf8mb4` / `utf8mb4_unicode_ci` | Tiếng Việt + emoji |
| Frontend | Blade + Bootstrap 5.3 + Vanilla JS, build bằng **Vite** | Theo spec, không React/Vue |
| Auth | **Custom Blade auth** (không dùng Breeze) + **Sanctum** cho API | Breeze ship Tailwind → xung đột Bootstrap 5 |
| API auth | Session cookie + CSRF cho web (same-origin), Sanctum token để dành cho PWA/mobile | Đơn giản, an toàn |
| RBAC | Tự viết `roles`/`permissions` + Gate/Policy (không dùng spatie) | Spec đã định sẵn schema; giảm phụ thuộc |
| AI provider | **OpenAI** qua `AiProviderInterface` + `OpenAiProvider` | Có thể đổi provider không sửa business code |
| Queue | `database` (Phase 1–9) → **Redis/predis** (Phase 10) | PHP chưa có ext `redis`; predis là pure-PHP |
| Cache/Session | `file` → `redis` (Phase 10) | Như trên |
| Toán học | **KaTeX** (nhanh hơn MathJax, đủ dùng) | Render `$...$` / `$$...$$` phía client |
| Tiền tệ | Lưu `decimal(12,2)` VND, MoMo nhận **integer VND** | Tránh lỗi float |
| Múi giờ | `Asia/Ho_Chi_Minh`, lưu DB theo UTC | Chuẩn Laravel |

**Extension PHP còn thiếu cần bật trong `php.ini`:** `intl` (định dạng số/ngày tiếng Việt). Không bắt buộc Phase 1.

---

## 1. Nguyên tắc kiến trúc (bất di bất dịch)

1. **Controller mỏng** — chỉ validate (Form Request), gọi Service, trả view/JSON. Không query Eloquent phức tạp trong controller.
2. **Business logic nằm trong `app/Services/`** — mọi thao tác nhiều bước, nhiều bảng.
3. **Repository chỉ dùng khi query phức tạp/tái sử dụng** — không tạo repository rỗng bọc Eloquent cho đủ lệ.
4. **Không hard-code giá, quota, quyền** — tất cả từ DB (`packages`, `package_features`, `permissions`).
5. **Frontend không bao giờ quyết định số tiền hay quyền truy cập** — backend tự tra.
6. **Mọi thay đổi trạng thái tiền bạc chạy trong `DB::transaction()` + idempotency key.**
7. **AI không tự publish nội dung** — mọi output AI vào bảng draft, chờ người duyệt.
8. **Mọi endpoint ghi dữ liệu đều có Form Request + Policy.** Không có ngoại lệ.
9. **Mobile-first**: viết CSS mặc định cho mobile, `@media (min-width: …)` để mở rộng.

---

## 2. Cấu trúc thư mục

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/            LoginController, RegisterController, PasswordController
│   │   ├── Web/             LandingController, DashboardController
│   │   ├── Student/         LessonController, PracticeController, ExamController, ProgressController
│   │   ├── Teacher/         ClassController, LessonController, QuestionController, ExamController,
│   │   │                    AssignmentController, ReportController
│   │   ├── Parent/          ChildController, ReportController
│   │   ├── Admin/           UserController, ApprovalController, ContentController, PackageController,
│   │   │                    PaymentController, AiUsageController
│   │   └── Api/V1/          AuthController, LessonController, ExamController, AiController,
│   │                        SubscriptionController, PaymentController
│   ├── Requests/            (1 Form Request / 1 action ghi)
│   ├── Middleware/          EnsureUserHasRole, EnsureSubscriptionAccess, EnsureTeacherApproved
│   └── Resources/           API JSON Resources
│
├── Models/
├── Policies/                LessonPolicy, QuestionPolicy, ExamPolicy, ClassPolicy, AssignmentPolicy,
│                            StudentProgressPolicy
├── Services/
│   ├── Learning/            LessonService, ProgressService, PracticeService, ExamService, GradingService
│   ├── Teaching/            ClassService, AssignmentService, TeacherReportService
│   ├── AI/
│   │   ├── Contracts/AiProviderInterface.php
│   │   ├── Providers/OpenAiProvider.php, FakeProvider.php
│   │   ├── TutorService.php            (chat, hint, explain, check-answer, similar, analyze-mistake)
│   │   ├── ContentGeneratorService.php (AI tạo câu hỏi / lesson cho teacher)
│   │   ├── RecommendationService.php   (cá nhân hóa)
│   │   ├── PromptBuilder.php
│   │   └── AiUsageGuard.php            (quota theo package)
│   ├── Payment/
│   │   ├── PaymentGatewayInterface.php
│   │   ├── PaymentService.php
│   │   └── Gateways/MomoPaymentService.php
│   ├── SubscriptionService.php
│   └── AccessControlService.php        (user X có được xem lesson Y không)
├── Jobs/                    ProcessAiRequest, RecalculateMastery, ExpireSubscriptions, SendWeeklyParentReport
├── Repositories/
└── Support/                 OrderCode, Money, Katex

resources/views/
├── layouts/     app.blade.php, guest.blade.php, student.blade.php, teacher.blade.php,
│                parent.blade.php, admin.blade.php
├── components/  bottom-nav, sidebar, lesson-section, question-card, progress-bar, ai-chat-widget
├── public/      landing.blade.php + partials/ (hero, features, ai, student, teacher, parent, grades, pricing, cta)
├── auth/        login, register-choose, register-student, register-teacher, register-parent
├── student/ · teacher/ · parent/ · admin/
```

---

## 3. ERD — quan hệ chính

```
users ──< role_user >── roles ──< permission_role >── permissions
  │
  ├──1:1 student_profiles ──> grades
  ├──1:1 teacher_profiles
  ├──< parent_children >── users (student)
  ├──< subscriptions >── packages ──< package_features
  ├──< payments
  ├──< ai_conversations ──< ai_messages
  └──< student_lesson_progress, student_topic_mastery, recommendations

grades ──< subjects ──< chapters ──< topics ──< lessons ──< lesson_sections
                                       │           │
                                       │           └──< student_lesson_progress
                                       └──< questions ──< question_options
                                                 │
                                                 ├──< question_tags >── tags
                                                 ├──< exam_questions >── exams ──< exam_attempts ──< student_answers
                                                 └──< assignment_questions >── assignments

classes ──< class_students >── users(student)
   │     ──< teacher_classes >── users(teacher)
   └──< assignments ──< assignment_students >── assignment_submissions
```

---

## 4. Migration checklist (theo thứ tự chạy)

Thứ tự quan trọng vì ràng buộc khoá ngoại. Mỗi bảng đều có `timestamps`; bảng nội dung có `softDeletes`.

### Nhóm 1 — Identity & RBAC (Phase 1)
| # | Bảng | Cột chính |
|---|---|---|
| 01 | `roles` | `name`(unique: student/teacher/parent/admin), `display_name` |
| 02 | `permissions` | `name`(unique, dạng `lesson.create`), `group`, `display_name` |
| 03 | `role_user` | `role_id`, `user_id`, unique(role_id,user_id) |
| 04 | `permission_role` | `permission_id`, `role_id` |
| 05 | `users` | `name`, `email`(unique), `phone`, `password`, `status`(pending/active/suspended/rejected), `avatar`, `last_login_at`, `email_verified_at`, softDeletes |
| 06 | `grades` | `name`, `level`(1..12, unique), `slug`, `sort_order`, `is_active` |
| 07 | `student_profiles` | `user_id`(unique), `grade_id`, `birth_year`, `link_code`(unique, để phụ huynh liên kết) |
| 08 | `teacher_profiles` | `user_id`(unique), `school`, `subject`, `approved_at`, `approved_by`, `reject_reason` |
| 09 | `parent_children` | `parent_id`, `student_id`, `status`(pending/linked/revoked), `linked_at`, unique(parent_id,student_id) |
| 10 | `audit_logs` | `user_id`, `action`, `auditable_type`, `auditable_id`, `old_values`json, `new_values`json, `ip`, `user_agent` |

### Nhóm 2 — Learning (Phase 2)
| # | Bảng | Cột chính |
|---|---|---|
| 11 | `subjects` | `grade_id`, `name`, `slug`, `sort_order`, `is_active` |
| 12 | `chapters` | `subject_id`, `name`, `slug`, `sort_order`, `is_active` |
| 13 | `topics` | `chapter_id`, `name`, `slug`, `sort_order`, `is_active` |
| 14 | `lessons` | `topic_id`, `title`, `slug`, `summary`, `sort_order`, `difficulty`(easy/medium/hard), `estimated_minutes`, `access_level`(free/pro/premium), `status`(draft/review/published), `created_by`, `published_at`, softDeletes. Index: (topic_id, status, sort_order) |
| 15 | `lesson_sections` | `lesson_id`, `type`(theory/example/insight/formula/common_mistake/quiz/practice/advanced/test), `title`, `content`(longText HTML+KaTeX), `sort_order` |
| 16 | `student_lesson_progress` | `user_id`, `lesson_id`, `status`, `sections_completed`json, `progress_percent`, `time_spent_seconds`, `last_viewed_at`, `completed_at`, unique(user_id,lesson_id) |

### Nhóm 3 — Question Bank (Phase 3)
| # | Bảng | Cột chính |
|---|---|---|
| 17 | `questions` | `grade_id`, `topic_id`(null), `lesson_id`(null), `type`(multiple_choice/single_choice/true_false/fill_blank/short_answer/essay), `content`, `explanation`, `difficulty`, `correct_answer`json, `points`, `status`(draft/published), `source`(manual/ai), `created_by`, softDeletes. Index: (grade_id, topic_id, difficulty, status) |
| 18 | `question_options` | `question_id`, `content`, `is_correct`, `sort_order` |
| 19 | `tags` | `name`, `slug`(unique) |
| 20 | `question_tags` | `question_id`, `tag_id` |
| 21 | `student_topic_mastery` | `user_id`, `topic_id`, `correct_count`, `wrong_count`, `avg_time_seconds`, `mastery_score`(0–100), `last_practiced_at`, unique(user_id,topic_id) |

### Nhóm 4 — Exam (Phase 4)
| # | Bảng | Cột chính |
|---|---|---|
| 22 | `exams` | `title`, `slug`, `grade_id`, `subject_id`, `type`(practice/quiz/test), `duration_minutes`, `total_questions`, `total_points`, `difficulty`, `access_level`, `max_attempts`, `shuffle_questions`, `shuffle_options`, `available_from`, `available_to`, `status`, `created_by`, softDeletes |
| 23 | `exam_questions` | `exam_id`, `question_id`, `sort_order`, `points`, unique(exam_id,question_id) |
| 24 | `exam_attempts` | `exam_id`, `user_id`, `attempt_no`, `started_at`, `expires_at`, `submitted_at`, `score`, `total_points`, `correct_count`, `status`(in_progress/submitted/graded/expired). Index: (user_id, exam_id) |
| 25 | `student_answers` | `attempt_id`, `question_id`, `selected_option_ids`json, `answer_text`, `is_correct`, `score`, `time_spent_seconds`, `graded_by`, `feedback`, unique(attempt_id,question_id) |

### Nhóm 5 — Teacher (Phase 5)
| # | Bảng | Cột chính |
|---|---|---|
| 26 | `classes` | `name`, `code`(unique, để HS join), `grade_id`, `description`, `owner_teacher_id`, `status`, softDeletes |
| 27 | `teacher_classes` | `class_id`, `teacher_id`, `role`(owner/assistant) |
| 28 | `class_students` | `class_id`, `student_id`, `status`(active/removed), `joined_at`, unique(class_id,student_id) |
| 29 | `assignments` | `class_id`, `teacher_id`, `title`, `description`, `type`(question_set/exam/lesson), `exam_id`(null), `lesson_id`(null), `due_at`, `allow_retry`, `max_attempts`, `status`(draft/published/closed), `published_at` |
| 30 | `assignment_questions` | `assignment_id`, `question_id`, `sort_order`, `points` |
| 31 | `assignment_students` | `assignment_id`, `student_id`, `status`(assigned/in_progress/submitted/graded/late), unique(assignment_id,student_id) |
| 32 | `assignment_submissions` | `assignment_id`, `student_id`, `attempt_id`(null), `submitted_at`, `score`, `feedback`, `graded_by`, `graded_at` |
| 33 | `teacher_comments` | `teacher_id`, `student_id`, `class_id`(null), `lesson_id`(null), `content`, `visible_to_parent` |

### Nhóm 6 — AI (Phase 7)
| # | Bảng | Cột chính |
|---|---|---|
| 34 | `ai_conversations` | `user_id`, `mode`(chat/hint/explain/check_answer/similar/analyze_mistake), `context_type`(lesson/question/exam/free), `context_id`, `title`, `last_message_at` |
| 35 | `ai_messages` | `conversation_id`, `role`(system/user/assistant), `content`, `model`, `tokens_in`, `tokens_out`, `latency_ms`, `meta`json |
| 36 | `ai_usage` | `user_id`, `usage_date`, `feature`, `request_count`, `tokens_in`, `tokens_out`, `cost_estimate`, unique(user_id,usage_date,feature) |
| 37 | `ai_generation_drafts` | `user_id`, `type`(questions/lesson/exam), `input`json, `output`json, `status`(draft/accepted/rejected), `reviewed_by`, `reviewed_at` |
| 38 | `recommendations` | `user_id`, `type`(review_lesson/practice_topic/take_exam), `target_type`, `target_id`, `reason`, `score`, `status`(new/seen/done/expired), `expires_at` |

### Nhóm 7 — Subscription & Payment (Phase 8–9)
| # | Bảng | Cột chính |
|---|---|---|
| 39 | `packages` | `name`, `slug`(unique), `tier`(free/pro/premium), `price`decimal(12,2), `currency`(VND), `duration_days`, `description`, `is_active`, `sort_order` |
| 40 | `package_features` | `package_id`, `key`(vd `ai.daily_requests`), `label`, `value`, `limit_value`(int, null = không giới hạn) |
| 41 | `subscriptions` | `user_id`, `package_id`, `status`(pending/active/expired/cancelled), `starts_at`, `ends_at`, `payment_id`, `auto_renew`, `cancelled_at`. Index: (user_id, status, ends_at) |
| 42 | `payments` | `user_id`, `package_id`, `subscription_id`, `order_code`(unique), `amount`decimal(12,2), `currency`, `method`(momo), `status`(pending/paid/failed/cancelled/refunded), `gateway_request_id`, `gateway_transaction_id`, `gateway_response`json, `paid_at`, `client_ip` |
| 43 | `payment_webhook_logs` | `provider`, `order_code`, `signature_valid`, `payload`json, `headers`json, `result`, `processed_at` — **bắt buộc**, để truy vết IPN |
| 44 | `notifications` | bảng chuẩn Laravel |

**Tổng: 44 migration.**

---

## 5. RBAC matrix

Role slug: `student` · `teacher` · `parent` · `admin`. Permission đặt tên `<resource>.<action>`.

| Permission | student | teacher | parent | admin |
|---|:--:|:--:|:--:|:--:|
| `lesson.view` | ✅ (theo access_level) | ✅ | — | ✅ |
| `lesson.create` / `.update` / `.delete` | — | ✅ (của mình) | — | ✅ |
| `lesson.publish` | — | ✅ (của mình) | — | ✅ |
| `question.view` | — | ✅ | — | ✅ |
| `question.create` / `.update` / `.delete` | — | ✅ (của mình) | — | ✅ |
| `exam.take` | ✅ | — | — | — |
| `exam.create` / `.update` / `.delete` | — | ✅ | — | ✅ |
| `exam.grade` | — | ✅ | — | ✅ |
| `class.manage` | — | ✅ (lớp mình) | — | ✅ |
| `assignment.manage` | — | ✅ (lớp mình) | — | ✅ |
| `assignment.submit` | ✅ | — | — | — |
| `progress.view.own` | ✅ | — | — | ✅ |
| `progress.view.student` | — | ✅ (HS lớp mình) | ✅ (con mình) | ✅ |
| `comment.create` | — | ✅ | — | ✅ |
| `ai.tutor` | ✅ (theo quota gói) | ✅ | — | ✅ |
| `ai.generate_content` | — | ✅ | — | ✅ |
| `child.link` / `child.view` | — | — | ✅ | ✅ |
| `subscription.purchase` | ✅ | ✅ | ✅ | ✅ |
| `subscription.manage` | — | — | ✅ (cho con) | ✅ |
| `payment.view.own` | ✅ | ✅ | ✅ | ✅ |
| `payment.manage` | — | — | — | ✅ |
| `user.manage` / `teacher.approve` | — | — | — | ✅ |
| `package.manage` / `system.manage` | — | — | — | ✅ |

**Hai tầng kiểm tra, luôn đi cùng nhau:**
1. `permission` — role có được làm hành động này không → middleware `can:`
2. `Policy` — có được làm trên **bản ghi cụ thể** này không (lesson của tôi? HS lớp tôi? con tôi?)

**Tầng thứ ba cho nội dung:** `AccessControlService::canAccess($user, $lesson)` — so `lesson.access_level` với gói đang `active` của user.

---

## 6. API specification (`/api/v1/`)

Ký hiệu: 🔓 public · 🔒 cần đăng nhập · 👤 role bắt buộc · 💎 cần kiểm tra gói

### Auth
| Method | Endpoint | Ghi chú |
|---|---|---|
| POST | `/auth/register/student` | 🔓 name, email, password, grade_id, birth_year |
| POST | `/auth/register/teacher` | 🔓 → `status=pending` |
| POST | `/auth/register/parent` | 🔓 |
| POST | `/auth/login` | 🔓 throttle 5/phút/IP+email |
| POST | `/auth/logout` | 🔒 |
| GET | `/auth/me` | 🔒 user + roles + subscription hiện tại |
| POST | `/auth/forgot-password` · `/auth/reset-password` | 🔓 |

### Catalog & Learning
| Method | Endpoint | Ghi chú |
|---|---|---|
| GET | `/grades` · `/grades/{id}/subjects` | 🔓 |
| GET | `/subjects/{id}/chapters` · `/chapters/{id}/topics` | 🔓 |
| GET | `/topics/{id}/lessons` | 🔓 (trả `is_locked` cho lesson ngoài gói) |
| GET | `/lessons/{slug}` | 🔒💎 nội dung đầy đủ; ngoài gói → 402 + preview |
| POST | `/lessons/{id}/progress` | 🔒👤student section_type, time_spent |
| POST | `/lessons/{id}/complete` | 🔒👤student |
| GET | `/me/progress` · `/me/progress/topics` | 🔒👤student |

### Practice & Exam
| Method | Endpoint | Ghi chú |
|---|---|---|
| GET | `/practice/questions?topic_id&difficulty&limit` | 🔒💎 |
| POST | `/practice/submit` | 🔒 chấm + cập nhật mastery |
| GET | `/exams` · `/exams/{id}` | 🔒💎 |
| POST | `/exams/{id}/attempts` | 🔒 bắt đầu, trả `attempt_id` + `expires_at` |
| PATCH | `/attempts/{id}/answers` | 🔒 lưu nháp từng câu |
| POST | `/attempts/{id}/submit` | 🔒 chấm, trả điểm |
| GET | `/attempts/{id}/review` | 🔒 xem lại + giải thích |

### AI (§10)
| Method | Endpoint | Ghi chú |
|---|---|---|
| POST | `/ai/chat` | 🔒💎 throttle + quota theo gói |
| POST | `/ai/hint` | 🔒💎 gợi ý từng bước, **không** đưa đáp án |
| POST | `/ai/explain` | 🔒💎 |
| POST | `/ai/check-answer` | 🔒💎 |
| POST | `/ai/similar-exercise` | 🔒💎 |
| POST | `/ai/analyze-mistake` | 🔒💎 |
| GET | `/ai/conversations` · `/ai/conversations/{id}` | 🔒 |
| GET | `/me/recommendations` | 🔒💎 |

### Teacher (👤teacher, đã duyệt)
`/teacher/classes` CRUD · `/teacher/classes/{id}/students` · `/teacher/questions` CRUD ·
`/teacher/exams` CRUD · `/teacher/assignments` CRUD · `/teacher/assignments/{id}/results` ·
`/teacher/students/{id}/report` · `/teacher/comments` ·
`/teacher/ai/generate-questions` · `/teacher/ai/generate-lesson` ·
`/teacher/ai/drafts/{id}/accept|reject`

### Parent (👤parent)
`POST /parent/children/link` (code) · `GET /parent/children` ·
`GET /parent/children/{id}/report` · `GET /parent/children/{id}/recommendations`

### Subscription & Payment
| Method | Endpoint | Ghi chú |
|---|---|---|
| GET | `/packages` | 🔓 giá lấy từ DB |
| GET | `/me/subscription` | 🔒 |
| POST | `/subscriptions/checkout` | 🔒 body **chỉ** `{package_id, payment_method}` |
| POST | `/payment/momo/ipn` | 🔓 **không CSRF**, verify chữ ký, idempotent |
| GET | `/me/payments` | 🔒 |
| — | `GET /payment/momo/return` (web route, không phải API) | 🔓 chỉ hiển thị, không kích hoạt gói |

### Admin (👤admin)
`/admin/users` · `/admin/teachers/pending` · `POST /admin/teachers/{id}/approve|reject` ·
`/admin/packages` CRUD · `/admin/payments` · `/admin/ai/usage` · `/admin/audit-logs`

**Quy ước response:**
```json
{ "success": true,  "data": {…}, "meta": {…} }
{ "success": false, "message": "…", "errors": {"field": ["…"]} }
```
Mã lỗi: `401` chưa đăng nhập · `403` sai quyền · **`402` hết/ngoài gói** · `422` validate · `429` quá quota.

---

## 7. Access Control — nội dung nào cho ai

```php
AccessControlService::canAccess(User $user, string $accessLevel): bool
```
- Lấy subscription `active` mới nhất (`ends_at > now()`), không có → tier `free`.
- Bậc: `free(0) < pro(1) < premium(2)`. Cho phép khi `userTier >= contentTier`.
- Trả `402` kèm `{required_tier, preview}` để frontend hiện paywall thay vì lỗi trống.
- **Quota AI** đọc từ `package_features.key = 'ai.daily_requests'`, đối chiếu `ai_usage` hôm nay → vượt thì `429`.
- Cron hằng đêm `ExpireSubscriptions` chuyển `active` quá hạn → `expired`.

---

## 8. Payment — luồng bắt buộc

```
POST /subscriptions/checkout  { package_id, payment_method }
  └─ PaymentService::checkout()
       ├─ $package = Package::where('is_active',1)->findOrFail($id)
       ├─ $amount  = $package->price                ← giá LẤY TỪ DB
       ├─ DB::transaction: payments(pending) + subscriptions(pending) + order_code duy nhất
       ├─ MomoPaymentService::createPayment() → payUrl
       └─ trả { pay_url, order_code }
```

```
POST /api/v1/payment/momo/ipn   (MoMo → server, KHÔNG CSRF, KHÔNG auth)
  1. Ghi payment_webhook_logs (payload thô) TRƯỚC khi xử lý
  2. Verify HMAC-SHA256 signature  → sai: log + 204, không tiết lộ lỗi
  3. Tìm payment theo order_code    → không có: log + 204
  4. So khớp amount (integer VND)   → lệch: log + đánh dấu nghi vấn + 204
  5. IDEMPOTENCY: payment.status === 'paid' → trả 204 ngay, KHÔNG cấp thêm
  6. DB::transaction + lockForUpdate:
       payment.status = paid, paid_at, gateway_transaction_id
       subscription.status = active, starts_at = now, ends_at = now + duration_days
       (nếu đang có gói active → cộng dồn từ ends_at cũ)
  7. Notification cho user · audit_logs
```

`GET /payment/momo/return` **chỉ** hiển thị "đang xử lý / đã thành công" bằng cách đọc `payments.status` từ DB. Không bao giờ dùng query string của MoMo làm căn cứ kích hoạt.

**Biến `.env`:** `MOMO_PARTNER_CODE`, `MOMO_ACCESS_KEY`, `MOMO_SECRET_KEY`, `MOMO_ENDPOINT`, `MOMO_IPN_URL`, `MOMO_RETURN_URL`.

---

## 9. AI layer

```php
interface AiProviderInterface {
    public function chat(array $messages, array $options = []): AiResponse;
}
```
- `OpenAiProvider` — đọc `OPENAI_API_KEY`, `OPENAI_MODEL` từ `.env`. **Key không bao giờ ra frontend.**
- `FakeProvider` — dùng trong test, trả response cố định, không tốn tiền.
- `PromptBuilder` — system prompt theo mode. Quy tắc cứng trong system prompt:
  - Chế độ **hint**: chỉ gợi ý bước tiếp theo, **cấm** đưa đáp án cuối.
  - Luôn trả lời tiếng Việt, đúng trình độ lớp của học sinh.
  - Công thức bọc `$…$` để KaTeX render.
- `TutorService` — mỗi mode = 1 method, ghi `ai_conversations`/`ai_messages`/`ai_usage`.
- `AiUsageGuard` — kiểm quota **trước** khi gọi provider.
- Request nặng (tạo 10 câu hỏi) → đẩy qua `Jobs\ProcessAiRequest` + polling.
- **Mọi output AI sinh nội dung → `ai_generation_drafts`, người duyệt mới vào bảng thật.**

**Cá nhân hóa (§11):** sau mỗi lần nộp bài → `RecalculateMastery` job cập nhật `student_topic_mastery`; `RecommendationService` lấy topic `mastery_score < 60`, tìm lesson nền của topic đó, ghi vào `recommendations`.

---

## 10. Roadmap — checklist thi hành

### ✅ Phase 0 — Khởi tạo
- [x] Đọc spec, chốt quyết định kỹ thuật
- [x] `composer create-project laravel/laravel` (Laravel 12)
- [x] `.env`: DB `toan_ai`, timezone `Asia/Ho_Chi_Minh`, locale `vi`, placeholder OpenAI/MoMo
- [x] Tạo database `toan_ai` + `toan_ai_test` (utf8mb4)
- [x] Bootstrap 5.3 + Bootstrap Icons + KaTeX + Chart.js qua Vite (gỡ Tailwind)
- [x] `CLAUDE.md` cho repo
- [ ] Commit đầu tiên

### ✅ Phase 1 — Auth, RBAC, Layout
- [x] Migration nhóm 1 (bảng 01–10)
- [x] Model + quan hệ: `User`, `Role`, `Permission`, `Grade`, `StudentProfile`, `TeacherProfile`, `ParentChild`, `AuditLog`
- [x] Seeder: 4 roles, 32 permissions, 12 grades, 5 tài khoản demo
- [x] Custom auth: login / register (3 nhánh) / logout
- [x] `HasRoles` trait + `Gate::before` cho admin + Gate sinh từ bảng `permissions` + middleware `role:`
- [x] Middleware `EnsureAccountIsActive` (chặn teacher `pending`, khoá tài khoản `suspended`)
- [x] Layout Bootstrap 5: `base`, `guest`, `app` (4 portal dùng chung, menu từ `config/navigation.php`)
- [x] Bottom nav (mobile) + sidebar (desktop ≥992px)
- [x] Landing page 10 khối theo §6
- [x] Dashboard cho 4 role, redirect sau login theo role
- [x] Luồng admin duyệt / từ chối giáo viên + `AuditLogger`
- [x] Test: 14 test xanh (đăng ký 3 role, phân quyền chéo, duyệt giáo viên, landing)
- [ ] Quên mật khẩu / đặt lại mật khẩu (hoãn sang đầu Phase 2 — cần cấu hình mail)

**DoD:** ✅ đăng ký 3 role chạy được, admin duyệt teacher được, mỗi role thấy đúng layout, landing responsive.

### Phase 2 — Learning content & Progress
- [ ] Migration nhóm 2 (11–16)
- [ ] Model + Service `LessonService`, `ProgressService`
- [ ] Admin/Teacher CRUD: Grade → Subject → Chapter → Topic → Lesson → Sections
- [ ] Editor lesson có preview KaTeX
- [ ] Trang học sinh: cây chương trình, trang lesson theo 9 khối §8
- [ ] Ghi tiến độ (thời gian học, section hoàn thành)
- [ ] Seeder mẫu: 1 lớp đầy đủ (Lớp 6 → Phân số) để demo
- [ ] `AccessControlService` bản đầu (chưa có subscription → tier free)

**DoD:** học sinh mở được 1 lesson thật, đọc lý thuyết có công thức, tiến độ lưu vào DB.

### Phase 3 — Question Bank & Luyện tập
- [ ] Migration nhóm 3 (17–21)
- [ ] CRUD câu hỏi 6 loại + import CSV
- [ ] `PracticeService` + `GradingService` (chấm tự động 5 loại, essay chờ người chấm)
- [ ] UI làm bài mobile-first, hiện giải thích sau khi trả lời
- [ ] Cập nhật `student_topic_mastery`
- [ ] Test chấm điểm từng loại câu hỏi

### Phase 4 — Exam
- [ ] Migration nhóm 4 (22–25)
- [ ] `ExamService`: start → answer → submit → review
- [ ] Đếm ngược + **server-side `expires_at`** (client chỉ hiển thị)
- [ ] Tự nộp khi hết giờ (job + kiểm tra lúc submit)
- [ ] Trang kết quả + review, Chart.js
- [ ] Chống gian lận cơ bản: 1 attempt in_progress/exam, shuffle

### Phase 5 — Teacher Portal
- [ ] Migration nhóm 5 (26–33)
- [ ] Lớp học: tạo, mã tham gia, thêm/xoá HS
- [ ] Giao bài §17 + theo dõi Đã làm/Chưa làm
- [ ] Dashboard GV §13 + bộ lọc học sinh 5 trạng thái
- [ ] Nhận xét học sinh (`visible_to_parent`)
- [ ] Policy: GV chỉ thao tác trên lớp/HS của mình

### Phase 6 — Parent Portal
- [ ] Liên kết con bằng `link_code` / link / QR
- [ ] Dashboard §14: tiến độ, điểm TB, thời gian học, mạnh/yếu
- [ ] Xem nhận xét GV + báo cáo tuần (job + mail)

### Phase 7 — AI
- [ ] Migration nhóm 6 (34–38)
- [ ] `AiProviderInterface` + `OpenAiProvider` + `FakeProvider`
- [ ] 6 endpoint AI Tutor §10
- [ ] Widget chat nổi trên trang lesson/bài tập, render KaTeX
- [ ] AI cho GV: tạo câu hỏi theo tỉ lệ độ khó, tạo lesson → draft → duyệt
- [ ] `RecommendationService` + hiển thị đề xuất ở dashboard HS & PH
- [ ] `AiUsageGuard` + log usage + trang admin xem chi phí
- [ ] Test bằng `FakeProvider`, không gọi API thật trong CI

### Phase 8 — Package & Subscription
- [ ] Migration nhóm 7 (39–41)
- [ ] Seeder 3 gói Free/Pro/Premium + feature (giá trong DB)
- [ ] `SubscriptionService` + `AccessControlService` bản đầy đủ
- [ ] Middleware `subscription:pro|premium` + trang paywall
- [ ] Trang bảng giá (public + trong app)
- [ ] Job `ExpireSubscriptions` hằng đêm

### Phase 9 — Thanh toán MoMo
- [ ] Migration 42–43
- [ ] `PaymentGatewayInterface` + `PaymentService` + `MomoPaymentService`
- [ ] Checkout → payUrl
- [ ] IPN: chữ ký, order, amount, idempotency, transaction, log
- [ ] Return URL chỉ hiển thị trạng thái
- [ ] Lịch sử thanh toán (user) + quản lý giao dịch (admin)
- [ ] Test: IPN gọi 2 lần chỉ cấp gói 1 lần; chữ ký sai bị từ chối; amount lệch bị chặn

### Phase 10 — Hạ tầng & tối ưu
- [ ] predis + Redis cho cache/session/queue
- [ ] Queue worker + Scheduler (supervisor/task scheduler)
- [ ] Index & query tuning, eager loading
- [ ] Analytics dashboard admin
- [ ] PWA: manifest, service worker, offline lý thuyết đã tải
- [ ] Rate limiting toàn hệ thống, security headers, HTTPS
- [ ] Backup DB, log rotation, deploy checklist

---

## 11. Checklist bảo mật (§29) — kiểm trước mỗi lần release

- [ ] CSRF bật cho toàn bộ route web; **loại trừ đúng 1 route** `payment/momo/ipn`
- [ ] Blade dùng `{{ }}`; chỗ nào `{!! !!}` (nội dung lesson) phải qua HTML Purifier
- [ ] Không `DB::raw` với input người dùng
- [ ] Mọi action ghi đều có Form Request
- [ ] Mọi truy cập bản ghi đều qua Policy — kiểm bằng test "user A đọc dữ liệu user B → 403"
- [ ] Throttle: login 5/phút, AI theo quota gói, IPN 60/phút/IP
- [ ] `bcrypt`/`argon2id`, không log password
- [ ] `.env` không commit; `OPENAI_API_KEY`, `MOMO_SECRET_KEY` chỉ ở server
- [ ] `APP_DEBUG=false` production
- [ ] Audit log cho: duyệt GV, đổi quyền, đổi giá gói, mọi thay đổi payment

---

## 12. Rủi ro đã nhận diện

| Rủi ro | Xử lý |
|---|---|
| Nội dung Toán 12 lớp là khối lượng khổng lồ | Phase 2 chỉ làm **1 lớp mẫu** đầy đủ; dựng công cụ import trước khi nhập đại trà |
| Chi phí AI vượt kiểm soát | `ai_usage` + quota theo gói + cache câu hỏi lặp + dashboard chi phí từ Phase 7 |
| AI đưa lời giải sai | Hiển thị cảnh báo "AI có thể sai"; nội dung sinh ra phải người duyệt; log để rà |
| MoMo sandbox khác production | Tách config theo env; `payment_webhook_logs` để đối soát |
| MariaDB 10.4 + index utf8mb4 quá 767 byte | `Schema::defaultStringLength(191)` trong `AppServiceProvider` |
| Không có ext `redis` | Dùng `predis/predis` (pure PHP) ở Phase 10 |
| Đếm giờ thi bị gian lận | `expires_at` lưu server, kiểm lại lúc submit |

---

## 13. Thứ tự làm việc khuyến nghị

Phase 1 → 2 → 3 → 4 là **trục xương sống**, phải xong và chắc trước khi đụng tới AI hay thanh toán.
Phase 8 (subscription) nên làm **trước** Phase 9 (MoMo) — cấp quyền phải đúng trước khi thu tiền.
Phase 7 (AI) có thể chạy song song với 5–6 nếu có người thứ hai, vì phụ thuộc ít.
