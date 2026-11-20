@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Điểm thưởng — Quản trị Math AI')
@section('page_title', 'Điểm thưởng')

@section('content')
    <p class="text-secondary small">
        Điểm thưởng chỉ dùng để mở huy hiệu và xếp hạng tuần trong lớp — không đổi ra tiền hay mã giảm giá.
        Mỗi hoạt động chỉ được cộng một lần cho mỗi bài / đề / buổi / ngày. Đổi số điểm không tính lại điểm cũ.
    </p>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card border"><div class="card-body">
            <div class="small text-secondary">Điểm đã cộng tuần này</div>
            <div class="h4 fw-bold mb-0">{{ number_format($weekPoints, 0, ',', '.') }}</div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card border"><div class="card-body">
            <div class="small text-secondary">Học sinh có điểm tuần này</div>
            <div class="h4 fw-bold mb-0">{{ $earners }}</div>
        </div></div></div>
    </div>

    <form method="POST" action="{{ route('admin.rewards.update') }}" class="card border mb-4">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Hoạt động</th><th style="width:9rem">Số điểm</th><th style="width:6rem">Bật</th></tr></thead>
                    <tbody>
                        @foreach ($rules as $rule)
                            <tr>
                                <td class="small">{{ $rule->label }}</td>
                                <td>
                                    <input type="number" name="rules[{{ $rule->id }}][points]" min="0" max="1000" required
                                           value="{{ old("rules.{$rule->id}.points", $rule->points) }}" aria-label="Số điểm: {{ $rule->label }}"
                                           class="form-control form-control-sm @error("rules.{$rule->id}.points") is-invalid @enderror">
                                </td>
                                <td>
                                    <input type="hidden" name="rules[{{ $rule->id }}][is_active]" value="0">
                                    <input type="checkbox" class="form-check-input" name="rules[{{ $rule->id }}][is_active]" value="1"
                                           aria-label="Bật: {{ $rule->label }}" @checked(old("rules.{$rule->id}.is_active", $rule->is_active))>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button class="btn btn-primary btn-touch mt-3">Lưu</button>
        </div>
    </form>

    <h2 class="h6 fw-bold">Huy hiệu (khai ở config/badges.php)</h2>
    <div class="table-responsive">
        <table class="table table-sm small">
            <thead><tr><th>Huy hiệu</th><th>Điều kiện</th><th class="text-end">Đã trao</th></tr></thead>
            <tbody>
                @foreach (config('badges') as $key => $badge)
                    <tr>
                        <td><i class="bi {{ $badge['icon'] }} text-warning me-1"></i>{{ $badge['name'] }}</td>
                        <td>{{ $badge['description'] }}</td>
                        <td class="text-end">{{ $badgeCounts[$key] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
