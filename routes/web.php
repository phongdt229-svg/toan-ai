<?php

use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\TeacherApprovalController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ParentPortal\DashboardController as ParentDashboard;
use App\Http\Controllers\Student\DashboardController as StudentDashboard;
use App\Http\Controllers\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Student\ClassController as StudentClassController;
use App\Http\Controllers\Student\ExamController as StudentExamController;
use App\Http\Controllers\Student\LearnController;
use App\Http\Controllers\Student\LessonController as StudentLessonController;
use App\Http\Controllers\Student\PracticeController;
use App\Http\Controllers\Teacher\AssignmentController as TeacherAssignmentController;
use App\Http\Controllers\Teacher\ClassController as TeacherClassController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboard;
use App\Http\Controllers\Teacher\ExamController as TeacherExamController;
use App\Http\Controllers\Teacher\ExamGradingController;
use App\Http\Controllers\Teacher\LessonController as TeacherLessonController;
use App\Http\Controllers\Teacher\LessonSectionController;
use App\Http\Controllers\Teacher\QuestionController;
use App\Http\Controllers\Teacher\QuestionImportController;
use App\Http\Controllers\Teacher\StudentController as TeacherStudentController;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\LandingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::get('/', [LandingController::class, 'index'])->name('home');

/*
|--------------------------------------------------------------------------
| Khách (chưa đăng nhập)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('dang-nhap', [LoginController::class, 'create'])->name('login');
    Route::post('dang-nhap', [LoginController::class, 'store']);

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

    // Không qua middleware `active` — đây chính là trang dành cho tài khoản pending.
    Route::get('tai-khoan/cho-duyet', [AccountController::class, 'pending'])->name('account.pending');

    Route::middleware('active')->group(function () {
        Route::prefix('hoc-sinh')->name('student.')->middleware('role:student')->group(function () {
            Route::get('/', [StudentDashboard::class, 'index'])->name('dashboard');

            Route::get('hoc', [LearnController::class, 'index'])->name('learn.index');
            Route::get('hoc/chu-de/{topic}', [LearnController::class, 'topic'])->name('learn.topic');

            Route::get('luyen-tap', [PracticeController::class, 'index'])->name('practice.index');
            Route::post('luyen-tap/bat-dau', [PracticeController::class, 'start'])->name('practice.start');
            Route::get('luyen-tap/lam-bai', [PracticeController::class, 'show'])->name('practice.show');
            Route::post('luyen-tap/nop', [PracticeController::class, 'submit'])->name('practice.submit');
            Route::get('luyen-tap/ket-qua', [PracticeController::class, 'result'])->name('practice.result');

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
        });

        Route::prefix('quan-tri')->name('admin.')->middleware('role:admin')->group(function () {
            Route::get('/', [AdminDashboard::class, 'index'])->name('dashboard');

            Route::get('giao-vien/cho-duyet', [TeacherApprovalController::class, 'index'])
                ->name('teachers.pending');
            Route::post('giao-vien/{user}/duyet', [TeacherApprovalController::class, 'approve'])
                ->name('teachers.approve');
            Route::post('giao-vien/{user}/tu-choi', [TeacherApprovalController::class, 'reject'])
                ->name('teachers.reject');

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
