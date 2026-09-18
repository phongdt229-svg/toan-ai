{{--
    Phân trang Bootstrap 5, bản tiếng Việt.
    Khác bản gốc của Laravel: bỏ dòng "Showing x to y of z results" (component <x-pagination>
    đã hiện thông tin này bằng tiếng Việt) và dùng nhãn tiếng Việt cho nút trước/sau.
--}}
@if ($paginator->hasPages())
    <nav aria-label="Phân trang">
        <ul class="pagination mb-0">
            {{-- Trang trước --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true" aria-label="Trang trước">
                    <span class="page-link" aria-hidden="true">&lsaquo;</span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Trang trước">&lsaquo;</a>
                </li>
            @endif

            {{-- Số trang: ẩn bớt trên điện thoại để không tràn ngang --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled d-none d-sm-block" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item d-none d-sm-block">
                                <a class="page-link" href="{{ $url }}" aria-label="Trang {{ $page }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Trang sau --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Trang sau">&rsaquo;</a>
                </li>
            @else
                <li class="page-item disabled" aria-disabled="true" aria-label="Trang sau">
                    <span class="page-link" aria-hidden="true">&rsaquo;</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
