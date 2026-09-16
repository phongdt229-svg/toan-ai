<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // MariaDB 10.4 + utf8mb4: index tối đa 767 byte → giới hạn string mặc định 191.
        Schema::defaultStringLength(191);

        // Local: nổ ngay khi lazy loading / gán field ngoài fillable — bắt N+1 từ sớm.
        Model::shouldBeStrict($this->app->isLocal());

        Paginator::useBootstrapFive();

        $this->registerPermissionGates();
    }

    /**
     * Mỗi permission trong DB thành một Gate cùng tên → dùng được `can:lesson.create`.
     * Admin bỏ qua mọi kiểm tra permission (nhưng Policy vẫn chạy nếu tự viết).
     */
    private function registerPermissionGates(): void
    {
        Gate::before(fn ($user) => $user->hasRole(Role::ADMIN) ? true : null);

        try {
            // Tránh vỡ khi chạy migrate lần đầu hoặc DB chưa sẵn sàng.
            if (! Schema::hasTable('permissions')) {
                return;
            }

            $names = Cache::remember(
                'rbac.permission_names',
                now()->addHour(),
                fn () => Permission::pluck('name')->all(),
            );
        } catch (QueryException) {
            return;
        }

        foreach ($names as $permission) {
            Gate::define($permission, fn ($user) => $user->hasPermission($permission));
        }
    }
}
