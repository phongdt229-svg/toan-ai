<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login', ['demoAccounts' => $this->demoAccounts()]);
    }

    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        /** @var User $user */
        $user = $request->user();

        // Có 2FA: mật khẩu đúng chưa đủ — đăng xuất ngay, chỉ nhớ id trong session tới khi nhập mã.
        if ($this->twoFactor->isEnabled($user)) {
            Auth::logout();
            $request->session()->regenerate();
            $request->session()->put('two_factor', [
                'id' => $user->id,
                'remember' => $request->boolean('remember'),
                'expires' => now()->addMinutes(10)->timestamp,
            ]);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        if ($user->isPending()) {
            return redirect()->route('account.pending');
        }

        return redirect()->intended($user->homeRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * Chỉ ở APP_ENV=local: tài khoản demo đã seed, để bấm là điền sẵn email/mật khẩu.
     * Lọc theo DB để không hiện tài khoản chưa tồn tại (chưa chạy seeder).
     *
     * @return list<array{email: string, role: string, name: string, status: string}>
     */
    private function demoAccounts(): array
    {
        if (! app()->isLocal()) {
            return [];
        }

        $users = User::whereIn('email', array_keys(DemoUserSeeder::ACCOUNTS))->get(['name', 'email', 'status'])->keyBy('email');

        return collect(DemoUserSeeder::ACCOUNTS)
            ->filter(fn ($role, $email) => $users->has($email))
            ->map(fn ($role, $email) => [
                'email' => $email,
                'role' => $role,
                'name' => $users[$email]->name,
                'status' => $users[$email]->status,
            ])
            ->values()
            ->all();
    }
}
