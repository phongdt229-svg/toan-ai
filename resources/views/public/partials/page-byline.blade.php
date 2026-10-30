{{--
    Dòng "Biên soạn · Cập nhật" cho trang giới thiệu (E-E-A-T). $updated là Carbon hoặc chuỗi Y-m-d —
    ngày SỬA NỘI DUNG thật, không lấy thời gian deploy (deploy nào cũng đổi mtime file).
--}}
@php $updatedAt = \Illuminate\Support\Carbon::parse($updated); @endphp
<p class="small text-secondary {{ $class ?? 'mb-3' }}">
    <i class="bi bi-person-check me-1" aria-hidden="true"></i>Biên soạn:
    <span class="fw-semibold">{{ \App\Support\PageAuthor::name() }}</span>@if (\App\Support\PageAuthor::title()), {{ \App\Support\PageAuthor::title() }}@endif
    <span class="mx-1" aria-hidden="true">·</span>
    Cập nhật: <time datetime="{{ $updatedAt->toDateString() }}">{{ $updatedAt->format('d/m/Y') }}</time>
</p>
