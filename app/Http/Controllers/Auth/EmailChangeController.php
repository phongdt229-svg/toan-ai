<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEmailRequest;
use App\Models\User;
use App\Services\Auth\EmailChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Đổi email tài khoản — xem EmailChangeService để biết vì sao phải qua hai bước. */
class EmailChangeController extends Controller
{
    public function __construct(private readonly EmailChangeService $emails) {}

    public function store(UpdateEmailRequest $request): RedirectResponse
    {
        try {
            $this->emails->request($request->user(), $request->validated()['email']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        return back()->with('status', 'Đã gửi thư xác nhận tới địa chỉ mới. Mở hộp thư đó và bấm link để hoàn tất.');
    }

    /** Link trong thư gửi tới địa chỉ mới. Đã ký nên không cần đăng nhập. */
    public function confirm(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        try {
            $this->emails->confirm($user, $hash);
        } catch (RuntimeException $e) {
            return $this->done($request, $user)->with('error', $e->getMessage());
        }

        return $this->done($request, $user)->with('status', "Đã đổi email tài khoản sang {$user->email}.");
    }

    /** Link "Không phải tôi" trong thư gửi về địa chỉ cũ. */
    public function cancel(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        try {
            $this->emails->cancel($user, $hash);
        } catch (RuntimeException $e) {
            return $this->done($request, $user)->with('error', $e->getMessage());
        }

        return $this->done($request, $user)
            ->with('status', 'Đã huỷ yêu cầu đổi email. Nếu không phải bạn yêu cầu, hãy đổi mật khẩu ngay.');
    }

    /** Người dùng tự bỏ yêu cầu đang chờ trong trang Cài đặt. */
    public function destroy(Request $request): RedirectResponse
    {
        $this->emails->cancel($request->user());

        return back()->with('status', 'Đã bỏ yêu cầu đổi email.');
    }

    private function done(Request $request, User $user): RedirectResponse
    {
        // Đang đăng nhập đúng tài khoản đó thì về trang cài đặt của họ, không thì về trang đăng nhập.
        return $request->user()?->is($user)
            ? redirect()->route($user->settingsRoute())
            : redirect()->route('login');
    }
}
