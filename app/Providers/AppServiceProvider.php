<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Providers\FakeProvider;
use App\Services\AI\Providers\OpenAiProvider;
use App\Support\HtmlSanitizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\Gateways\FakeMomoGateway;
use App\Services\Payment\Gateways\MomoGateway;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Khởi tạo HTMLPurifier khá nặng — dùng chung một instance cho cả request.
        $this->app->singleton(HtmlSanitizer::class);

        // Singleton để test lấy đúng instance FakeProvider mà service đang dùng (push/calls).
        $this->app->singleton(FakeProvider::class);

        $this->app->singleton(AiProviderInterface::class, function ($app) {
            $config = $app['config']['ai'];

            return match ($config['provider']) {
                'openai' => new OpenAiProvider(
                    apiKey: $config['openai']['api_key'],
                    model: $config['openai']['model'],
                    baseUrl: $config['openai']['base_url'],
                    timeout: $config['openai']['timeout'],
                ),
                default => $app->make(FakeProvider::class),
            };
        });

        // Cổng thanh toán (§20). `fake` chỉ dùng ở local/testing — production luôn gọi MoMo thật.
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $useFake = $app['config']['payment.gateway'] === 'fake' && ! $app->environment('production');

            return $useFake ? new FakeMomoGateway : new MomoGateway;
        });
    }

    public function boot(): void
    {
        // MariaDB 10.4 + utf8mb4: index tối đa 767 byte → giới hạn string mặc định 191.
        Schema::defaultStringLength(191);

        // Local: nổ ngay khi lazy loading / gán field ngoài fillable — bắt N+1 từ sớm.
        Model::shouldBeStrict($this->app->isLocal());

        Paginator::useBootstrapFive();

        // Mỗi lượt AI tốn tiền thật — chặn bấm liên tục. Quota theo ngày kiểm riêng ở AiUsageGuard.
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip())
            ->response(fn () => response()->json([
                'success' => false,
                'message' => 'Bạn hỏi hơi nhanh, đợi một chút rồi hỏi tiếp nhé.',
                'reason' => 'rate_limited',
            ], 429)));

        // Trần chung cho mọi request web/api: đủ rộng cho người dùng thật (autosave làm bài ~1 lần/giây),
        // chặn script cào dữ liệu. Theo user khi đã đăng nhập để nhiều học sinh chung IP trường không bị chặn oan.
        RateLimiter::for('global', fn (Request $request) => $request->user()
            ? Limit::perMinute(300)->by('u'.$request->user()->id)
            : Limit::perMinute(240)->by('ip'.$request->ip()));

        // Production sau proxy HTTPS: sinh link https (route(), asset()) dù PHP thấy request là http.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Đăng ký xong là gửi luôn mail xác thực (Laravel 12 không tự gắn listener này).
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

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
