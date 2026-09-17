<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
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

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();
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
