<?php

namespace App\Http\Requests\Web;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return WebPushChannel::configured();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:191'],
            'keys.auth' => ['required', 'string', 'max:191'],
        ];
    }

    /** Chưa khai khoá VAPID thì tính năng "không tồn tại", trả 404 chứ không phải 403. */
    protected function failedAuthorization(): void
    {
        throw new NotFoundHttpException;
    }
}
