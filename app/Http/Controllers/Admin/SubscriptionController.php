<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GrantSubscriptionRequest;
use App\Http\Requests\Admin\ReasonRequest;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                ->paginate(config('site.per_page'))
                ->withQueryString(),
            'packages' => Package::where('price', '>', 0)->ordered()->get(),
            'status' => $status,
            'search' => $search,
            'stats' => [
                'effective' => Subscription::effective()->count(),
                'pending' => Subscription::where('status', Subscription::STATUS_PENDING)->count(),
                'expiring' => Subscription::effective()->where('ends_at', '<=', now()->addDays(7))->count(),
            ],
            // Đang hiệu lực, theo từng gói cụ thể — biết gói nào đang được mua nhiều nhất.
            'packageChart' => Subscription::query()
                ->effective()
                ->join('packages', 'packages.id', '=', 'subscriptions.package_id')
                ->groupBy('packages.id', 'packages.name')
                ->orderByDesc('c')
                ->selectRaw('packages.name label, COUNT(*) c')
                ->get()
                ->map(fn ($row) => ['label' => $row->label, 'count' => (int) $row->c]),
        ]);
    }

    public function grant(GrantSubscriptionRequest $request): RedirectResponse
    {
        $data = $request->validated();

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

    public function cancel(ReasonRequest $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validated();

        if (in_array($subscription->status, [Subscription::STATUS_CANCELLED, Subscription::STATUS_EXPIRED], true)) {
            return back()->with('error', 'Đăng ký này đã kết thúc.');
        }

        $this->subscriptions->cancel($subscription, $request->user(), $data['reason']);

        return back()->with('status', 'Đã huỷ đăng ký.');
    }
}
