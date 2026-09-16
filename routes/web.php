<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\TeacherApprovalController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ParentPortal\DashboardController as ParentDashboard;
use App\Http\Controllers\Student\DashboardController as StudentDashboard;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboard;
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
        });

        Route::prefix('giao-vien')->name('teacher.')->middleware('role:teacher')->group(function () {
            Route::get('/', [TeacherDashboard::class, 'index'])->name('dashboard');
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
        });
    });
});
