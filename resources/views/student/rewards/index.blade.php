@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Thành tích — Math AI')
@section('page_title', 'Thành tích')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border h-100"><div class="card-body">
                <div class="small text-secondary">Tổng điểm thưởng</div>
                <div class="h3 fw-bold mb-0" data-testid="reward-total"><i class="bi bi-star-fill text-warning"></i> {{ number_format($total, 0, ',', '.') }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border h-100"><div class="card-body">
                <div class="small text-secondary">Tuần này</div>
                <div class="h3 fw-bold mb-0">+{{ number_format($week, 0, ',', '.') }}</div>
            </div></div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card border h-100"><div class="card-body small">
                <div class="fw-semibold mb-1">Cách nhận điểm</div>
                <ul class="mb-0 ps-3">
                    @foreach ($rules->where('is_active', true) as $rule)
                        <li>{{ $rule->label }}: <strong>+{{ $rule->points }}</strong></li>
                    @endforeach
                </ul>
                <div class="text-secondary mt-1">Điểm dùng để mở huy hiệu và đua top tuần trong lớp.</div>
            </div></div>
        </div>
    </div>

    <h2 class="h6 fw-bold">Huy hiệu</h2>
    <div class="row g-2 mb-4">
        @foreach ($badges as $key => $badge)
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card border h-100 {{ $badge['earned_at'] ? '' : 'opacity-50' }}" data-badge="{{ $key }}" data-earned="{{ $badge['earned_at'] ? '1' : '0' }}">
                    <div class="card-body text-center small">
                        <i class="bi {{ $badge['icon'] }} fs-2 {{ $badge['earned_at'] ? 'text-warning' : 'text-secondary' }}" aria-hidden="true"></i>
                        <div class="fw-semibold mt-1">{{ $badge['name'] }}</div>
                        <div class="text-secondary">{{ $badge['description'] }}</div>
                        <div class="mt-1 {{ $badge['earned_at'] ? 'text-success' : 'text-secondary' }}">
                            {{ $badge['earned_at'] ? 'Đạt ngày '.$badge['earned_at']->format('d/m/Y') : 'Chưa đạt' }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <h2 class="h6 fw-bold">Bảng xếp hạng tuần này</h2>
    @forelse ($boards as $board)
        <div class="card border mb-3" data-leaderboard>
            <div class="card-body">
                <div class="fw-semibold mb-2">{{ $board['class']->name }}</div>
                <ol class="list-unstyled mb-0 small">
                    @foreach ($board['rows'] as $row)
                        <li class="d-flex gap-2 py-1 border-bottom {{ $row['me'] ? 'fw-bold text-primary' : '' }}">
                            <span style="width:2rem">#{{ $row['rank'] }}</span>
                            <span class="flex-grow-1">{{ $row['name'] }}{{ $row['me'] ? ' (em)' : '' }}</span>
                            <span>{{ number_format($row['points'], 0, ',', '.') }} điểm</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    @empty
        <p class="text-secondary small">Em chưa ở lớp nào. Khi thầy cô cho em vào lớp, bảng xếp hạng tuần của lớp sẽ hiện ở đây.</p>
    @endforelse
    <p class="small text-secondary">
        {{ $hidden ? 'Tên em đang được ẩn với các bạn trong lớp.' : 'Các bạn cùng lớp thấy tên em trên bảng xếp hạng.' }}
        Đổi ở <a href="{{ route('student.settings') }}">Cài đặt</a>.
    </p>

    <h2 class="h6 fw-bold mt-4">Điểm gần đây</h2>
    @forelse ($history as $row)
        <div class="small d-flex justify-content-between border-bottom py-1">
            <span>{{ $rules->get($row->reason)?->label ?? $row->reason }}</span>
            <span class="text-success">+{{ $row->points }} · {{ $row->created_at->format('d/m H:i') }}</span>
        </div>
    @empty
        <p class="text-secondary small">Chưa có điểm nào — học xong một bài là có điểm đầu tiên.</p>
    @endforelse
@endsection
