<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Bước 2 của đăng nhập: nhập mã TOTP hoặc mã dự phòng. */
class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function create(Request $request): View|RedirectResponse
    {
        return $this->pending($request) ? view('auth.two-factor') : redirect()->route('login');
    }

    public function store(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);

        if (! $pending) {
            return redirect()->route('login')->withErrors(['email' => 'Phiên xác thực đã hết hạn, hãy đăng nhập lại.']);
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);

        $key = '2fa|'.$pending['id'].'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Nhập sai quá nhiều lần. Thử lại sau '.RateLimiter::availableIn($key).' giây.',
            ]);
        }

        $user = User::find($pending['id']);

        if (! $user || ! $this->twoFactor->verifyChallenge($user, $data['code'])) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['code' => 'Mã không đúng hoặc đã dùng rồi.']);
        }

        RateLimiter::clear($key);
        $request->session()->forget('two_factor');

        Auth::login($user, $pending['remember']);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return $user->isPending() ? redirect()->route('account.pending') : redirect()->intended($user->homeRoute());
    }

    /** @return array{id: int, remember: bool, expires: int}|null */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get('two_factor');

        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->timestamp) {
            $request->session()->forget('two_factor');

            return null;
        }

        return $pending;
    }
}
