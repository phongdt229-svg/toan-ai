<?php

use App\Http\Controllers\Admin\AiUsageController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\TeacherApprovalController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\Auth\AccountDeletionController;
use App\Http\Controllers\Auth\EmailChangeController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ParentPortal\ChildController as ParentChildController;
use App\Http\Controllers\ParentPortal\DashboardController as ParentDashboard;
use App\Http\Controllers\ParentPortal\SettingsController as ParentSettingsController;
use App\Http\Controllers\ParentPortal\SubscriptionController as ParentSubscriptionController;
use App\Http\Controllers\Student\DashboardController as StudentDashboard;
use App\Http\Controllers\Student\AiTutorController;
use App\Http\Controllers\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Student\ClassController as StudentClassController;
use App\Http\Controllers\Student\ExamController as StudentExamController;
use App\Http\Controllers\Student\LearnController;
use App\Http\Controllers\Student\LearningPathController;
use App\Http\Controllers\Student\LessonController as StudentLessonController;
use App\Http\Controllers\Student\ParentConnectionController;
use App\Http\Controllers\Student\PlacementController;
use App\Http\Controllers\Student\PracticeController;
use App\Http\Controllers\Student\SettingsController as StudentSettingsController;
use App\Http\Controllers\Student\SubscriptionController as StudentSubscriptionController;
use App\Http\Controllers\Teacher\AiContentController;
use App\Http\Controllers\Teacher\AssignmentController as TeacherAssignmentController;
use App\Http\Controllers\Teacher\ClassController as TeacherClassController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboard;
use App\Http\Controllers\Teacher\ExamController as TeacherExamController;
use App\Http\Controllers\Teacher\ExamGradingController;
use App\Http\Controllers\Teacher\LessonController as TeacherLessonController;
use App\Http\Controllers\Teacher\LessonSectionController;
use App\Http\Controllers\Teacher\QuestionController;
use App\Http\Controllers\Teacher\QuestionImportController;
use App\Http\Controllers\Teacher\ReportController as TeacherReportController;
use App\Http\Controllers\Teacher\SearchController as TeacherSearchController;
use App\Http\Controllers\Teacher\SettingsController as TeacherSettingsController;
use App\Http\Controllers\Teacher\StudentController as TeacherStudentController;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\LandingController;
use App\Http\Controllers\Web\CookieConsentController;
use App\Http\Controllers\Web\GuideController;
use App\Http\Controllers\Web\PackageController;
use App\Http\Controllers\Web\PaymentController;
use App\Http\Controllers\Web\PushSubscriptionController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\SupportController;
use App\Http\Controllers\Web\VoucherController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::get('/', [LandingController::class, 'index'])->name('home');
// Bảng giá công khai (§18) — đã đăng nhập vẫn dùng chung trang này.
Route::get('goi-hoc', [PackageController::class, 'index'])->name('packages.index');

// Trang pháp lý — tĩnh, ai cũng xem được (footer và trang đăng ký trỏ tới).
Route::view('dieu-khoan-su-dung', 'public.legal.terms')->name('legal.terms');
Route::view('chinh-sach-bao-mat', 'public.legal.privacy')->name('legal.privacy');

// Hỗ trợ / báo lỗi nội dung: khách chưa đăng nhập cũng gửi được (có captcha + throttle trong request).
// Trung tâm hướng dẫn — nội dung tĩnh trong config/guides.php, ai cũng xem được.
Route::get('huong-dan', [GuideController::class, 'index'])->name('guides.index');
Route::get('huong-dan/{slug}', [GuideController::class, 'show'])->name('guides.show');

// Lựa chọn cookie — ai cũng dùng được, kể cả khách chưa đăng nhập.
Route::post('cookie', [CookieConsentController::class, 'store'])->name('cookie.store');
Route::delete('cookie', [CookieConsentController::class, 'destroy'])->name('cookie.destroy');

// Sơ đồ trang cho công cụ tìm kiếm (robots.txt trỏ tới đây).
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('ho-tro', [SupportController::class, 'create'])->name('support.create');
Route::post('ho-tro', [SupportController::class, 'store'])->middleware('throttle:20,60')->name('support.store');

/*
|--------------------------------------------------------------------------
| Khách (chưa đăng nhập)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('dang-nhap', [LoginController::class, 'create'])->name('login');
    Route::post('dang-nhap', [LoginController::class, 'store']);

    // Quên mật khẩu (§29): throttle nằm trong ForgotPasswordRequest + broker chặn gửi lại trong 60 giây.
    Route::get('quen-mat-khau', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('quen-mat-khau', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('dat-lai-mat-khau/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('dat-lai-mat-khau', [PasswordResetController::class, 'update'])->name('password.update');

    Route::get('dang-ky', [RegisterController::class, 'choose'])->name('register');
    Route::get('dang-ky/hoc-sinh', [RegisterController::class, 'createStudent'])->name('register.student');
    Route::post('dang-ky/hoc-sinh', [RegisterController::class, 'storeStudent']);
    Route::get('dang-ky/giao-vien', [RegisterController::class, 'createTeacher'])->name('register.teacher');
    Route::post('dang-ky/giao-vien', [RegisterController::class, 'storeTeacher']);
    Route::get('dang-ky/phu-huynh', [RegisterController::class, 'createParent'])->name('register.parent');
    Route::post('dang-ky/phu-huynh', [RegisterController::class, 'storeParent']);
});

/*
|--------------------------------------------------------------------------
| Đã đăng nhập
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('dang-xuat', [LoginController::class, 'destroy'])->name('logout');

    // Xác thực email (§29). Link trong mail đã ký nên không cần đăng nhập mới mở được.
    Route::get('xac-thuc-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('xac-thuc-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->withoutMiddleware('auth')
        ->name('verification.verify');
    Route::post('xac-thuc-email/gui-lai', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Đổi email (§10, đợt 23/09). Hai link trong thư đều đã ký nên không cần đăng nhập:
    // người bị chiếm tài khoản có thể đã không vào được nữa.
    Route::post('tai-khoan/doi-email', [EmailChangeController::class, 'store'])
        ->middleware('throttle:6,60')
        ->name('email-change.store');
    Route::delete('tai-khoan/doi-email', [EmailChangeController::class, 'destroy'])->name('email-change.destroy');
    Route::get('doi-email/{id}/{hash}', [EmailChangeController::class, 'confirm'])
        ->middleware('signed')
        ->withoutMiddleware('auth')
        ->name('email-change.confirm');
    Route::get('huy-doi-email/{id}/{hash}', [EmailChangeController::class, 'cancel'])
        ->middleware('signed')
        ->withoutMiddleware('auth')
        ->name('email-change.cancel');

    // Xoá tài khoản: dùng chung cho cả 4 portal, hỏi lại mật khẩu trong controller.
    Route::delete('tai-khoan/xoa', [AccountDeletionController::class, 'destroy'])->name('account.destroy');

    // Không qua middleware `active` — đây chính là trang dành cho tài khoản pending.
    Route::get('tai-khoan/cho-duyet', [AccountController::class, 'pending'])->name('account.pending');

    Route::middleware('active')->group(function () {
        Route::get('thong-bao', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('thong-bao/{notification}/mo', [NotificationController::class, 'open'])->name('notifications.open');
        Route::post('thong-bao/danh-dau-da-doc', [NotificationController::class, 'readAll'])->name('notifications.read_all');
        Route::put('thong-bao/cai-dat', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');

        // Đăng ký nhận thông báo đẩy của từng trình duyệt (gọi bằng fetch từ trang Cài đặt).
        Route::post('thong-bao/day', [PushSubscriptionController::class, 'store'])->name('push.store');
        Route::delete('thong-bao/day', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');

        // Học sinh mua cho mình, phụ huynh mua cho con — controller tự kiểm tra role.
        Route::get('goi-hoc/{package}/mua', [PackageController::class, 'checkout'])
            ->middleware('verified')
            ->name('packages.checkout');
        Route::post('goi-hoc/{package}/mua', [PaymentController::class, 'store'])
            ->middleware(['verified', 'throttle:10,1']) // mỗi lần bấm là một lượt gọi MoMo
            ->name('packages.pay');

        // Mã giảm giá (§8b). Throttle chặt hơn bình thường vì đây là chỗ duy nhất dò được mã.
        Route::post('goi-hoc/{package}/ma-giam-gia', [VoucherController::class, 'apply'])
            ->middleware(['verified', 'throttle:10,1'])
            ->name('packages.voucher.apply');
        Route::delete('goi-hoc/{package}/ma-giam-gia', [VoucherController::class, 'remove'])
            ->middleware('verified')
            ->name('packages.voucher.remove');

        // §8: return URL chỉ hiển thị trạng thái đọc từ DB.
        Route::get('payment/momo/return', [PaymentController::class, 'handleReturn'])->name('payment.return');
        Route::get('thanh-toan', [PaymentController::class, 'history'])->name('payment.history');
        Route::get('thanh-toan/{payment}', [PaymentController::class, 'show'])->name('payment.show');
        Route::get('thanh-toan/{payment}/trang-thai', [PaymentController::class, 'status'])
            ->middleware('throttle:60,1')->name('payment.status');
        // Giả lập MoMo — controller trả 404 nếu không phải PAYMENT_GATEWAY=fake hoặc đang production.
        Route::get('thanh-toan/{payment}/gia-lap', [PaymentController::class, 'simulator'])->name('payment.simulator');
        Route::post('thanh-toan/{payment}/gia-lap', [PaymentController::class, 'simulate'])->name('payment.simulate');

        Route::prefix('hoc-sinh')->name('student.')->middleware('role:student')->group(function () {
            Route::get('/', [StudentDashboard::class, 'index'])->name('dashboard');

            Route::get('cai-dat', [StudentSettingsController::class, 'edit'])->name('settings');
            Route::put('cai-dat/ho-so', [StudentSettingsController::class, 'updateProfile'])->name('settings.profile');
            Route::put('cai-dat/mat-khau', [StudentSettingsController::class, 'updatePassword'])->name('settings.password');

            Route::get('hoc', [LearnController::class, 'index'])->name('learn.index');
            Route::get('hoc/chu-de/{topic}', [LearnController::class, 'topic'])->name('learn.topic');

            Route::get('luyen-tap', [PracticeController::class, 'index'])->name('practice.index');
            Route::post('luyen-tap/bat-dau', [PracticeController::class, 'start'])->name('practice.start');
            Route::get('luyen-tap/lam-bai', [PracticeController::class, 'show'])->name('practice.show');
            Route::post('luyen-tap/nop', [PracticeController::class, 'submit'])->name('practice.submit');
            Route::get('luyen-tap/ket-qua', [PracticeController::class, 'result'])->name('practice.result');

            // §34 Kiểm tra đầu vào · §35 Lộ trình · §37 Kiểm tra cuối buổi.
            Route::get('kiem-tra-dau-vao', [PlacementController::class, 'intro'])->name('placement.intro');
            Route::post('kiem-tra-dau-vao', [PlacementController::class, 'start'])->name('placement.start');
            Route::get('kiem-tra-dau-vao/{test}/lam-bai', [PlacementController::class, 'take'])->name('placement.take');
            Route::post('kiem-tra-dau-vao/{test}/nop', [PlacementController::class, 'submit'])->name('placement.submit');
            Route::get('kiem-tra-dau-vao/{test}/ket-qua', [PlacementController::class, 'result'])->name('placement.result');

            Route::get('lo-trinh', [LearningPathController::class, 'show'])->name('path.show');
            Route::get('lo-trinh/buoi/{session}/kiem-tra', [LearningPathController::class, 'quiz'])->name('path.quiz');
            Route::post('lo-trinh/buoi/{session}/kiem-tra', [LearningPathController::class, 'submitQuiz'])->name('path.quiz.submit');

            Route::get('ai', [AiTutorController::class, 'index'])->name('ai.index');

            Route::get('goi-cua-toi', [StudentSubscriptionController::class, 'index'])->name('subscription.index');

            Route::get('phu-huynh', [ParentConnectionController::class, 'index'])->name('parents.index');
            Route::post('phu-huynh/doi-ma', [ParentConnectionController::class, 'regenerate'])->name('parents.regenerate');
            Route::delete('phu-huynh/{parent}', [ParentConnectionController::class, 'revoke'])->name('parents.revoke');

            Route::get('lop-cua-toi', [StudentClassController::class, 'index'])->name('classes.index');
            Route::post('lop-cua-toi/tham-gia', [StudentClassController::class, 'join'])
                ->middleware('throttle:10,1') // chặn dò mã lớp
                ->name('classes.join');

            Route::get('bai-duoc-giao', [StudentAssignmentController::class, 'index'])->name('assignments.index');
            Route::get('bai-duoc-giao/{assignment}', [StudentAssignmentController::class, 'show'])->name('assignments.show');
            Route::post('bai-duoc-giao/{assignment}/nop', [StudentAssignmentController::class, 'submit'])
                ->name('assignments.submit');
            Route::get('bai-duoc-giao/{assignment}/ket-qua/{submission}', [StudentAssignmentController::class, 'result'])
                ->name('assignments.result');

            Route::get('de-kiem-tra', [StudentExamController::class, 'index'])->name('exams.index');
            Route::get('de-kiem-tra/{exam}', [StudentExamController::class, 'show'])->name('exams.show');
            Route::post('de-kiem-tra/{exam}/bat-dau', [StudentExamController::class, 'start'])->name('exams.start');
            Route::get('lam-bai/{attempt}', [StudentExamController::class, 'take'])->name('exams.take');
            // Autosave gọi liên tục khi làm bài — nới rate limit nhưng vẫn chặn spam.
            Route::post('lam-bai/{attempt}/tra-loi', [StudentExamController::class, 'saveAnswer'])
                ->middleware('throttle:120,1')
                ->name('exams.answer');
            Route::post('lam-bai/{attempt}/nop', [StudentExamController::class, 'submit'])->name('exams.submit');
            Route::get('lam-bai/{attempt}/ket-qua', [StudentExamController::class, 'result'])->name('exams.result');

            Route::get('bai-hoc/{lesson}', [StudentLessonController::class, 'show'])->name('lesson.show');
            Route::post('bai-hoc/{lesson}/tien-do', [StudentLessonController::class, 'trackProgress'])
                ->name('lesson.progress');
            Route::post('bai-hoc/{lesson}/hoan-thanh', [StudentLessonController::class, 'complete'])
                ->name('lesson.complete');
        });

        Route::prefix('giao-vien')->name('teacher.')->middleware('role:teacher,admin')->group(function () {
            Route::get('/', [TeacherDashboard::class, 'index'])->name('dashboard');

            Route::get('tim-kiem', [TeacherSearchController::class, 'index'])->name('search');

            Route::get('cai-dat', [TeacherSettingsController::class, 'edit'])->name('settings');
            Route::put('cai-dat/ho-so', [TeacherSettingsController::class, 'updateProfile'])->name('settings.profile');
            Route::put('cai-dat/mat-khau', [TeacherSettingsController::class, 'updatePassword'])->name('settings.password');

            // AI soạn nội dung (§12) — mọi output là nháp, giáo viên duyệt từng mục.
            Route::get('ai', [AiContentController::class, 'index'])->name('ai.index');
            Route::post('ai/cau-hoi', [AiContentController::class, 'storeQuestions'])
                ->middleware('throttle:ai')->name('ai.questions');
            Route::post('ai/bai-hoc', [AiContentController::class, 'storeLesson'])
                ->middleware('throttle:ai')->name('ai.lesson');
            Route::post('ai/viet-lai', [AiContentController::class, 'rewrite'])
                ->middleware('throttle:ai')->name('ai.rewrite');
            Route::get('ai/nhap/{draft}', [AiContentController::class, 'show'])->name('ai.show');
            Route::post('ai/nhap/{draft}/muc/{index}/chap-nhan', [AiContentController::class, 'accept'])
                ->whereNumber('index')->name('ai.accept');
            Route::post('ai/nhap/{draft}/muc/{index}/bo', [AiContentController::class, 'reject'])
                ->whereNumber('index')->name('ai.reject');
            Route::post('ai/nhap/{draft}/muc/{index}/tao-lai', [AiContentController::class, 'regenerate'])
                ->whereNumber('index')->middleware('throttle:ai')->name('ai.regenerate');
            Route::get('ai/nhap/{draft}/muc/{index}/sua', [AiContentController::class, 'edit'])
                ->whereNumber('index')->name('ai.edit');
            Route::post('ai/nhap/{draft}/tao-bai-hoc', [AiContentController::class, 'createLesson'])->name('ai.create-lesson');

            Route::get('lop-hoc', [TeacherClassController::class, 'index'])->name('classes.index');
            Route::get('lop-hoc/tao-moi', [TeacherClassController::class, 'create'])->name('classes.create');
            Route::post('lop-hoc', [TeacherClassController::class, 'store'])->name('classes.store');
            Route::get('lop-hoc/{class}', [TeacherClassController::class, 'show'])->name('classes.show');
            Route::put('lop-hoc/{class}', [TeacherClassController::class, 'update'])->name('classes.update');
            Route::post('lop-hoc/{class}/luu-tru', [TeacherClassController::class, 'toggleArchive'])->name('classes.archive');
            Route::post('lop-hoc/{class}/doi-ma', [TeacherClassController::class, 'regenerateCode'])->name('classes.code');
            Route::post('lop-hoc/{class}/hoc-sinh', [TeacherClassController::class, 'addStudent'])->name('classes.students.add');
            Route::delete('lop-hoc/{class}/hoc-sinh/{student}', [TeacherClassController::class, 'removeStudent'])
                ->name('classes.students.remove');
            Route::post('lop-hoc/{class}/giao-vien-phu', [TeacherClassController::class, 'addAssistant'])
                ->name('classes.assistants.add');

            Route::get('giao-bai', [TeacherAssignmentController::class, 'index'])->name('assignments.index');
            Route::get('giao-bai/tao-moi', [TeacherAssignmentController::class, 'create'])->name('assignments.create');
            Route::post('giao-bai', [TeacherAssignmentController::class, 'store'])->name('assignments.store');
            Route::get('giao-bai/{assignment}', [TeacherAssignmentController::class, 'show'])->name('assignments.show');
            Route::put('giao-bai/{assignment}', [TeacherAssignmentController::class, 'update'])->name('assignments.update');
            Route::post('giao-bai/{assignment}/dong', [TeacherAssignmentController::class, 'toggleClosed'])
                ->name('assignments.close');
            Route::delete('giao-bai/{assignment}', [TeacherAssignmentController::class, 'destroy'])
                ->name('assignments.destroy');

            Route::get('bao-cao', [TeacherReportController::class, 'index'])->name('reports.index');
            Route::get('bao-cao/lop/{class}/csv', [TeacherReportController::class, 'export'])->name('reports.export');

            Route::get('hoc-sinh', [TeacherStudentController::class, 'index'])->name('students.index');
            Route::get('hoc-sinh/{student}', [TeacherStudentController::class, 'show'])->name('students.show');
            Route::post('hoc-sinh/{student}/nhan-xet', [TeacherStudentController::class, 'storeComment'])
                ->name('students.comments.store');
            Route::delete('nhan-xet/{comment}', [TeacherStudentController::class, 'destroyComment'])
                ->name('students.comments.destroy');

            Route::get('bai-hoc', [TeacherLessonController::class, 'index'])->name('lessons.index');
            Route::get('bai-hoc/tao-moi', [TeacherLessonController::class, 'create'])->name('lessons.create');
            Route::post('bai-hoc', [TeacherLessonController::class, 'store'])->name('lessons.store');
            Route::get('bai-hoc/{lesson}/sua', [TeacherLessonController::class, 'edit'])->name('lessons.edit');
            Route::put('bai-hoc/{lesson}', [TeacherLessonController::class, 'update'])->name('lessons.update');
            Route::post('bai-hoc/{lesson}/xuat-ban', [TeacherLessonController::class, 'togglePublish'])
                ->name('lessons.publish');
            Route::delete('bai-hoc/{lesson}', [TeacherLessonController::class, 'destroy'])->name('lessons.destroy');

            Route::get('cau-hoi', [QuestionController::class, 'index'])->name('questions.index');
            Route::get('cau-hoi/tao-moi', [QuestionController::class, 'create'])->name('questions.create');
            Route::post('cau-hoi', [QuestionController::class, 'store'])->name('questions.store');
            Route::get('cau-hoi/{question}/sua', [QuestionController::class, 'edit'])->name('questions.edit');
            Route::put('cau-hoi/{question}', [QuestionController::class, 'update'])->name('questions.update');
            Route::delete('cau-hoi/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');

            Route::get('cau-hoi-nhap', [QuestionImportController::class, 'create'])->name('questions.import');
            Route::post('cau-hoi-nhap', [QuestionImportController::class, 'store'])->name('questions.import.store');
            Route::get('cau-hoi-nhap/mau', [QuestionImportController::class, 'template'])
                ->name('questions.import.template');

            Route::get('de-kiem-tra', [TeacherExamController::class, 'index'])->name('exams.index');
            Route::get('de-kiem-tra/tao-moi', [TeacherExamController::class, 'create'])->name('exams.create');
            Route::post('de-kiem-tra', [TeacherExamController::class, 'store'])->name('exams.store');
            Route::get('de-kiem-tra/{exam}/sua', [TeacherExamController::class, 'edit'])->name('exams.edit');
            Route::put('de-kiem-tra/{exam}', [TeacherExamController::class, 'update'])->name('exams.update');
            Route::post('de-kiem-tra/{exam}/xuat-ban', [TeacherExamController::class, 'togglePublish'])
                ->name('exams.publish');
            Route::delete('de-kiem-tra/{exam}', [TeacherExamController::class, 'destroy'])->name('exams.destroy');
            Route::post('de-kiem-tra/{exam}/cau-hoi', [TeacherExamController::class, 'addQuestions'])
                ->name('exams.questions.add');
            Route::post('de-kiem-tra/{exam}/cau-hoi-ngau-nhien', [TeacherExamController::class, 'addRandom'])
                ->name('exams.questions.random');
            Route::put('de-kiem-tra/{exam}/cau-hoi/{question}', [TeacherExamController::class, 'updateQuestion'])
                ->name('exams.questions.update');
            Route::delete('de-kiem-tra/{exam}/cau-hoi/{question}', [TeacherExamController::class, 'removeQuestion'])
                ->name('exams.questions.remove');

            Route::get('de-kiem-tra/{exam}/bai-lam', [ExamGradingController::class, 'index'])->name('exams.attempts');
            Route::get('bai-lam/{attempt}/cham', [ExamGradingController::class, 'show'])->name('exams.grade');
            Route::post('cau-tra-loi/{answer}/cham', [ExamGradingController::class, 'grade'])
                ->name('exams.grade.answer');

            Route::post('bai-hoc/{lesson}/phan', [LessonSectionController::class, 'store'])->name('sections.store');
            Route::put('bai-hoc/{lesson}/phan/{section}', [LessonSectionController::class, 'update'])
                ->name('sections.update');
            Route::delete('bai-hoc/{lesson}/phan/{section}', [LessonSectionController::class, 'destroy'])
                ->name('sections.destroy');
        });

        Route::prefix('phu-huynh')->name('parent.')->middleware('role:parent')->group(function () {
            Route::get('/', [ParentDashboard::class, 'index'])->name('dashboard');

            Route::get('lien-ket', [ParentChildController::class, 'linkForm'])->name('children.link');
            // Mã liên kết mở quyền xem điểm của một đứa trẻ — chặn dò mã.
            Route::post('lien-ket', [ParentChildController::class, 'link'])
                ->middleware('throttle:5,1')
                ->name('children.link.store');
            Route::get('lien-ket/xac-nhan', [ParentChildController::class, 'accept'])
                ->middleware('signed')
                ->name('children.accept');

            Route::get('con/{student}', [ParentChildController::class, 'show'])->name('children.show');
            Route::delete('con/{student}', [ParentChildController::class, 'unlink'])->name('children.unlink');

            Route::get('goi-hoc', [ParentSubscriptionController::class, 'index'])->name('subscriptions.index');

            Route::get('cai-dat', [ParentSettingsController::class, 'edit'])->name('settings');
            Route::put('cai-dat', [ParentSettingsController::class, 'update'])->name('settings.update');
            Route::put('cai-dat/ho-so', [ParentSettingsController::class, 'updateProfile'])->name('settings.profile');
            Route::put('cai-dat/mat-khau', [ParentSettingsController::class, 'updatePassword'])->name('settings.password');
        });

        Route::prefix('quan-tri')->name('admin.')->middleware('role:admin')->group(function () {
            Route::get('/', [AdminDashboard::class, 'index'])->name('dashboard');
            Route::post('lam-moi-so-lieu', [AdminDashboard::class, 'refresh'])->name('dashboard.refresh');

            // Bảo trì: xem bootstrap/app.php — route này cố ý vẫn vào được khi đang bảo trì,
            // nếu không admin mất cookie bỏ qua là hết đường tắt mà không SSH vào máy chủ.
            Route::get('bao-tri', [MaintenanceController::class, 'edit'])->name('maintenance.edit');
            Route::post('bao-tri', [MaintenanceController::class, 'store'])->name('maintenance.store');
            Route::delete('bao-tri', [MaintenanceController::class, 'destroy'])->name('maintenance.destroy');

            Route::get('cai-dat', [AdminSettingsController::class, 'edit'])->name('settings');
            Route::put('cai-dat/ho-so', [AdminSettingsController::class, 'updateProfile'])->name('settings.profile');
            Route::put('cai-dat/mat-khau', [AdminSettingsController::class, 'updatePassword'])->name('settings.password');

            Route::get('nguoi-dung', [AdminUserController::class, 'index'])->name('users.index');
            Route::get('nguoi-dung/{user}', [AdminUserController::class, 'show'])->name('users.show');
            Route::post('nguoi-dung/{user}/doi-email', [AdminUserController::class, 'changeEmail'])->name('users.email');
            Route::post('nguoi-dung/{user}/khoa', [AdminUserController::class, 'suspend'])->name('users.suspend');
            Route::post('nguoi-dung/{user}/mo-khoa', [AdminUserController::class, 'reactivate'])->name('users.reactivate');
            Route::post('nguoi-dung/{user}/khoi-phuc', [AdminUserController::class, 'restore'])
                ->withTrashed()
                ->name('users.restore');

            Route::get('ho-tro', [SupportTicketController::class, 'index'])->name('support.index');
            Route::get('ho-tro/{ticket}', [SupportTicketController::class, 'show'])->name('support.show');
            Route::put('ho-tro/{ticket}', [SupportTicketController::class, 'update'])->name('support.update');

            Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('audit-log/xuat-csv', [AuditLogController::class, 'export'])->name('audit-logs.export');

            Route::get('giao-vien/cho-duyet', [TeacherApprovalController::class, 'index'])
                ->name('teachers.pending');
            Route::post('giao-vien/{user}/duyet', [TeacherApprovalController::class, 'approve'])
                ->name('teachers.approve');
            Route::post('giao-vien/{user}/tu-choi', [TeacherApprovalController::class, 'reject'])
                ->name('teachers.reject');

            Route::get('ai-usage', [AiUsageController::class, 'index'])->name('ai-usage.index');

            Route::resource('goi-hoc', AdminPackageController::class)
                ->except('show')
                ->parameters(['goi-hoc' => 'package'])
                ->names('packages');
            Route::resource('ma-giam-gia', AdminVoucherController::class)
                ->parameters(['ma-giam-gia' => 'voucher'])
                ->names('vouchers');
            Route::get('dang-ky-goi', [AdminSubscriptionController::class, 'index'])->name('subscriptions.index');
            Route::post('dang-ky-goi/cap', [AdminSubscriptionController::class, 'grant'])->name('subscriptions.grant');
            Route::get('giao-dich', [AdminPaymentController::class, 'index'])->name('payments.index');
            Route::get('giao-dich/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
            Route::post('giao-dich/{payment}/doi-soat', [AdminPaymentController::class, 'reconcile'])->name('payments.reconcile');
            Route::post('dang-ky-goi/{subscription}/huy', [AdminSubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

            Route::get('chuong-trinh', [CurriculumController::class, 'index'])->name('curriculum.index');
            Route::post('chuong-trinh/lop/{grade}/mon', [CurriculumController::class, 'storeSubject'])
                ->name('curriculum.subjects.store');
            Route::post('chuong-trinh/mon/{subject}/chuong', [CurriculumController::class, 'storeChapter'])
                ->name('curriculum.chapters.store');
            Route::post('chuong-trinh/chuong/{chapter}/chu-de', [CurriculumController::class, 'storeTopic'])
                ->name('curriculum.topics.store');
            Route::delete('chuong-trinh/chu-de/{topic}', [CurriculumController::class, 'destroyTopic'])
                ->name('curriculum.topics.destroy');
        });
    });
});
