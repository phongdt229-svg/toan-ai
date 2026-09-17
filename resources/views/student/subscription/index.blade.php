@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Gói của tôi — TOÁN AI')
@section('page_title', 'Gói của tôi')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card border h-100 {{ $current ? 'border-success' : '' }}">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Gói đang dùng</div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-gem fs-3 {{ $current ? 'text-success' : 'text-secondary' }}"></i>
                        <span class="h4 fw-bold mb-0">{{ $package?->name ?? 'Free' }}</span>
                    </div>

                    @if ($current)
                        <p class="mb-3">
                            Còn <strong>{{ $current->daysLeft() }} ngày</strong> · hết hạn {{ $current->ends_at->format('H:i d/m/Y') }}
                        </p>
                        <a href="{{ route('packages.index') }}" class="btn btn-outline-primary btn-sm">Gia hạn / nâng cấp</a>
                    @else
                        <p class="text-secondary mb-3">Nâng cấp để mở toàn bộ bài học, luyện tập không giới hạn và dùng AI nhiều hơn.</p>
                        <a href="{{ route('packages.index') }}" class="btn btn-warning btn-sm">Xem các gói</a>
                    @endif

                    @if ($package)
                        <ul class="list-unstyled small d-grid gap-1 mt-3 mb-0">
                            @foreach ($package->features->where('show_on_pricing', true) as $feature)
                                @php $off = $feature->value === '0'; @endphp
                                <li class="{{ $off ? 'text-secondary text-decoration-line-through' : '' }}">
                                    <i class="bi {{ $off ? 'bi-x-lg' : 'bi-check-lg text-success' }} me-1"></i>{{ $feature->label }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="text-secondary small mb-3">Hôm nay</div>

                    @foreach ([['Lượt hỏi AI', 'bi-robot', $aiUsage], ['Câu luyện tập', 'bi-pencil-square', $practiceUsage]] as [$label, $icon, $usage])
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span><i class="bi {{ $icon }} me-1"></i>{{ $label }}</span>
                                <span>
                                    @if ($usage['limit'] === null)
                                        {{ $usage['used'] }} · không giới hạn
                                    @else
                                        {{ $usage['used'] }}/{{ $usage['limit'] }}
                                    @endif
                                </span>
                            </div>
                            @if ($usage['limit'])
                                @php $pct = min(100, (int) round($usage['used'] / $usage['limit'] * 100)); @endphp
                                <div class="progress" style="height:8px" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar {{ $pct >= 100 ? 'bg-danger' : '' }}" style="width: {{ $pct }}%"></div>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <div class="small text-secondary">Lượt được làm mới lúc 0 giờ mỗi ngày.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center mb-2">
        <h2 class="h6 fw-bold mb-0">Lịch sử gói</h2>
        <a href="{{ route('payment.history') }}" class="small ms-auto">Lịch sử thanh toán</a>
    </div>
    @if ($history->isEmpty())
        <div class="text-secondary small">Chưa có gói nào.</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle small">
                <thead>
                    <tr><th>Gói</th><th>Trạng thái</th><th>Thời hạn</th><th class="text-end">Giá</th><th>Người mua</th></tr>
                </thead>
                <tbody>
                    @foreach ($history as $sub)
                        <tr>
                            <td>{{ $sub->package->name }}</td>
                            <td><span class="badge text-bg-{{ $sub->isEffective() ? 'success' : 'light border' }}">{{ $sub->statusLabel() }}</span></td>
                            <td>{{ $sub->starts_at ? $sub->starts_at->format('d/m/Y') . ' → ' . $sub->ends_at->format('d/m/Y') : '—' }}</td>
                            <td class="text-end">{{ number_format((float) $sub->price_paid, 0, ',', '.') }}₫</td>
                            <td>{{ $sub->source === 'manual' ? 'Quản trị cấp' : ($sub->purchaser?->id === auth()->id() ? 'Em' : $sub->purchaser?->name) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
