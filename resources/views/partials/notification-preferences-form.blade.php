{{-- Dùng chung 4 portal — mỗi loại thông báo một công tắc, bật hết theo mặc định (opt-out). --}}
<div class="card border mb-3" style="max-width:36rem">
    <div class="card-body">
        <h3 class="h6 fw-bold mb-3">Thông báo trong app</h3>
        <form method="POST" action="{{ route('notifications.preferences.update') }}">
            @csrf
            @method('PUT')

            @foreach (\App\Support\NotificationType::LABELS as $type => $label)
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="notif-{{ $loop->index }}" name="enabled[]" value="{{ $type }}"
                           @checked(! auth()->user()->hasMutedNotification($type))>
                    <label class="form-check-label" for="notif-{{ $loop->index }}">{{ $label }}</label>
                </div>
            @endforeach

            <button class="btn btn-primary btn-touch mt-2">Lưu</button>
        </form>
    </div>
</div>

{{--
    Công tắc thông báo đẩy. Chỉ hiện khi máy chủ đã khai khoá VAPID (config/push.php) —
    chưa khai thì không có gì để bật, hiện công tắc chết chỉ làm người dùng bối rối.
--}}
@if (\App\Notifications\Channels\WebPushChannel::configured())
    <div class="card border mt-4">
        <div class="card-body">
            <h3 class="h6 fw-bold mb-2">Thông báo đẩy về máy</h3>
            <p class="small text-secondary">
                Nhận nhắc ngay trên điện thoại hoặc máy tính, kể cả khi không mở trang —
                nhắc gói sắp hết hạn và bài giao sắp tới hạn. Bật riêng cho từng thiết bị.
            </p>

            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="push-toggle"
                       data-key="{{ config('push.public_key') }}">
                <label class="form-check-label small" for="push-toggle">Bật trên thiết bị này</label>
            </div>
            <div id="push-status" class="form-text"></div>
        </div>
    </div>

    @push('scripts')
        @vite('resources/js/push.js')
    @endpush
@endif
