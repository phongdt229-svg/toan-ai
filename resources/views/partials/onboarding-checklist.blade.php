{{--
    Danh sách "Bắt đầu" cho người mới (OB-01). Mỗi mục tự tích theo dữ liệu thật — không có nút đánh dấu tay.
    Xong hết hoặc bấm "Ẩn" thì thẻ biến mất (vẫn xem lại được ở Trung tâm hướng dẫn).
--}}
@php
    $checklist = app(\App\Services\OnboardingService::class)->checklist(auth()->user());
@endphp

@if ($checklist && ! $checklist['hidden'])
    <div class="card border-primary mb-4" data-testid="onboarding-checklist" id="onboarding-checklist">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <h3 class="h6 fw-bold mb-0"><i class="bi bi-flag text-primary me-1"></i>Bắt đầu với TOÁN AI</h3>
                <span class="small text-secondary">{{ $checklist['done'] }}/{{ $checklist['total'] }} việc</span>
            </div>
            <div class="progress mb-3" style="height:6px" role="progressbar" aria-label="Tiến độ bắt đầu"
                 aria-valuenow="{{ $checklist['done'] }}" aria-valuemin="0" aria-valuemax="{{ $checklist['total'] }}">
                <div class="progress-bar" style="width:{{ round($checklist['done'] / $checklist['total'] * 100) }}%"></div>
            </div>
            <ul class="list-unstyled mb-2">
                @foreach ($checklist['items'] as $item)
                    <li class="d-flex gap-2 py-1">
                        <i @class(['bi mt-1', 'bi-check-circle-fill text-success' => $item['done'], 'bi-circle text-secondary' => ! $item['done']])></i>
                        <div>
                            @if ($item['done'])
                                <span class="text-secondary text-decoration-line-through">{{ $item['label'] }}</span>
                            @else
                                <a href="{{ $item['url'] }}" class="fw-semibold text-decoration-none">{{ $item['label'] }}</a>
                                <div class="small text-secondary">{{ $item['hint'] }}</div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <button type="button" class="btn btn-sm btn-outline-primary d-none" data-tour-start>
                    <i class="bi bi-signpost me-1"></i>Xem hướng dẫn trang này
                </button>
                <button type="button" class="btn btn-sm btn-link text-secondary ms-auto" data-onboarding-hide data-endpoint="{{ route('api.onboarding') }}">Ẩn danh sách</button>
            </div>
        </div>
    </div>
@endif
