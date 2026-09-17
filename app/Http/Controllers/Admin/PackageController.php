<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PackageRequest;
use App\Models\Package;
use App\Services\PackageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class PackageController extends Controller
{
    public function __construct(private readonly PackageService $packages) {}

    public function index(): View
    {
        return view('admin.packages.index', [
            'packages' => Package::ordered()
                ->withCount(['subscriptions as active_count' => fn ($q) => $q->effective()])
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.packages.form', ['package' => new Package(['is_active' => true, 'tier' => 'pro']), 'features' => collect()]);
    }

    public function store(PackageRequest $request): RedirectResponse
    {
        $package = $this->packages->save(null, $request->validated());

        return redirect()->route('admin.packages.index')->with('status', "Đã tạo gói {$package->name}.");
    }

    public function edit(Package $package): View
    {
        return view('admin.packages.form', [
            'package' => $package,
            'features' => $package->features()->get()->keyBy('key'),
        ]);
    }

    public function update(PackageRequest $request, Package $package): RedirectResponse
    {
        $this->packages->save($package, $request->validated());

        return redirect()->route('admin.packages.index')
            ->with('status', "Đã lưu gói {$package->name}. Giá mới chỉ áp dụng cho lượt mua sau.");
    }

    public function destroy(Package $package): RedirectResponse
    {
        try {
            $this->packages->delete($package);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.packages.index')->with('status', 'Đã xoá gói.');
    }
}
