<?php

use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\TeacherApprovalController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ParentPortal\DashboardController as ParentDashboard;
use App\Http\Controllers\Student\DashboardController as StudentDashboard;
use App\Http\Controllers\Student\LearnController;
use App\Http\Controllers\Student\LessonController as StudentLessonController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboard;
use App\Http\Controllers\Teacher\LessonController as TeacherLessonController;
use App\Http\Controllers\Teacher\LessonSectionController;
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

            Route::get('bai-hoc/{lesson}', [StudentLessonController::class, 'show'])->name('lesson.show');
            Route::post('bai-hoc/{lesson}/tien-do', [StudentLessonController::class, 'trackProgress'])
                ->name('lesson.progress');
            Route::post('bai-hoc/{lesson}/hoan-thanh', [StudentLessonController::class, 'complete'])
                ->name('lesson.complete');
        });

        Route::prefix('giao-vien')->name('teacher.')->middleware('role:teacher,admin')->group(function () {
            Route::get('/', [TeacherDashboard::class, 'index'])->name('dashboard');

            Route::get('bai-hoc', [TeacherLessonController::class, 'index'])->name('lessons.index');
            Route::get('bai-hoc/tao-moi', [TeacherLessonController::class, 'create'])->name('lessons.create');
            Route::post('bai-hoc', [TeacherLessonController::class, 'store'])->name('lessons.store');
            Route::get('bai-hoc/{lesson}/sua', [TeacherLessonController::class, 'edit'])->name('lessons.edit');
            Route::put('bai-hoc/{lesson}', [TeacherLessonController::class, 'update'])->name('lessons.update');
            Route::post('bai-hoc/{lesson}/xuat-ban', [TeacherLessonController::class, 'togglePublish'])
                ->name('lessons.publish');
            Route::delete('bai-hoc/{lesson}', [TeacherLessonController::class, 'destroy'])->name('lessons.destroy');

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
