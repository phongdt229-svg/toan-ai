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
