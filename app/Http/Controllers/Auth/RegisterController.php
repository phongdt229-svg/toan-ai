<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ParentRegisterRequest;
use App\Http\Requests\Auth\StudentRegisterRequest;
use App\Http\Requests\Auth\TeacherRegisterRequest;
use App\Models\Grade;
use App\Services\Auth\RegistrationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(private readonly RegistrationService $registration) {}

    /** Bước "Bạn là ai?" (§5). */
    public function choose(): View
    {
        return view('auth.register-choose');
    }

    public function createStudent(): View
    {
        return view('auth.register-student', [
            'grades' => Grade::active()->ordered()->get(),
        ]);
    }

    public function storeStudent(StudentRegisterRequest $request): RedirectResponse
    {
        $user = $this->registration->registerStudent($request->validated());

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('student.dashboard')
            ->with('status', 'Chào mừng bạn đến với TOÁN AI!');
    }

    public function createTeacher(): View
    {
        return view('auth.register-teacher');
    }

    public function storeTeacher(TeacherRegisterRequest $request): RedirectResponse
    {
        $user = $this->registration->registerTeacher($request->validated());

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        // Tài khoản ở trạng thái pending → vào trang chờ duyệt, không vào portal.
        return redirect()->route('account.pending');
    }

    public function createParent(): View
    {
        return view('auth.register-parent');
    }

    public function storeParent(ParentRegisterRequest $request): RedirectResponse
    {
        $user = $this->registration->registerParent($request->validated());

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('parent.dashboard')
            ->with('status', 'Tạo tài khoản thành công.');
    }
}
