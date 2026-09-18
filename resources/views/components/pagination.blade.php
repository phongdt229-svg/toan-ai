@props([
    'paginator',
    'label' => 'kết quả',   // "18 học sinh", "42 giao dịch"…
])

{{--
    Thanh phân trang dùng chung cho mọi bảng/danh sách.
    Đặt NGAY SAU .table-responsive thì nó dính liền vào đáy bảng (xem _tables.scss).
--}}
@if ($paginator->total() > 0)
    <div {{ $attributes->merge(['class' => 'table-pagination']) }}>
        <div class="table-pagination__info">
            Hiển thị <strong>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</strong>
            trong <strong>{{ number_format($paginator->total(), 0, ',', '.') }}</strong> {{ $label }}
        </div>

        @if ($paginator->hasPages())
            {{-- onEachSide(1): trên điện thoại chỉ hiện vài trang quanh trang hiện tại, không tràn ngang. --}}
            {{ $paginator->onEachSide(1)->links() }}
        @endif
    </div>
@endif
