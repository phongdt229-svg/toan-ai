{{-- Buổi kế tiếp bị khoá vì đã học hết số buổi học thử của gói Free. Nhận $session, $limit. --}}
<div class="card border-warning mb-4" data-testid="session-locked">
    <div class="card-body text-center py-4">
        <i class="bi bi-lock fs-2 text-warning"></i>
        <h3 class="h6 fw-bold mt-2 mb-1">Buổi {{ $session->session_no }} cần gói học</h3>
        <p class="text-secondary small mb-3">
            Em đã học xong {{ $limit }} buổi học thử. Mua gói để học tiếp lộ trình riêng của em —
            kết quả, lộ trình và tiến độ vẫn được giữ nguyên.
        </p>
        <a href="{{ route('packages.index') }}" class="btn btn-warning btn-touch">
            <i class="bi bi-gem me-1"></i>Xem gói học
        </a>
    </div>
</div>
