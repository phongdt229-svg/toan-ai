<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Danh sách đăng ký, cấp gói tay và huỷ gói (có audit log). */
class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());

        return view('admin.subscriptions.index', [
            'subscriptions' => Subscription::query()
                ->with('package', 'user', 'purchaser')
                ->when($status === 'effective', fn ($q) => $q->effective())
                ->when(array_key_exists($status, Subscription::STATUS_LABELS), fn ($q) => $q->where('status', $status))
                ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where(fn ($w) => $w
                    ->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'packages' => Package::where('price', '>', 0)->ordered()->get(),
            'status' => $status,
            'search' => $search,
            'stats' => [
                'effective' => Subscription::effective()->count(),
                'pending' => Subscription::where('status', Subscription::STATUS_PENDING)->count(),
                'expiring' => Subscription::effective()->where('ends_at', '<=', now()->addDays(7))->count(),
            ],
        ]);
    }

    public function grant(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'package_id' => ['required', Rule::exists('packages', 'id')],
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ], [], ['email' => 'email học sinh', 'package_id' => 'gói', 'days' => 'số ngày']);

        $student = User::where('email', $data['email'])->first();

        if (! $student?->isStudent()) {
            return back()->withInput()->withErrors(['email' => 'Không tìm thấy tài khoản học sinh với email này.']);
        }

        try {
            $sub = $this->subscriptions->grant($request->user(), $student, Package::findOrFail($data['package_id']), (int) $data['days']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', "Đã cấp {$sub->package->name} cho {$student->name} đến {$sub->ends_at->format('d/m/Y')}.");
    }

    public function cancel(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:191']], [], ['reason' => 'lý do']);

        if (in_array($subscription->status, [Subscription::STATUS_CANCELLED, Subscription::STATUS_EXPIRED], true)) {
            return back()->with('error', 'Đăng ký này đã kết thúc.');
        }

        $this->subscriptions->cancel($subscription, $request->user(), $data['reason']);

        return back()->with('status', 'Đã huỷ đăng ký.');
    }
}
