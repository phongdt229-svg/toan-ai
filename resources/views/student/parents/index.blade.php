@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Kết nối phụ huynh — TOÁN AI')
@section('page_title', 'Phụ huynh')

@section('content')
    <h2 class="h5 fw-bold mb-1">Kết nối với phụ huynh</h2>
    <p class="text-secondary small mb-4">
        Phụ huynh liên kết xong sẽ xem được tiến độ, điểm, thời gian học và nhận xét giáo viên của bạn.
    </p>

    <div class="row g-3 mb-4">
        {{-- Cách 1: mã --}}
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card border h-100">
                <div class="card-body text-center">
                    <div class="small text-secondary mb-1">Cách 1 · Đọc mã cho bố mẹ</div>
                    <div class="fs-2 fw-bold font-monospace my-2" style="letter-spacing:.12em">{{ $code }}</div>
                    <div class="small text-secondary">Bố mẹ nhập ở mục <strong>Liên kết con</strong>.</div>
                </div>
            </div>
        </div>

        {{-- Cách 2: link --}}
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="small text-secondary mb-2 text-center">Cách 2 · Gửi link qua Zalo / Messenger</div>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm" value="{{ $shareUrl }}" readonly id="share-url"
                               aria-label="Link liên kết">
                        <button class="btn btn-outline-primary btn-sm" type="button" id="copy-link">
                            <i class="bi bi-clipboard"></i> Sao chép
                        </button>
                    </div>
                    <div class="small text-secondary mt-2">Link hết hạn sau {{ $linkDays }} ngày.</div>
                </div>
            </div>
        </div>

        {{-- Cách 3: QR --}}
        <div class="col-12 col-xl-4">
            <div class="card border h-100">
                <div class="card-body text-center">
                    <div class="small text-secondary mb-2">Cách 3 · Bố mẹ quét mã QR</div>
                    <div class="d-inline-block bg-white p-2 border rounded" style="max-width:220px">{!! $qrSvg !!}</div>
                </div>
            </div>
        </div>
    </div>

    <h3 class="h6 fw-bold mb-2">Đang chia sẻ với</h3>
    @forelse ($parents as $parent)
        <div class="card border mb-2">
            <div class="card-body py-2 d-flex align-items-center gap-2">
                <i class="bi bi-person-circle fs-4 text-primary"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ $parent->name }}</div>
                    <div class="text-secondary small">
                        {{ $parent->email }} · từ {{ \Illuminate\Support\Carbon::parse($parent->pivot->linked_at)->format('d/m/Y') }}
                    </div>
                </div>
                <form method="POST" action="{{ route('student.parents.revoke', $parent) }}"
                      data-confirm="Ngừng chia sẻ với {{ $parent->name }}? Mã liên kết sẽ được đổi." data-confirm-ok="Ngừng chia sẻ">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">Ngừng chia sẻ</button>
                </form>
            </div>
        </div>
    @empty
        <p class="text-secondary small">Chưa có phụ huynh nào liên kết.</p>
    @endforelse

    <div class="alert alert-light border small mt-4">
        <i class="bi bi-shield-lock me-1"></i>
        Thấy người lạ trong danh sách, hoặc lỡ gửi mã cho người khác?
        <form method="POST" action="{{ route('student.parents.regenerate') }}" class="d-inline"
              data-confirm="Đổi mã? Mã, link và QR cũ sẽ không dùng được nữa." data-confirm-ok="Đổi mã">
            @csrf
            <button class="btn btn-link btn-sm p-0 align-baseline">Đổi mã mới</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
document.getElementById('copy-link')?.addEventListener('click', async (e) => {
    const input = document.getElementById('share-url');
    try {
        await navigator.clipboard.writeText(input.value);
    } catch {
        input.select();
        document.execCommand('copy');
    }
    e.currentTarget.innerHTML = '<i class="bi bi-check2"></i> Đã chép';
});
</script>
@endpush
