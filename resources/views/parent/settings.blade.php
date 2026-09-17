@extends('layouts.app', ['portal' => 'parent'])

@section('title', 'Cài đặt — TOÁN AI')
@section('page_title', 'Cài đặt')

@section('content')
    <h2 class="h5 fw-bold mb-3">Cài đặt</h2>

    <div class="card border" style="max-width:36rem">
        <div class="card-body">
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

                <button class="btn btn-primary">Lưu</button>
            </form>
        </div>
    </div>
@endsection
