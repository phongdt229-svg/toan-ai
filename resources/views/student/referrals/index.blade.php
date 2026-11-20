@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Mời bạn học cùng — Math AI')
@section('page_title', 'Mời bạn học cùng')

@section('content')
    @if (! $enabled)
        <div class="alert alert-secondary">Chương trình giới thiệu bạn bè đang tạm dừng. Mã quà em đã có vẫn dùng được tới hạn.</div>
    @else
        <div class="card border mb-4" data-referral-link>
            <div class="card-body">
                <h2 class="h6 fw-bold">Gửi link này cho bạn em</h2>
                <ul class="small mb-3 ps-3">
                    <li>Bạn đăng ký qua link được <strong>mã giảm {{ $settings->new_user_percent }}%</strong> cho gói học đầu tiên.</li>
                    <li>Khi bạn đó mua gói, em nhận <strong>mã giảm {{ $settings->referrer_percent }}%</strong> cho lần gia hạn của em.</li>
                    <li>Mã có hạn {{ $settings->voucher_valid_days }} ngày, mỗi mã dùng một lần và chỉ tài khoản được tặng dùng được.</li>
                </ul>
                <div class="input-group">
                    <input type="text" class="form-control" value="{{ $link }}" readonly aria-label="Link giới thiệu của em">
                    <button type="button" class="btn btn-outline-primary" data-copy-text="{{ $link }}">
                        <i class="bi bi-copy me-1"></i><span data-copy-label>Sao chép</span>
                    </button>
                    <button type="button" class="btn btn-primary" data-share-url="{{ $link }}"
                            data-share-text="Học Toán cùng mình trên {{ config('site.brand') }} — đăng ký qua link này được giảm {{ $settings->new_user_percent }}%:">
                        <i class="bi bi-share me-1"></i><span data-copy-label>Chia sẻ</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6"><div class="card border"><div class="card-body">
            <div class="small text-secondary">Bạn đã đăng ký qua link của em</div>
            <div class="h4 fw-bold mb-0">{{ $summary['invited'] }}</div>
        </div></div></div>
        <div class="col-6"><div class="card border"><div class="card-body">
            <div class="small text-secondary">Bạn đã mua gói</div>
            <div class="h4 fw-bold mb-0">{{ $summary['rewarded'] }}</div>
        </div></div></div>
    </div>

    <h2 class="h6 fw-bold">Mã quà của em</h2>
    @forelse ($summary['vouchers'] as $voucher)
        <div class="card border mb-2" data-owned-voucher>
            <div class="card-body small d-flex flex-wrap gap-2 align-items-center">
                <span class="font-monospace fw-bold">{{ $voucher->code }}</span>
                <span>giảm {{ (int) $voucher->value }}%</span>
                <span class="text-secondary">{{ $voucher->description }} · hạn {{ $voucher->ends_at?->format('d/m/Y') }}</span>
                <a href="{{ route('packages.index', ['ma' => $voucher->code]) }}" class="btn btn-sm btn-primary ms-auto">Dùng mã</a>
            </div>
        </div>
    @empty
        <p class="text-secondary small">Em chưa có mã quà nào.</p>
    @endforelse
@endsection
