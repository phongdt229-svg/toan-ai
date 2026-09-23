# TOÁN AI — Kế hoạch triển khai (PROJECT PLAN)

> Tài liệu thi hành của [TOAN_AI_SPEC.md](TOAN_AI_SPEC.md). Spec nói **làm gì**, tài liệu này nói **làm thế nào, theo thứ tự nào, xong thì trông ra sao**.
>
> Cập nhật: 2026-09-17 · Trạng thái: **Phase 0–10 đã xong** — roadmap hoàn tất · Hướng dẫn triển khai: [docs/DEPLOY.md](docs/DEPLOY.md)

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
| Múi giờ | `Asia/Ho_Chi_Minh` cho cả lưu DB lẫn hiển thị | Chỉ phục vụ VN; input `datetime-local` không mang múi giờ nên lưu UTC dễ lệch 7 tiếng ở giờ mở/đóng đề |

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
  ├──< placement_tests ──< placement_test_questions ──< placement_test_answers
  ├──< learning_paths ──< learning_path_stages ──< learning_path_items
  ├──< study_sessions
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
| 07 | `student_profiles` | `user_id`(unique), `grade_id`, `birth_date`, `address`, `school`, `self_assessed_level`(average/good/excellent), `math_average_score`decimal(4,2), `tutor_persona`(thay/co), `favorite_color`, `interests`json, `link_code`(unique, để phụ huynh liên kết) — các trường cá nhân hóa theo §33 |
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
| 21b | `question_attempts` | `user_id`, `question_id`, `topic_id`, `context`(practice/exam/assignment), `context_id`, `difficulty`, `answer`json, `is_correct`(null = chờ chấm), `score`, `time_spent_seconds`, `attempt_no`. Index: (user_id, topic_id, created_at) — lịch sử chi tiết cho §11 |
| 21 | `student_topic_mastery` | `user_id`, `topic_id`, `correct_count`, `wrong_count`, `avg_time_seconds`, `mastery_score`(0–100), `last_practiced_at`, unique(user_id,topic_id) |

### Nhóm 4 — Exam (Phase 4)
| # | Bảng | Cột chính |
|---|---|---|
| 22 | `exams` | `title`, `slug`, `grade_id`, `subject_id`, `type`(practice/quiz/test), `duration_minutes`, `total_questions`, `total_points`, `difficulty`, `access_level`, `max_attempts`, `shuffle_questions`, `shuffle_options`, `available_from`, `available_to`, `status`, `created_by`, softDeletes |
| 23 | `exam_questions` | `exam_id`, `question_id`, `sort_order`, `points`, unique(exam_id,question_id) |
| 24 | `exam_attempts` | `exam_id`, `user_id`, `attempt_no`, `started_at`, `expires_at`, `submitted_at`, `score`, `total_points`, `correct_count`, `status`(in_progress/submitted/graded), `auto_submitted`, `question_order`json, `option_order`json. Index: (user_id, exam_id, status), (status, expires_at) |
| 25 | `student_answers` | `exam_attempt_id`, `question_id`, `answer`json, `is_correct`, `score`(null = chờ chấm), `max_score`, `time_spent_seconds`, `graded_by`, `feedback`, `graded_at`, unique(exam_attempt_id,question_id) |

### Nhóm 5 — Teacher (Phase 5)
| # | Bảng | Cột chính |
|---|---|---|
| 26 | `classes` | `name`, `code`(unique, để HS join), `grade_id`, `description`, `owner_teacher_id`, `status`, softDeletes |
| 27 | `teacher_classes` | `class_id`, `teacher_id`, `role`(owner/assistant) |
| 28 | `class_students` | `class_id`, `student_id`, `status`(active/removed), `joined_at`, unique(class_id,student_id) |
| 29 | `assignments` | `class_id`, `teacher_id`, `title`, `description`, `type`(question_set/exam/lesson), `exam_id`(null), `lesson_id`(null), `assign_to_all`, `due_at`, `allow_retry`, `max_attempts`, `status`(published/closed), `published_at`, softDeletes |
| 30 | `assignment_questions` | `assignment_id`, `question_id`, `sort_order`, `points` |
| 31 | `assignment_students` | `assignment_id`, `student_id`, `status`(assigned/submitted/completed), `score`, `max_score`, `percent`, `attempts_count`, `time_spent_seconds`, `is_late`, `completed_at`, unique(assignment_id,student_id) — bảng tổng hợp cho bộ lọc |
| 32 | `assignment_submissions` | `assignment_id`, `student_id`, `attempt_no`, `answers`json, `score`, `max_score`, `correct_count`, `time_spent_seconds`, `is_late`, `submitted_at` — chỉ cho loại bộ câu hỏi |
| 33 | `teacher_comments` | `teacher_id`, `student_id`, `class_id`(null), `lesson_id`(null), `content`, `visible_to_parent` |

### Nhóm 6 — AI (Phase 7)
| # | Bảng | Cột chính |
|---|---|---|
| 34 | `ai_conversations` | `user_id`, `mode`(chat/hint/explain/check_answer/similar/analyze_mistake), `context_type`(lesson/question/exam/free), `context_id`, `title`, `last_message_at` |
| 35 | `ai_messages` | `conversation_id`, `role`(system/user/assistant), `content`, `model`, `tokens_in`, `tokens_out`, `latency_ms`, `meta`json |
| 36 | `ai_usage` | `user_id`, `usage_date`, `feature`, `request_count`, `tokens_in`, `tokens_out`, `cost_estimate`, unique(user_id,usage_date,feature) |
| 37 | `ai_generation_drafts` | `user_id`, `type`(questions/lesson/exam), `input`json, `output`json, `status`(draft/accepted/rejected), `reviewed_by`, `reviewed_at` |
| 38 | `recommendations` | `user_id`, `type`(review_lesson/practice_topic/take_exam), `target_type`, `target_id`, `reason`, `score`, `status`(new/seen/done/expired), `expires_at` |

### Nhóm 6b — Kiểm tra đầu vào & Giáo trình cá nhân hóa (Phase 7, §34–36)
| # | Bảng | Cột chính |
|---|---|---|
| 39 | `placement_tests` | `user_id`, `grade_id`, `status`(in_progress/graded), `started_at`, `expires_at`, `submitted_at`, `auto_submitted`, `total_questions`, `correct_count`, `score`(thang 10), `level_result`, `understanding_percent`, `avg_seconds_per_question`, `weak_topics`json, `analysis`text. Index: (user_id, status) |
| 40 | `placement_test_questions` | `placement_test_id`, `question_id`(null nếu AI sinh bù), `topic_id`, `type`, `difficulty`, `content`, `options`json, `correct_answer`json, `explanation`, `points`, `sort_order` — bản chụp |
| 41 | `placement_test_answers` | `placement_test_id`, `placement_test_question_id`, `answer`json, `is_correct`, `score`, `time_spent_seconds`, unique |
| 42 | `learning_paths` | `user_id`, `grade_id`, `placement_test_id`(null), `status`(active/completed/archived), `items_per_session`, `total_sessions`, `completed_sessions`, `progress_percent`, `generated_at`. Index: (user_id, status) |
| 43 | `learning_path_stages` | `learning_path_id`, `stage`(foundation/consolidation/advanced/exam_practice), `name`, `sort_order`, `status`(locked/in_progress/done), `progress_percent` |
| 44 | `learning_path_items` | `learning_path_stage_id`, `study_session_id`, `item_type`(lesson/practice/exam), `topic_id`, `target_id`, `difficulty`, `origin`(plan/review), `title`, `sort_order`, `status`(pending/done), `completed_at` |
| 45 | `study_sessions` | `user_id`, `learning_path_id`, `session_no`, `status`(planned/quiz_pending/done), `quiz_question_ids`json, `quiz_percent`, `quiz_submitted_at`, `completed_at`, unique(learning_path_id, session_no) |

> **§37 "Kiểm tra cuối buổi"**: đã làm bản mặc định ở Phase 7B (spec nguồn bị cắt) — xem Phase 7B.

### Nhóm 7 — Subscription & Payment (Phase 8–9)
| # | Bảng | Cột chính |
|---|---|---|
| 46 | `packages` | `name`, `slug`(unique), `tier`(free/pro/premium), `price`decimal(12,2), `currency`(VND), `duration_days`(null = vô thời hạn), `description`, `is_default`, `is_active`, `is_highlighted`, `sort_order` |
| 47 | `package_features` | `package_id`, `key`(vd `ai.daily_requests`), `label`, `value`('1'/'0'), `limit_value`(int, null = không giới hạn), `show_on_pricing`, `sort_order`, unique(package_id, key) |
| 48 | `subscriptions` | `user_id`(người dùng gói), `purchased_by`(người trả tiền), `package_id`, `status`(pending/active/expired/cancelled), `price_paid`, `duration_days`, `starts_at`, `ends_at`, `activated_at`, `cancelled_at`, `cancel_reason`, `source`(payment/manual). Index: (user_id, status, ends_at) |
| 49 | `payments` | `user_id`(người trả), `package_id`, `subscription_id`, `order_code`(unique), `amount`decimal(12,2), `currency`, `method`(momo), `status`(pending/paid/failed/cancelled), `gateway_request_id`, `gateway_transaction_id`(unique), `gateway_result_code`, `gateway_message`, `pay_url`, `gateway_response`json, `flag_reason`, `paid_at`, `expires_at`, `client_ip` |
| 50 | `payment_webhook_logs` | `provider`(momo/momo-query), `order_code`, `signature_valid`, `payload`json, `headers`json, `result`, `message`, `ip_address`, `processed_at` — **bắt buộc**, để truy vết IPN |
| 51 | `notifications` | bảng chuẩn Laravel |

**Tổng: 51 bảng.**

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
POST /goi-hoc/{slug}/mua  { con? }        ← không nhận số tiền từ client
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

## 8b. Voucher giảm giá ✅ (23/09/2026)

Nguyên tắc gốc không đổi: **client chỉ gửi chuỗi mã, số tiền vẫn do server tính từ DB.**
Mã giảm giá không phải "giá do người dùng đề nghị" — nó là một phép trừ server tự làm lại từ đầu
ở mỗi bước, kể cả khi người dùng đã thấy giá giảm trên màn hình.

### Bảng

`vouchers`
| Cột | Kiểu | Ghi chú |
|---|---|---|
| `code` | string(32) unique | lưu **in hoa**, người dùng gõ thường vẫn nhận |
| `type` | enum `percent` / `fixed` | |
| `value` | decimal(12,2) | percent: 1–100 · fixed: số tiền VND |
| `max_discount` | decimal(12,2) null | trần cho loại percent ("giảm 50% tối đa 100k") |
| `min_order_amount` | decimal(12,2) null | giá gói tối thiểu mới áp dụng |
| `starts_at` / `ends_at` | dateTime null | null = không giới hạn thời gian |
| `max_uses` | int null | tổng lượt toàn hệ thống · null = vô hạn |
| `max_uses_per_user` | int default 1 | |
| `is_active` | bool | tắt nhanh mà không xoá, giữ lịch sử |
| `created_by` | FK users null | |

`package_voucher` (pivot) — trống = áp dụng mọi gói; có dòng = chỉ các gói được liệt kê.

`voucher_redemptions` — **nguồn sự thật cho việc đếm lượt**, không dùng cột đếm sẵn để khỏi lệch số:
`voucher_id` · `user_id` · `payment_id` (unique) · `discount_amount` · `redeemed_at` null · `released_at` null.

`payments` thêm: `voucher_id` (FK null, `restrictOnDelete`) và `discount_amount` (default 0).
`amount` vẫn là **số tiền thực trả sau giảm** — IPN so khớp cột này, không được đổi ý nghĩa.

### Luồng

```
POST /goi-hoc/{slug}/ma-giam-gia  { code }      ← throttle 10/phút, chống dò mã
  └─ VoucherService::quote(code, package, user) → hiện giá mới, mã lưu trong SESSION
                                                   (session giữ MÃ, không giữ SỐ TIỀN)

POST /goi-hoc/{slug}/mua  { con? }
  └─ PaymentService::checkout()
       ├─ lấy lại mã từ session → VoucherService::redeem() TÍNH LẠI TỪ ĐẦU
       ├─ DB::transaction + lockForUpdate(voucher):
       │     kiểm hạn, lượt tổng, lượt/người, gói áp dụng, giá tối thiểu
       │     → tạo payments(pending, amount = giá − giảm) + voucher_redemptions (giữ chỗ)
       ├─ amount == 0  → KHÔNG gọi cổng: đánh dấu paid, activate() ngay, method='voucher'
       └─ amount > 0   → như cũ, gửi MoMo đúng số tiền đã giảm
```

Trả lại lượt: `markFailed()` / `expireStale()` → `released_at = now()`.
Ghi nhận thật: IPN thành công → `redeemed_at = now()`.

### Quyết định đã chốt

- **Đếm lượt bằng bảng, không bằng cột `used_count`** — hai người bấm cùng lúc mã còn 1 lượt thì
  `lockForUpdate` trên dòng voucher quyết định ai được; cột đếm sẵn dễ lệch khi đơn huỷ/hết hạn.
- **Giữ chỗ ngay khi tạo đơn**, trả lại khi đơn hỏng. Không chờ tới lúc trả tiền mới trừ lượt,
  nếu không mã còn 1 lượt sẽ bị 10 người cùng mang sang MoMo rồi 9 người trả tiền xong mới báo lỗi.
- **Mã giảm 100% không đi qua cổng** — MoMo không nhận đơn 0đ. Kích hoạt thẳng qua
  `SubscriptionService::activate()`, `payments.method = 'voucher'` để sổ sách vẫn có dòng.
- **0 < số tiền < 1.000đ thì từ chối mã** — dưới mức tối thiểu MoMo nhận. Báo rõ cho người dùng
  thay vì để họ bấm sang MoMo rồi gặp lỗi không hiểu.
- **Đơn chờ dùng lại (`$reusable`) phải khớp cả `voucher_id`** — nếu không, người gỡ mã ra vẫn
  bị trả lại đúng đơn giá đã giảm.
- **Báo lỗi cụ thể** ("mã hết lượt", "mã không áp dụng cho gói này") chứ không gộp chung —
  chống dò mã đã có throttle lo; gộp chung chỉ làm khách thật bối rối.
- Admin tạo/sửa/tắt mã tại `/quan-tri/ma-giam-gia`, mọi thay đổi ghi `audit_logs`
  (cùng nhóm với đổi giá gói — đều là thay đổi ảnh hưởng tới tiền).

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
- [x] Commit đầu tiên

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
- [x] **Quên mật khẩu / đặt lại mật khẩu** (làm bù 18/09): `/quen-mat-khau` → mail tiếng Việt `ResetPasswordLink` (không qua queue)
      → `/dat-lai-mat-khau/{token}`. Thông báo **giống hệt nhau** dù email có tài khoản hay không (chống dò email);
      tài khoản `suspended`/`rejected` không nhận được link; throttle 5 lần/phút theo email+IP + broker chặn gửi lại trong 60 giây;
      token sống 60 phút, dùng một lần; đổi xong **thu hồi token API và xoá phiên cũ**, ghi audit `user.password_reset`

**DoD:** ✅ đăng ký 3 role chạy được, admin duyệt teacher được, mỗi role thấy đúng layout, landing responsive.

### ✅ Phase 2 — Learning content & Progress
- [x] Migration bổ sung `student_profiles` theo §33 (ngày sinh, địa chỉ, trường, học lực, điểm TB, avatar thầy/cô, màu, sở thích)
- [x] Form đăng ký học sinh thu đủ trường cá nhân hóa §33 + suy ra học lực từ điểm TB
- [x] Migration nhóm 2 (11–16)
- [x] Model + Service `LessonService`, `ProgressService`
- [x] Admin CRUD cây chương trình: Grade → Subject → Chapter → Topic
- [x] Teacher CRUD bài học + sections + xuất bản/gỡ xuất bản (`LessonPolicy`: chỉ sửa bài của mình)
- [x] Editor lesson có preview KaTeX
- [x] **HtmlSanitizer (HTMLPurifier)** lọc nội dung lúc lưu — view render `{!! !!}`
- [x] Trang học sinh: cây chương trình, trang lesson theo 9 khối §8
- [x] Ghi tiến độ (thời gian học có trần 300s/ping, section hoàn thành, % tính server-side)
- [x] Seeder mẫu: Lớp 6 → Phân số, 2 bài học đầy đủ section
- [x] `AccessControlService` + trang paywall 402 (chưa có subscription → tier free)
- [x] Test: 40 test xanh

**DoD:** ✅ học sinh mở được lesson thật, đọc lý thuyết có công thức, tiến độ lưu vào DB;
giáo viên soạn + xuất bản được bài; admin dựng được cây chương trình.

> **Nợ kỹ thuật ghi nhận (đã trả xong):** chuyển bài học sang chủ đề khác — sửa được ngay trong form
> soạn bài (ô "Chủ đề"); import CSV nội dung — `QuestionImporter` ở Phase 3; quên mật khẩu — Phase 1 bổ sung.

### ✅ Phase 3 — Question Bank & Luyện tập
- [x] Migration nhóm 3 (17–21) + bảng bổ sung `question_attempts` (21b)
- [x] CRUD câu hỏi 6 loại (`QuestionPolicy`: chỉ sửa câu của mình) + form đáp án đổi theo loại
- [x] Import CSV: tệp mẫu tải về, bỏ BOM Excel, báo lỗi theo số dòng, rollback dòng hỏng
- [x] `GradingService`: chấm tự động 5 loại, essay trả "chờ người chấm"
  - Nhiều đáp án: điểm từng phần, **chọn thừa bị trừ** (chặn "chọn hết cho chắc")
  - Điền chỗ trống: điểm chia theo chỗ, nhiều cách viết cho một đáp án
  - Text: bỏ qua hoa/thường, khoảng trắng, dấu phẩy thập phân kiểu Việt
- [x] `PracticeService`: bốc ngẫu nhiên, **bộ câu hỏi giữ ở session** (client không đổi được)
- [x] UI làm bài mobile-first, trang kết quả hiện đáp án đúng + giải thích từng câu
- [x] `MasteryService`: điểm nắm vững có trọng số độ khó, chủ đề yếu cần ≥5 lần làm mới tính
- [x] Gợi ý "Nên ôn lại" ở trang luyện tập từ chủ đề yếu
- [x] Test: 66 test xanh (10 test riêng cho chấm điểm từng loại)

> **Lệch so với plan ban đầu:** thêm bảng `question_attempts`. `student_topic_mastery` chỉ là số tổng hợp;
> §11 yêu cầu theo dõi từng câu hỏi, đúng/sai, thời gian, số lần làm — không có bảng chi tiết thì
> Phase 7 không có dữ liệu để AI phân tích lỗi.

### ✅ Phase 4 — Exam
- [x] Migration nhóm 4 (22–25)
- [x] `ExamService`: start → autosave → submit → review
- [x] Đếm ngược từ **số giây còn lại do server gửi** (không từ đồng hồ máy học sinh), `expires_at` là nguồn sự thật
- [x] Ân hạn 30 giây cho độ trễ mạng; quá ân hạn server từ chối lưu và tự chốt bài
- [x] Tự nộp khi hết giờ: lệnh `exams:finalize-expired` chạy mỗi phút + chốt ngay khi học sinh mở lại trang
- [x] Tự nộp ghi `submitted_at = expires_at`, không phải lúc cron phát hiện
- [x] Chống gian lận: 1 lượt đang làm/đề (khoá theo user), giới hạn số lượt, thứ tự câu/lựa chọn xáo một lần và lưu lại, nộp hai lần vô hại
- [x] Đáp án chỉ lộ khi giáo viên cho phép **và** đề đã đóng — tránh chuyền đáp án cho người làm sau
- [x] Trang kết quả: điểm, thời gian làm, biểu đồ % đúng theo chủ đề (Chart.js, bundle riêng)
- [x] Giáo viên soạn đề §16: chọn tay từ ngân hàng, bốc ngẫu nhiên theo tỉ lệ độ khó (vd 30/50/20), chỉnh điểm/thứ tự
- [x] **Khoá bộ câu hỏi khi đề đã có lượt làm** — đổi câu/điểm lúc này làm sai lệch điểm đã chấm
- [x] Chấm tay câu tự luận: nhận xét, chấm lại được, đồng bộ `question_attempts` + mastery
- [x] Tự luận bỏ trắng tự động 0 điểm, không bắt giáo viên chấm bài rỗng
- [x] Test: 98 test xanh (32 test riêng cho Exam)

> **Quyết định trong phase:**
> - Bỏ trạng thái `expired` khỏi plan ban đầu → lượt quá giờ được **chốt và chấm luôn** (`submitted`/`graded` + cờ `auto_submitted`), để học sinh vẫn có điểm cho phần đã làm.
> - Tự luận đạt ≥ 50% điểm được tính là "đúng" khi tổng hợp mastery — tự luận không có đúng/sai tuyệt đối.
> - Sửa `config/app.php` đọc `APP_TIMEZONE` (trước đó hard-code UTC, giờ mở/đóng đề sẽ lệch 7 tiếng).
> - Đề bị xoá là soft delete — học sinh vẫn xem lại được điểm.
>
> **Nợ kỹ thuật:** tạo đề bằng AI (§16) chờ Phase 7A. Production cần cron `schedule:run` mỗi phút.

### ✅ Phase 5 — Teacher Portal
- [x] Migration nhóm 5 (26–33)
- [x] Lớp học: tạo, mã 6 ký tự (bỏ ký tự dễ nhầm 0/O/1/I/L), đổi mã, lưu trữ lớp
- [x] Học sinh tự vào lớp bằng mã (không phân biệt hoa/thường, khoảng trắng; throttle 10 lần/phút chống dò mã)
- [x] Giáo viên thêm/xoá học sinh bằng email — xoá giữ nguyên lịch sử bài làm và điểm
- [x] Giáo viên phụ: quản lý học sinh và giao bài được; chỉ chủ nhiệm đổi mã / lưu trữ
- [x] Giao bài §17 đủ 3 loại: bộ câu hỏi (bài tập về nhà) · đề kiểm tra · học bài
- [x] Giao cả lớp hoặc chọn học sinh; học sinh vào lớp sau tự nhận bài "cả lớp" còn mở
- [x] Hạn nộp, cho phép làm lại (điểm lấy lượt cao nhất), đóng/mở bài
- [x] Nộp trễ vẫn nhận nhưng gắn cờ; dời hạn thì tính lại cờ trễ
- [x] Theo dõi: Đã làm / Chưa làm / Quá hạn, Điểm, Thời gian, Số lượt — chưa làm xếp trước
- [x] Dashboard GV §13: số lớp, số học sinh, bài đang giao, điểm TB, học sinh cần hỗ trợ, bài sắp đến hạn
- [x] Bộ lọc học sinh 5 trạng thái, ngưỡng hiển thị ngay trên trang
- [x] Nhận xét học sinh (`visible_to_parent`), chỉ người viết xoá được
- [x] Policy: GV chỉ thao tác trên lớp/HS của mình; số liệu chỉ tính bài trong lớp mình
- [x] Test: 139 test xanh (41 test riêng cho Phase 5)

> **Quyết định trong phase:**
> - **Đồng bộ qua event** `ExamAttemptFinished` / `LessonCompleted` → listener `SyncAssignmentProgress` (chạy sau commit).
>   `ExamService` và `ProgressService` không cần biết gì về giao bài.
> - `assignment_students.status` rút gọn còn `assigned / submitted / completed` + cờ `is_late` riêng
>   (plan cũ có `late` là một trạng thái — nhưng "trễ" và "đã xong" xảy ra đồng thời).
> - Thêm cột `percent` để so sánh được giữa bộ câu hỏi và đề có tổng điểm khác nhau.
> - Bỏ trạng thái `draft` của bài giao: §17 kết thúc bằng "Giao bài" — giao là có hiệu lực ngay.
> - **Bộ câu hỏi không nhận câu tự luận** — tự luận giao qua đề kiểm tra (đã có chấm tay).
> - **Bài dạng đề dùng số lượt của chính đề**, không chồng thêm giới hạn của bài giao.
> - Bài dạng đề: chỉ tính lượt làm **sau** khi giao. Bài dạng học bài: học xong từ trước **vẫn tính** —
>   làm lại đề có ý nghĩa, đọc lại lý thuyết đã nắm thì không.
> - Trễ hạn tính theo **lần nộp đầu**: làm lại sau hạn để nâng điểm không bị gắn cờ trễ.
>
> **Ngưỡng bộ lọc** (hằng số trong `StudentInsightService`): điểm thấp < 50% ·
> chưa làm bài = có bài quá hạn chưa nộp · cần hỗ trợ = điểm thấp hoặc ≥ 2 bài quá hạn hoặc ≥ 2 chủ đề yếu ·
> tiến bộ = TB 3 bài gần nhất cao hơn các bài trước ≥ 10 điểm % (cần ≥ 4 bài có điểm).

### ✅ Phase 6 — Parent Portal
- [x] Liên kết con bằng **mã** (throttle 5 lần/phút) / **link ký số** hết hạn sau 7 ngày / **QR** (SVG tự vẽ, không gửi mã ra dịch vụ ngoài)
- [x] Link mở khi chưa đăng nhập → đăng nhập/đăng ký xong quay lại đúng link
- [x] Trang xác nhận "Liên kết với [tên con]?" trước khi liên kết
- [x] Học sinh thấy ai đang xem kết quả của mình, **thu hồi được** — thu hồi thì đổi mã luôn
- [x] Học sinh tự đổi mã → mã, link, QR cũ đều mất hiệu lực
- [x] Phụ huynh huỷ liên kết / liên kết lại
- [x] Dashboard §14 mỗi con: tiến độ chương trình, điểm TB thang 10, thời gian học, bài chưa làm, cảnh báo quá hạn
- [x] Báo cáo chi tiết: chủ đề mạnh/yếu, đề xuất ôn lại, biểu đồ số câu đúng/sai 7 ngày, đề gần đây
- [x] Nhận xét giáo viên — chỉ hiện nhận xét `visible_to_parent`
- [x] Báo cáo tuần qua email: lệnh `reports:weekly-parents` tối Chủ nhật → 1 job/phụ huynh
- [x] Không gửi tuần trống, không gửi trùng trong 6 ngày, tắt được trong Cài đặt
- [x] Bảng `parent_profiles` (cài đặt báo cáo tuần; Phase 8 dùng cho gói học)
- [x] Test: 168 test xanh (29 test riêng cho Phase 6)

> **Định nghĩa số liệu cho phụ huynh** (ghi tại `StudentReportService`):
> - **Tiến độ** = bài học đã hoàn thành / tổng bài đã xuất bản của lớp con đang học. Lớp chưa có bài → "—", không hiện 0%.
> - **Điểm TB (thang 10)** = trung bình % của đề kiểm tra đã chấm xong + bài tập được giao. **Không** tính luyện tập tự do — lúc tập sai là bình thường.
> - **Thời gian học** = thời gian đọc bài + thời gian làm từng câu, chỉ khi có tương tác.
>
> **Quyết định trong phase:**
> - "AI đề xuất ôn lại" ở §14 hiện là **quy tắc cố định** (chủ đề yếu → bài chưa học của chủ đề đó). Trang ghi rõ; Phase 7A thay bằng `RecommendationService`.
> - Gom logic liên kết vào `ChildLinkService` (trước nằm tạm trong `RegistrationService`).
>
> **Lỗi phát hiện khi test:** Mailable không được có thuộc tính `$from` / `$to` — trùng tên người gửi / người nhận của Laravel.

### ✅ Phase 7A — AI Tutor
- [x] Migration nhóm 6 (34–38)
- [x] `AiProviderInterface` + `OpenAiProvider` (timeout, thử lại 1 lần khi 429/5xx) + `FakeProvider` (xếp sẵn câu trả lời cho test)
- [x] 6 endpoint AI Tutor §10 dưới `/api/v1/ai/*` (session + CSRF, dùng từ widget)
- [x] Widget chat nổi (panel phải, full màn hình trên mobile) + nút AI cạnh từng câu ở luyện tập / kết quả / bài giao, render KaTeX
- [x] Trang "AI Tutor" cho học sinh: lượt còn lại, xem lại hội thoại cũ
- [x] Giọng AI theo `tutor_persona` (thầy/cô), lớp, học lực, sở thích — §33, §35
- [x] AI cho GV §12: tạo câu hỏi theo tỉ lệ độ khó (chạy nền) → nháp → **Chấp nhận / Sửa / Tạo lại / Xoá** từng câu
- [x] AI cho GV: soạn bài học → tạo **bài NHÁP**; nút "Viết lại dễ hiểu" / "Tóm tắt" trong trình soạn (có hoàn tác)
- [x] `RecommendationService` (§11) + "Gợi ý học hôm nay" ở dashboard HS và "Đề xuất cho con" ở báo cáo PH
- [x] `AiUsageGuard`: quota/ngày theo vai trò + gói, throttle 10 lượt/phút, ước tính chi phí; trang admin AI usage
- [x] Test bằng `FakeProvider` + `Http::fake` cho OpenAI — không gọi API thật: 215 test xanh (47 test riêng cho AI)

> **Quy tắc sư phạm / chống gian lận** (`TutorAccessGuard`):
> 1. Đang làm đề kiểm tra → **khoá toàn bộ AI**, kể cả chat tự do (dán đề vào chat là ra đáp án).
> 2. Câu thuộc bài giao chưa nộp, hoặc thuộc đề chưa công bố đáp án → **chỉ được Gợi ý**.
> 3. Giải thích / Phân tích lỗi → phải **tự làm câu đó ít nhất một lần** trước.
> 4. Gợi ý: prompt cấm nêu đáp án cuối, và **không gửi đáp án đúng lên AI** ở chế độ này.
>
> **Quyết định trong phase:**
> - **Kiểm tra đáp án: đúng/sai do `GradingService` chấm từ DB**, AI chỉ nhận xét cách làm — model có thể chấm sai, DB thì không.
> - Output AI **luôn escape** (`AiText::toHtml`) — chỉ bật lại in đậm và xuống dòng. HTML AI soạn cho bài học qua `HtmlSanitizer`.
> - Nội dung học sinh gõ luôn ở role `user`, không nối vào system prompt (chống prompt injection).
> - Lỗi provider **không trừ quota** của học sinh nhưng vẫn ghi `failed_count` cho admin.
> - "AI cá nhân hóa" §11 là **thuật toán quy tắc trên dữ liệu làm bài thật**, không gọi LLM: miễn phí, chạy sau mỗi lần nộp
>   (event `MasteryUpdated`), giải thích được. Chuỗi: điểm yếu → kiến thức nền → bài học → bài tập → tăng độ khó → kiểm tra lại.
> - AI soạn cho giáo viên chạy qua queue → **cần `php artisan queue:work`**; admin thấy cảnh báo nếu nháp kẹt > 5 phút.
> - Quota tạm lấy từ `config/ai.php` (Free 20 · Pro 100 · Premium 300 · GV 60/ngày) — Phase 8 chuyển sang `package_features`.
>
> **Chưa làm:** tạo đề kiểm tra bằng AI (§16) — hiện giáo viên dùng AI tạo câu hỏi rồi bốc vào đề.

### ✅ Phase 7B — Kiểm tra đầu vào & Giáo trình cá nhân hóa (§34–37)
- [x] Migration nhóm 6b (39–45) + mở rộng `question_attempts.context` thêm `placement`, `session_quiz`
- [x] `PlacementTestService` §34: 8 câu, 20 phút, bấm giờ server-side, tự nộp khi hết giờ (chung lệnh `exams:finalize-expired`)
  - Đề **lấy từ ngân hàng câu hỏi** (người dùng chốt), tỉ lệ độ khó theo học lực tự đánh giá: TB 4/3/1 · Khá 2/4/2 · Giỏi 1/3/4, trải đều chủ đề
  - Ngân hàng < 5 câu → **AI sinh bù**; AI lỗi → báo "chưa đủ câu hỏi", không bao giờ đưa đề hỏng
  - **Chụp nội dung câu hỏi** lúc ra đề — giáo viên sửa/xoá câu trong ngân hàng không làm đổi bài đã làm
- [x] Chấm bằng `GradingService` → điểm thang 10 → học lực (≤5 TB · ≤8 Khá · >8 Giỏi), **mức độ hiểu** (% đúng có trọng số độ khó),
      **tốc độ** (giây/câu), **nhóm kiến thức yếu** (chủ đề < 50%), nhận xét bằng AI (lỗi AI không chặn kết quả)
- [x] `LearningPathService` §35: lộ trình 4 giai đoạn
  - **Nền tảng**: chủ đề yếu + chủ đề đứng ngay trước nó (kiến thức nền); học sinh Giỏi bắt đầu từ mức TB
  - **Củng cố**: các chủ đề còn lại · **Nâng cao**: câu Khó · **Luyện đề**: đề đã xuất bản của lớp
  - Chỉ tạo mục luyện tập khi có câu để luyện; bài đã học từ trước tính là xong
- [x] Chia thành **buổi học** 3 mục/buổi (§36)
- [x] **Tự điều chỉnh** qua event: học xong bài / luyện ≥ 5 câu / làm đề → mục tự xong; chủ đề đã học mà tụt xuống yếu → chèn mục ôn tập
- [x] **Kiểm tra cuối buổi §37** (bản mặc định): 5 câu về chủ đề của buổi; ≥ 50% hoàn thành; chưa đạt vẫn sang buổi mới
      nhưng buổi sau có thêm mục ôn đúng chủ đề còn sai
- [x] Dashboard §36: % hoàn thành lộ trình · buổi đã học / còn lại · điểm TB · kiến thức cần củng cố · **Gợi ý học hôm nay = buổi hiện tại**
- [x] Báo cáo phụ huynh: tiến độ lộ trình theo giai đoạn + kết quả đầu vào
- [x] Test: 249 test xanh (34 test riêng cho 7B)

> **Quyết định trong phase:**
> - Học sinh học **theo nhịp riêng**: buổi học là tuần tự (buổi 1, 2, 3…), không gắn cứng vào ngày lịch —
>   bỏ ràng buộc `unique(user_id, session_date)` của plan cũ, thay bằng `unique(learning_path_id, session_no)`.
> - Làm lại kiểm tra đầu vào → lộ trình cũ **lưu trữ** (không xoá), sinh lộ trình mới.
> - Lượt AI hệ thống dùng cho đầu vào (sinh câu bù, nhận xét) **ghi chi phí nhưng không trừ quota** hỏi AI của học sinh.
> - Giai đoạn không có mục nào chỉ tính "xong" khi các giai đoạn trước đã xong.
>
> **§37 là bản mặc định** vì spec nguồn bị cắt nội dung — cần chỉnh lại khi có đặc tả đầy đủ.

### ✅ Phase 8 — Package & Subscription (§18–19)
- [x] Migration nhóm 7 (46–48)
- [x] `PackageSeeder`: Free (mặc định) · Pro 1/12 tháng · Premium 1/12 tháng — giá chỉ là giá khởi tạo, chạy lại không ghi đè giá admin đã sửa
- [x] Khoá tính năng trong `package_features` (hệ thống đọc thật):
  | Khoá | Free | Pro | Premium |
  |---|---|---|---|
  | `ai.daily_requests` | 10 | 50 | 200 |
  | `practice.daily_questions` | 30 | ∞ | ∞ |
  | `ai.advanced_modes` (Phân tích lỗi, Bài tương tự) | ✗ | ✗ | ✓ |
  | `reports.advanced` (biểu đồ + đề xuất trong báo cáo phụ huynh) | ✗ | ✗ | ✓ |
  Dòng `display.*` chỉ để hiện trên bảng giá.
- [x] `SubscriptionService`: gói hiệu lực (tier cao nhất), `limit()`/`allows()`, `createPending` (chụp giá từ DB), `activate` (idempotent, **cộng nối** sau gói cùng hạng còn hạn), `grant`/`cancel` (audit log)
- [x] `AccessControlService` đọc tier từ subscription; giáo viên/admin luôn xem được mọi nội dung
- [x] Paywall: bài học khoá → trang bảng giá; AI nâng cao → `402 {reason: upgrade_required, upgrade}` + nút nâng cấp trong widget; hết lượt luyện tập → về bảng giá kèm thông báo
- [x] Trang bảng giá `/goi-hoc` (public, dùng chung khi đã đăng nhập) + landing đọc giá từ DB · trang xác nhận mua `/goi-hoc/{slug}/mua`
- [x] Học sinh "Gói của tôi" (lượt AI/luyện tập hôm nay, lịch sử) · Phụ huynh "Gói học" (gói từng con, gói đã mua cho con)
- [x] Admin: CRUD gói + quyền lợi (audit đổi giá, không xoá gói mặc định / gói đã bán) · danh sách đăng ký, cấp tay, huỷ
- [x] Lệnh `subscriptions:expire` 00:05 hằng ngày: quá hạn → `expired`, chờ thanh toán > 24h → `cancelled`
- [x] API `GET /api/v1/packages`, `/api/v1/me` thêm `subscription`
- [x] Test: 278 test xanh (29 test riêng cho Phase 8)

> **Quyết định trong phase:**
> - Quyền truy cập dựa vào `ends_at`, **không chờ job** — job hết hạn chạy trễ cũng không cho dùng lố.
> - Gói thuộc **học sinh** (`user_id`); phụ huynh là `purchased_by`. Báo cáo nâng cao của phụ huynh theo gói của con.
> - Không làm middleware `subscription:pro|premium` theo route: không có route nào cần hạng cố định — khoá ở mức nội dung
>   (`lessons.access_level`) và tính năng (`package_features`), kiểm tra trong service để API và web dùng chung.
> - DB chưa có gói nào → không khoá gì, quota AI dùng `config/ai.php` (tránh khoá cứng hệ thống mới cài).
> - Hết lượt luyện tập: bộ câu phát ra bị cắt bằng số lượt còn lại, không để vượt giới hạn giữa chừng.
> - Chưa tự gia hạn (bỏ cột `auto_renew`): luồng MoMo `captureWallet` là thanh toán một lần; trừ tiền định kỳ cần tích hợp liên kết ví riêng.

### ✅ Phase 9 — Thanh toán MoMo (§20–25)
- [x] Migration 49–50
- [x] `PaymentGatewayInterface` + `PaymentService` + `MomoGateway` (API v2 `captureWallet`, HMAC-SHA256) + `FakeMomoGateway`
- [x] Checkout → payUrl: giá lấy từ DB, bấm nhiều lần dùng lại đơn còn hạn, lỗi cổng → đơn `failed` + báo lỗi
- [x] IPN `POST /api/v1/payment/momo/ipn`: log thô trước → chữ ký (+ partnerCode) → tìm đơn (`lockForUpdate`) → idempotency
      → so số tiền nguyên VND (lệch: gắn `flag_reason`, không cấp) → cập nhật đơn + `SubscriptionService::activate` trong một transaction
      → audit log + email `PaymentSucceeded` (queue). Luôn trả 204.
- [x] Return URL `/payment/momo/return` chỉ chuyển tới trang kết quả đọc DB; trang kết quả tự hỏi trạng thái mỗi 3 giây
- [x] **Đối soát**: IPN không tới (localhost, sự cố mạng) → hỏi MoMo `/v2/gateway/api/query` khi mở trang kết quả, khi admin bấm,
      và trước khi huỷ đơn quá hạn (`payments:expire-pending` 5 phút/lần, đơn sống 30 phút)
- [x] Lịch sử thanh toán `/thanh-toan` (học sinh/phụ huynh) · Admin `/quan-tri/giao-dich`: doanh thu tháng, đơn nghi vấn, IPN sai chữ ký, log IPN từng đơn, nút đối soát
- [x] **Giả lập MoMo ở local** (`PAYMENT_GATEWAY=fake`): trang `/thanh-toan/{order}/gia-lap` tạo IPN ký đúng chuẩn và đi qua đúng `handleNotification` — bị chặn ở production
- [x] Test: 19 test — IPN 2 lần chỉ cấp 1 lần; chữ ký sai / sửa payload sau khi ký / sai partnerCode bị từ chối; lệch tiền bị chặn;
      return URL không tin query string; người khác không xem được đơn; giả lập chạy luồng IPN thật (297 test xanh)

> **Quyết định trong phase:**
> - IPN thất bại rồi sau đó IPN thành công (hoặc đơn đã huỷ vì hết hạn mà MoMo báo đã trả) → **vẫn cấp gói**: tiền đã trừ thì phải giao hàng.
> - Mã MoMo 1000/7000/7002 là "đang xử lý" — không đánh dấu thất bại.
> - `MOMO_ENDPOINT` chỉ là host (`https://test-payment.momo.vn`), path do code ghép.
> - Chưa làm hoàn tiền tự động: admin huỷ đăng ký ở trang Đăng ký gói, hoàn tiền thao tác trên cổng MoMo.

### ✅ Phase 10 — Hạ tầng, tối ưu & hoàn thiện quản trị
- [x] `predis/predis` — production bật Redis bằng env (`CACHE_STORE`/`SESSION_DRIVER`/`QUEUE_CONNECTION=redis`); local giữ database/file
- [x] Queue worker + Scheduler: mẫu Supervisor [deploy/supervisor](deploy/supervisor/toan-ai-worker.conf), cron, hướng dẫn Task Scheduler Windows
- [x] Index cho truy vấn nóng (migration `add_reporting_indexes`): đếm câu luyện tập/ngày, người dùng mới, doanh thu, log IPN, tiến độ bài học.
      Eager loading được `Model::shouldBeStrict()` ép từ Phase 1
- [x] **Analytics dashboard admin** (`AnalyticsService`, cache 10 phút + nút làm mới): doanh thu tháng, học sinh hoạt động 7/30 ngày,
      người dùng mới, gói trả phí theo hạng, chi phí AI, nội dung đã xuất bản, biểu đồ 30 ngày, chủ đề học sinh yếu nhất
- [x] **PWA**: `manifest.webmanifest` + icon, `sw.js` (asset cache-first; trang bài học network-first lưu 30 bài gần nhất để đọc offline;
      không cache API/làm bài/thanh toán), `offline.html` liệt kê bài đã lưu, đăng xuất xoá cache bài học
- [x] **Bảo mật HTTP**: middleware `SecurityHeaders` (nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy, CSP cơ bản,
      HSTS khi HTTPS, `Cache-Control: no-store` cho trang đã đăng nhập) · rate limit chung `throttle:global`
      (240/phút theo IP khách, 300/phút theo user) · `TRUSTED_PROXIES` · `URL::forceScheme('https')` ở production
- [x] **Sao lưu**: `backup:database` (mysqldump `--single-transaction` → `.sql.gz`, mật khẩu qua `MYSQL_PWD`, xoá bản cũ) chạy 02:00 ở production;
      log xoay vòng `LOG_STACK=daily`; dọn `failed_jobs`, token Sanctum hết hạn
- [x] Deploy checklist + mẫu Nginx: [docs/DEPLOY.md](docs/DEPLOY.md), [deploy/nginx](deploy/nginx/toan-ai.conf)
- [x] **Quản trị người dùng** `/quan-tri/nguoi-dung`: tìm theo tên/email/SĐT, lọc vai trò/trạng thái; hồ sơ theo vai trò
      (gói học, phụ huynh, lớp, thanh toán, nhật ký liên quan); **khoá / mở khoá** có lý do, thu hồi token + xoá phiên, không tự khoá mình
      và luôn còn ≥ 1 admin hoạt động
- [x] **Audit log** `/quan-tri/audit-log`: chỉ đọc, lọc theo hành động / email người làm / khoảng ngày, xem giá trị trước–sau, xuất CSV
- [x] **Báo cáo lớp cho giáo viên** `/giao-vien/bao-cao`: tỉ lệ nộp bài, điểm TB, học sinh cần hỗ trợ, tiến độ từng bài giao,
      chủ đề cả lớp còn yếu (biểu đồ), bảng học sinh, xuất CSV
- [x] `SampleAnalyticsSeeder` (chỉ local/testing): 12 học sinh demo + 8 chủ đề Lớp 6 + câu hỏi tự sinh (tự tính đáp án nên luôn đúng)
      + lịch sử luyện tập 30 ngày + kết quả bài giao → dashboard quản trị và báo cáo lớp có dữ liệu để xem ngay
- [x] Test: 317 test xanh (18 test mới: header bảo mật, rate limit, PWA, backup, analytics, người dùng/audit, báo cáo lớp, dữ liệu demo)

> **Quyết định trong phase:**
> - CSP **chưa** siết `script-src`: nhiều view còn `<script>` inline (autosave làm bài, biểu đồ…). Muốn siết phải chuyển sang file JS + nonce.
> - Analytics cache 10 phút thay vì bảng tổng hợp riêng — đủ nhanh ở quy mô hiện tại; khi dữ liệu lớn chuyển sang job tổng hợp hằng đêm.
> - PWA chỉ lưu **trang bài học đã mở**, không tải trước toàn bộ chương trình (tốn dung lượng máy học sinh, lộ nội dung Pro).
> - Không có chức năng đổi vai trò người dùng trên giao diện (rủi ro leo quyền) — cần thì làm qua seeder/tinker có audit.

---

### ✅ Bổ sung sau roadmap (18/09)
- [x] **Quên mật khẩu** (trả nợ Phase 1) — xem mục Phase 1
- [x] **Trang chủ mới**: hero có khung điện thoại giả lập, minh hoạ SVG nội tuyến cho 3 nhóm người dùng,
      chương trình nhóm theo Tiểu học / THCS / THPT
- [x] **Điều khoản sử dụng** `/dieu-khoan-su-dung` và **Chính sách bảo mật** `/chinh-sach-bao-mat`
      (nội dung mô tả đúng hệ thống đang chạy; thông tin đơn vị vận hành lấy từ `config/site.php`)
- [x] **Logo mới** `<x-brand>` (chữ "Math AI") dùng chung cho header, sidebar, footer
- [x] **Giao diện bảng**: `_tables.scss` + `<x-pagination>` ("Hiển thị 1–20 trong 49 câu hỏi"),
      view phân trang tiếng Việt thay bản mặc định tiếng Anh của Laravel
- [x] **Hỗ trợ & báo lỗi nội dung** `/ho-tro`: form có **captcha phép tính** (đáp án trong session, đọc được
      bằng trình đọc màn hình, không gọi dịch vụ ngoài) + honeypot + throttle 5 yêu cầu/giờ;
      email báo về hộp thư hỗ trợ; trang Quản trị → Hỗ trợ để lọc, xem và đổi trạng thái (có audit log);
      link "Báo lỗi nội dung" đặt ngay dưới mỗi bài học

### ✅ Bổ sung sau roadmap (19/09)
- [x] **Giới hạn phạm vi AI về Toán học**: vá `ContentGeneratorService::rewrite()` thiếu câu chặn ngoài lề
      (Tutor đã có sẵn, xem `PromptBuilder::system()`); thống nhất một cụm từ chối cố định
      ("không thuộc phạm vi Toán học") để nhận diện được lượt từ chối đúng cách
- [x] **`ScopeGuard`** (`app/Services/AI/ScopeGuard.php`): rào chắn thứ hai cho AI Tutor — không chặn câu
      trả lời, chỉ ghi audit log `ai.off_topic_suspected` khi tin nhắn học sinh có vẻ ngoài lề mà AI không
      từ chối đúng cách; cờ lưu kèm trong `ai_messages.meta`. Trang Quản trị → AI usage hiện số lượt nghi
      ngờ 30 ngày, link sang nhật ký thao tác đã lọc sẵn
- [x] Ghi chính thức yêu cầu "AI chỉ nói về Toán" vào `TOAN_AI_SPEC.md` §10/§12/§29 — trước đó chỉ tồn tại
      trong code, dễ bị bỏ sót khi đổi provider/model
- [x] **Thông báo trong app** (bảng `notifications` chuẩn của `Illuminate\Notifications`, kênh database):
      chuông + số chưa đọc trên header (`layouts/app.blade.php`), trang "Xem tất cả" (`/thong-bao`).
      Sự kiện báo: học sinh có điểm đề kiểm tra (`ExamResultReady`, chỉ khi đã `graded` — còn tự luận chờ
      chấm thì chưa báo), giáo viên có học sinh nộp bài giao (`AssignmentSubmittedByStudent`, cả đề lẫn bài
      học), giáo viên được duyệt (`TeacherAccountApproved`), phụ huynh có con điểm thấp dưới 50%
      (`ChildScoreLow`) hoặc báo cáo tuần đã gửi (`WeeklyReportReady`), yêu cầu hỗ trợ được xử lý xong
      (`SupportTicketResolved`, chỉ tài khoản có đăng nhập), thanh toán thành công (`PaymentSucceeded` —
      thêm kênh database vào thông báo email đã có). Bắn qua listener mới `SendActivityNotifications`
      (nghe `ExamAttemptFinished`/`LessonCompleted`, cùng quy ước với `SyncAssignmentProgress`) — không gọi
      thẳng từ `ExamService`.

### ✅ Bổ sung sau roadmap (20/09)
- [x] **Trang cài đặt tài khoản** cho cả 4 portal (`{student,teacher,parent,admin}.settings`) — đổi
      tên/SĐT (`AccountService::updateProfile`) và đổi mật khẩu (validate `current_password` + độ mạnh
      như lúc đăng ký). Dùng chung 1 service + 2 Form Request + 2 partial Blade
      (`partials/profile-form.blade.php`, `partials/password-form.blade.php`) cho cả 4 nơi, mỗi portal chỉ
      có 1 controller mỏng riêng. Trang cài đặt phụ huynh giữ nguyên phần bật/tắt báo cáo tuần đã có, chỉ
      thêm 2 phần mới cạnh đó. **Chưa làm avatar** (cột `avatar` vẫn chưa gắn upload/Storage) — cố tình cắt
      phạm vi, để riêng nếu cần.
- [x] **Cài đặt thông báo theo loại**: cột `users.notification_preferences` (JSON, mảng key bị tắt —
      opt-out nên user cũ mặc định bật hết). `App\Support\NotificationType::LABELS` là danh sách tập trung
      7 loại; `via()` của từng Notification tự kiểm `User::hasMutedNotification()` trước khi thêm kênh
      `database` — `PaymentSucceeded` cố tình chỉ cho tắt chuông trong app, **không cho tắt email biên
      nhận** (bằng chứng giao dịch). Giao diện: `partials/notification-preferences-form.blade.php`, dùng
      chung cả 4 trang cài đặt.
- [x] **Ô tìm kiếm chung cho giáo viên** (`teacher.search`, `/giao-vien/tim-kiem`): tìm xuyên suốt bài học +
      câu hỏi + học sinh trong một ô, thay vì mỗi trang một filter riêng. Bài học/câu hỏi lọc theo
      `created_by` (không thấy nội dung giáo viên khác); học sinh tái dùng
      `StudentInsightService::studentsFor()` để tự động giới hạn đúng học sinh trong lớp mình dạy. Hộp tìm
      hiện trên header desktop của portal giáo viên (`layouts/app.blade.php`), mobile vào mục "Tìm kiếm"
      trong menu.
- [x] **Trung tâm hướng dẫn** `/huong-dan`: 17 bài theo 4 nhóm (học sinh, giáo viên, phụ huynh, tài khoản & thanh toán),
      tìm kiếm không dấu, lọc theo vai trò; nội dung ở `config/guides.php` nên không cần DB.
      Lối vào: header trang chủ, footer, menu tài khoản trong app (mở đúng nhóm theo vai trò) và form hỗ trợ

### ✅ Bổ sung sau roadmap (21/09)
- [x] **Menu mobile**: nút hamburger + offcanvas `components/mobile-menu.blade.php`. Trước đó màn hình nhỏ chỉ
      có 4 ô bottom nav nên phần lớn mục menu không vào được.
- [x] **Xác thực email**: `User implements MustVerifyEmail`, notification `VerifyEmailLink` (link ký, hạn 60
      phút), controller `Auth\EmailVerificationController`, dải nhắc trên đầu trang trong `layouts/app`.
      Laravel 12 không tự đăng ký `SendEmailVerificationNotification` nên phải `Event::listen` trong
      `AppServiceProvider`. **Quyết định**: chưa xác thực vẫn học được, chỉ chặn mua gói (`verified` ở
      `packages.checkout`/`packages.pay`) — khoá chặt hơn sẽ làm người dùng mới không dùng được gì.
      Migration `mark_existing_users_as_verified` backfill user cũ để không khoá nhầm ai.
- [x] **Xoá tài khoản** (thực thi cam kết trong Chính sách bảo mật): `AccountDeletionService`
      (yêu cầu → soft delete ngay → 30 ngày → `anonymise()`), khung "Vùng nguy hiểm" ở 3 trang cài đặt,
      lệnh `accounts:purge` chạy 03:00, quản trị có bộ lọc "Chờ xoá" + nút Khôi phục.
      **Ẩn danh chứ không xoá cứng**: điểm số, bài nộp, hoá đơn còn gắn với lớp học và sổ sách kế toán.
      Chặn quản trị viên duy nhất tự xoá. Thêm bảng chứa dữ liệu cá nhân → phải bổ sung vào `anonymise()`.
- [x] **Trang lỗi tuỳ biến** 403/404/419/429/500 + **trang bảo trì** 503 (`resources/views/errors/`),
      dùng chung `errors/layout.blade.php`. Cố ý **không** `@vite`/`csrf`/DB/`route()` để hiện được cả khi
      hệ thống hỏng hoặc đang deploy. Deploy dùng `php artisan down --render="errors::503" --secret=...`
      (script `deploy/deploy.sh` luôn `artisan up` kể cả khi có bước hỏng).
- [x] **Bật/tắt bảo trì trong trang quản trị** (`/quan-tri/bao-tri`): nhập thời gian dự kiến, tích ô xác nhận,
      có audit log. Người bật được cấp cookie bỏ qua ngay để **không tự khoá mình ra ngoài**, kèm link chia sẻ
      cho người cần kiểm tra bản mới. `up`, `dang-nhap`, `quan-tri/bao-tri` không bị chặn — đường cứu hộ khi
      mất cookie, khỏi phải SSH.
- [x] **Chia sẻ mạng xã hội + SEO**: thẻ OG/Twitter trong `layouts/base`, ảnh `public/og-cover.png`
      (1200×630), `/sitemap.xml` sinh từ `SitemapController`, `robots.txt` chặn khu vực sau đăng nhập và
      trỏ tới sitemap; trang sau đăng nhập gắn `noindex,nofollow`.
- [x] **CI**: `.github/workflows/ci.yml` chạy `php artisan test` (PHP 8.2 + MariaDB 10.4 + `npm run build`)
      trên mỗi push/PR vào `main`. Chưa bật job Pint — code cũ còn 66 file lệch chuẩn, dọn riêng một lượt.
- [x] Hướng dẫn: thêm bài "Xoá tài khoản", bổ sung bước xác thực email vào bài "Tài khoản, mật khẩu và bảo mật".

### ✅ Bổ sung sau roadmap (23/09)
- [x] **Mã giảm giá** (§8b) — percent/fixed, trần giảm, đơn tối thiểu, thời gian, tổng lượt, lượt mỗi người,
      giới hạn theo gói. Giữ chỗ lúc tạo đơn → trả lại khi đơn hỏng; mã 100% không đi qua cổng.
      Quản trị có CRUD + audit log. 18 test.
- [x] **Google Analytics 4 + Google Tag Manager + xác minh Search Console** — khai ở `config/site.php`,
      trống ở local/test để không làm bẩn số liệu. Kéo theo việc sửa Chính sách bảo mật §3 và §8
      (trước đó đang khẳng định "không theo dõi bạn sang website khác" — câu đó thành sai khi bật GA).
- [x] **Link mạng xã hội ở footer** — Facebook, YouTube, TikTok, X, Google; chưa khai URL thì ẩn.

### Còn nợ — rà lại 23/09/2026 (cập nhật sau đợt voucher + analytics)

Xếp theo thứ tự nên làm. Roadmap Phase 0–10 và 4 trụ cột ở spec §38 đã xong, nên phần dưới đây
là **toàn bộ** việc còn lại đã biết.

#### A. Chặn đường bán hàng

- [ ] **Nội dung thật** — mới 1/12 lớp có dữ liệu (4 bài học, 49 câu hỏi, 1 đề tính tới 21/09).
      Công cụ đã đủ cả: soạn bài, `QuestionImporter` nhập CSV, AI soạn nháp, ra đề. Thiếu **người nhập**,
      không phải thiếu code. Đây là thứ duy nhất chặn giữa "chạy được" và "bán được".
- [ ] **Nhắc gói sắp hết hạn** *(~nửa buổi)* — mất doanh thu gia hạn.
      Admin thấy ô "Hết hạn trong 7 ngày" (`Admin\SubscriptionController`), phụ huynh thấy "còn N ngày"
      (`parent/dashboard.blade.php`), nhưng **không có email hay thông báo nào gửi đi**: gói cứ thế hết hạn.
      Làm: thêm Notification + nhánh "còn 7/3/1 ngày" vào lệnh `subscriptions:expire` (đã chạy 00:05).

#### B. Cam kết đã hứa nhưng chưa có công cụ

- [ ] **Trang chủ quảng cáo tính năng không có** — `public/partials/hero.blade.php` có chip
      "+10 điểm" và "7 ngày liên tiếp", trong khi code **không có** streak hay điểm thưởng nào.
      Hai đường: bỏ 2 chip *(~1 giờ)*, hoặc làm gamification thật *(~2 buổi)*. **Chưa có quyết định.**
- [ ] **Bảng hỏi đồng ý cookie** *(~nửa buổi)* — **phát sinh từ đợt bật GA/GTM ngày 23/09.**
      Nghị định 13/2023/NĐ-CP yêu cầu có sự đồng ý trước khi xử lý dữ liệu cá nhân; hiện GA chạy ngay
      từ lượt truy cập đầu, người dùng chỉ được *thông báo* trong Chính sách bảo mật chứ không được *chọn*.
      Cần dải hỏi đồng ý: từ chối thì không nạp gtag/GTM (nhóm cookie bắt buộc vẫn chạy).
      Phần lớn người dùng là trẻ em nên chỗ này không nên để nợ lâu.
- [ ] **Tự tải bản sao dữ liệu** *(~1 buổi)* — Chính sách bảo mật §7 hứa "yêu cầu bản sao dữ liệu học tập",
      hiện chỉ xử lý tay qua email. Đúng y tình trạng của "xoá tài khoản" trước khi làm ở đợt 21/09.
      Làm theo cùng khuôn: service xuất JSON/CSV + nút trong trang Cài đặt.

#### C. Tính năng spec có mà code chưa có

- [ ] **Tạo đề kiểm tra bằng AI** (spec §16) — `AiGenerationDraft` mới có `questions` và `lesson`.
      Giáo viên đang phải nhờ AI sinh câu rồi tự bốc vào đề. Xem ghi chú cuối Phase 7A.
- [ ] **Nhắc bài giao sắp đến hạn** *(~nửa buổi)* — hiện chỉ báo khi *đã có* kết quả
      (`SendActivityNotifications`), không nhắc trước deadline dù `AssignmentStudent` đủ dữ liệu.
- [ ] **Avatar** — cột `users.avatar` vẫn chưa gắn upload/Storage (cắt phạm vi từ đợt 20/09).
- [ ] **Giảm giá không hiện ở chỗ đối soát** *(~1 giờ)* — `payments.voucher_id` và `discount_amount` đã lưu
      nhưng trang Quản trị → Giao dịch và email biên nhận `PaymentSucceeded` đều không nhắc tới.
      Kế toán nhìn đơn 69.300₫ của gói 99.000₫ sẽ không biết vì sao.
- [ ] **Danh sách lượt dùng của một mã** *(~nửa buổi)* — quản trị mới thấy con số tổng (đã dùng / đã giảm),
      chưa xem được ai dùng, đơn nào, lúc nào. Thiếu cái này thì không soi được mã bị lạm dụng.
- [ ] **URL mạng xã hội thật** — footer đã sẵn sàng nhưng 5 khoá trong `config/site.php` còn trống,
      nên khối icon đang ẩn. Chờ link thật, không bịa.

#### D. Nợ kỹ thuật

- [ ] `vendor/bin/pint` một lượt cho toàn repo rồi bật job lint trong CI
      (66 file lệch chuẩn từ trước — bật ngay là CI đỏ không liên quan tới thay đổi đang đẩy).

#### Cố tình không làm — vẫn giữ nguyên quyết định

Hoàn tiền tự động (admin huỷ đăng ký, hoàn tiền thao tác trên cổng MoMo) · middleware
`subscription:pro|premium` theo route (khoá ở mức nội dung đúng hơn) · spec §37 bị cắt nội dung nguồn
nên đang chạy bản mặc định ghi ở Phase 7B.

---

## 11b. Kết quả rà checklist bảo mật — 2026-09-17 (Phase 10)

- CSRF: chỉ loại trừ `api/v1/payment/momo/ipn` (`bootstrap/app.php`) ✓
- `{!! !!}`: chỉ dùng cho nội dung bài học/câu hỏi/đề/bài giao (lọc HTMLPurifier khi lưu ở model/request), SVG QR do server sinh, output AI qua `AiText::toHtml` ✓
- Raw SQL: toàn chuỗi tĩnh hoặc binding `?` — không nối input người dùng ✓
- Throttle: đăng nhập 5/phút, AI 10/phút + quota ngày theo gói, IPN 60/phút/IP, chung 240–300/phút ✓
- Audit log: duyệt GV, khoá/mở khoá tài khoản, đổi giá/gói, cấp/huỷ gói, thanh toán thành công ✓ — chưa có chức năng đổi quyền (không cần log)
- `APP_DEBUG=false`, key chỉ ở server: nằm trong checklist triển khai [docs/DEPLOY.md](docs/DEPLOY.md) — kiểm lại mỗi lần phát hành

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
| Không có ext `redis` | Đã cài `predis/predis` (pure PHP) ở Phase 10 |
| Đếm giờ thi bị gian lận | `expires_at` lưu server, kiểm lại lúc submit |

---

## 13. Thứ tự làm việc khuyến nghị

Phase 1 → 2 → 3 → 4 là **trục xương sống**, phải xong và chắc trước khi đụng tới AI hay thanh toán.
Phase 8 (subscription) nên làm **trước** Phase 9 (MoMo) — cấp quyền phải đúng trước khi thu tiền.
Phase 7A (AI Tutor) có thể chạy song song với 5–6 nếu có người thứ hai, vì phụ thuộc ít.
Phase 7B (placement test + giáo trình) **phải sau 3 và 7A** — cần ngân hàng câu hỏi để sinh đề và cần provider AI để chấm.

**Giờ làm gì tiếp:** toàn bộ 10 phase đã xong, nên việc còn lại nằm ở mục
[Còn nợ](#còn-nợ--rà-lại-23092026) cuối §10 — xếp sẵn theo thứ tự nên làm. Nhóm A (nội dung + nhắc gia hạn)
là thứ đang chặn doanh thu; nhóm B là cam kết đã hứa với người dùng mà chưa có công cụ thực thi.
