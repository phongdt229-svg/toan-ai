{{-- Bộ lọc học sinh §13: Tất cả / Cần hỗ trợ / Chưa làm bài / Điểm thấp / Đang tiến bộ --}}
@php use App\Services\Teaching\StudentInsightService; @endphp

<div class="d-flex flex-wrap gap-2 mb-3" role="tablist" aria-label="Lọc học sinh">
    @foreach (StudentInsightService::FILTERS as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['filter' => $key]) }}"
           class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-outline-secondary' }}"
           role="tab" aria-selected="{{ $filter === $key ? 'true' : 'false' }}">
            {{ $label }}
            <span class="badge {{ $filter === $key ? 'text-bg-light' : 'text-bg-secondary' }} ms-1">{{ $filterCounts[$key] }}</span>
        </a>
    @endforeach
</div>

<details class="small text-secondary mb-3">
    <summary>Cách hệ thống đánh dấu</summary>
    <ul class="mb-0 mt-2">
        <li><strong>Điểm thấp:</strong> điểm TB các bài được giao dưới {{ StudentInsightService::LOW_SCORE_PERCENT }}%.</li>
        <li><strong>Chưa làm bài:</strong> có bài đã quá hạn mà chưa nộp.</li>
        <li><strong>Cần hỗ trợ:</strong> điểm thấp, hoặc từ {{ StudentInsightService::OVERDUE_FOR_SUPPORT }} bài quá hạn, hoặc từ {{ StudentInsightService::WEAK_TOPICS_FOR_SUPPORT }} chủ đề yếu.</li>
        <li><strong>Đang tiến bộ:</strong> 3 bài gần nhất cao hơn các bài trước ít nhất {{ StudentInsightService::IMPROVING_DELTA }} điểm % (cần ≥ 4 bài có điểm).</li>
    </ul>
</details>
