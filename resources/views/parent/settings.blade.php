@extends('layouts.app', ['portal' => 'parent'])

@section('title', 'Cài đặt — TOÁN AI')
@section('page_title', 'Cài đặt')

@section('content')
    <h2 class="h5 fw-bold mb-3">Cài đặt</h2>

    @include('partials.profile-form', ['user' => auth()->user(), 'profileRoute' => route('parent.settings.profile')])
    @include('partials.email-form')
    @include('partials.notification-preferences-form')
    @include('partials.password-form', ['passwordRoute' => route('parent.settings.password')])
    @include('partials.data-export')
    @include('partials.danger-zone')

    <div class="card border mt-3" style="max-width:36rem">
        <div class="card-body">
            <h3 class="h6 fw-bold mb-3">Báo cáo & theo dõi buổi học</h3>
            <form method="POST" action="{{ route('parent.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="weekly_report_enabled"
                           name="weekly_report_enabled" value="1" @checked($profile->weekly_report_enabled)>
                    <label class="form-check-label fw-semibold" for="weekly_report_enabled">Nhận báo cáo tuần qua email</label>
                </div>
                <p class="text-secondary small mt-1 mb-3">
                    Gửi tối Chủ nhật tới <strong>{{ auth()->user()->email }}</strong>: số bài đã học, tỉ lệ làm đúng,
                    bài quá hạn và nhận xét mới của giáo viên. Tuần con không học gì sẽ không gửi.
                </p>

                @if ($profile->last_weekly_report_at)
                    <p class="small text-secondary">Lần gửi gần nhất: {{ $profile->last_weekly_report_at->format('H:i d/m/Y') }}</p>
                @endif

                {{-- TA-13: mặc định tắt cả hai — bật thì mỗi ngày có thêm thông báo. --}}
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="session_events_enabled"
                           name="session_events_enabled" value="1" @checked($profile->session_events_enabled)>
                    <label class="form-check-label fw-semibold" for="session_events_enabled">Báo khi con bắt đầu và học xong buổi</label>
                </div>
                <p class="text-secondary small mt-1 mb-3">Theo lịch học của con: báo lúc con vào học và khi chốt buổi (có mặt / học chưa đủ).
                    Con vắng hoặc chưa vào học thì luôn được báo, không phụ thuộc mục này.</p>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="daily_report_enabled"
                           name="daily_report_enabled" value="1" @checked($profile->daily_report_enabled)>
                    <label class="form-check-label fw-semibold" for="daily_report_enabled">Nhận báo cáo cuối ngày</label>
                </div>
                <p class="text-secondary small mt-1 mb-3">Gửi lúc 21:30 qua email và ứng dụng: hôm nay con học thực bao nhiêu phút,
                    điểm danh, bài kiểm tra cuối buổi. Ngày con không có lịch và không học thì không gửi.</p>

                <button class="btn btn-primary">Lưu</button>
            </form>
        </div>
    </div>
@endsection
