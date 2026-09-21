<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MaintenanceModeRequest;
use App\Services\Admin\MaintenanceModeService;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;
use RuntimeException;

/** Trang bật / tắt chế độ bảo trì (§ bổ sung sau roadmap). */
class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceModeService $maintenance) {}

    public function edit(): View
    {
        return view('admin.maintenance', [
            'status' => $this->maintenance->status(),
        ]);
    }

    public function store(MaintenanceModeRequest $request): RedirectResponse
    {
        try {
            $secret = $this->maintenance->enable(
                $request->user(),
                $request->string('eta')->trim()->value() ?: null,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Không có cookie này thì chính người vừa bật bảo trì cũng bị chặn ở request kế tiếp,
        // và trang tắt bảo trì cũng không vào được nữa.
        Cookie::queue(MaintenanceModeBypassCookie::create($secret));

        return redirect()->route('admin.maintenance.edit')
            ->with('status', 'Đã bật chế độ bảo trì. Trình duyệt này vẫn xem được site thật trong 12 giờ.');
    }

    public function destroy(MaintenanceModeRequest $request): RedirectResponse
    {
        try {
            $this->maintenance->disable($request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Cookie::queue(Cookie::forget(MaintenanceModeService::BYPASS_COOKIE));

        return redirect()->route('admin.maintenance.edit')->with('status', 'Đã tắt chế độ bảo trì. Site đang phục vụ bình thường.');
    }
}
