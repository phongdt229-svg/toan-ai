{{--
    Lịch học tuần (D-01) — dùng chung cho học sinh và phụ huynh.
    Nhận $schedule (Collection keyBy weekday), $action (URL lưu), $weeklyMinutes.
    Không JS: mỗi ngày là một dòng, tick "Học" thì giờ/thời lượng mới có nghĩa — tắt JS vẫn dùng được.
--}}
<form method="POST" action="{{ $action }}" class="card border" style="max-width:40rem">
    @csrf
    @method('PUT')

    <div class="card-body">
        @error('days')<div class="alert alert-danger small">{{ $message }}</div>@enderror

        <div class="vstack gap-2">
            @foreach (\App\Models\StudySchedule::WEEKDAYS as $weekday => $label)
                @php
                    $slot = $schedule->get($weekday);
                    $enabled = (bool) old("days.{$weekday}.enabled", $slot !== null);
                @endphp
                <div class="row g-2 align-items-center border-bottom pb-2">
                    <div class="col-12 col-sm-4">
                        <div class="form-check form-switch mb-0">
                            <input type="hidden" name="days[{{ $weekday }}][enabled]" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="day-{{ $weekday }}"
                                   name="days[{{ $weekday }}][enabled]" value="1" @checked($enabled)>
                            <label class="form-check-label fw-semibold" for="day-{{ $weekday }}">{{ $label }}</label>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4">
                        <label class="visually-hidden" for="time-{{ $weekday }}">Giờ bắt đầu {{ $label }}</label>
                        <input type="time" id="time-{{ $weekday }}" name="days[{{ $weekday }}][start_time]"
                               value="{{ old("days.{$weekday}.start_time", $slot?->startLabel() ?? '19:30') }}"
                               class="form-control form-control-sm @error("days.{$weekday}.start_time") is-invalid @enderror">
                    </div>
                    <div class="col-6 col-sm-4">
                        <label class="visually-hidden" for="duration-{{ $weekday }}">Thời lượng {{ $label }}</label>
                        <select id="duration-{{ $weekday }}" name="days[{{ $weekday }}][duration_minutes]"
                                class="form-select form-select-sm @error("days.{$weekday}.duration_minutes") is-invalid @enderror">
                            @foreach (\App\Models\StudySchedule::DURATIONS as $minutes)
                                <option value="{{ $minutes }}" @selected((int) old("days.{$weekday}.duration_minutes", $slot?->duration_minutes ?? 45) === $minutes)>
                                    {{ $minutes }} phút
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
            <span class="small text-secondary">Đang đặt: <strong class="text-body">{{ $schedule->count() }} buổi · {{ $weeklyMinutes }} phút/tuần</strong></span>
            <button class="btn btn-primary btn-touch">Lưu lịch học</button>
        </div>
    </div>
</form>
