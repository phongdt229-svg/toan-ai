@php
    // Lỗi thường gặp cần nổi bật hơn để học sinh không lướt qua.
    $variant = match ($section->type) {
        'common_mistake' => 'border-warning',
        'formula' => 'border-primary',
        default => '',
    };
@endphp

<section id="section-{{ $section->id }}"
         class="card border mb-3 {{ $variant }} {{ $isDone ? 'border-success' : '' }}"
         data-section-id="{{ $section->id }}">
    <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi {{ $section->typeIcon() }} text-primary fs-5"></i>
            <h2 class="h6 fw-bold mb-0">{{ $section->title ?: $section->typeLabel() }}</h2>
            <span class="badge text-bg-light border ms-auto">{{ $section->typeLabel() }}</span>
        </div>

        {{-- Nội dung đã qua HtmlSanitizer lúc lưu (LessonSection::setContentAttribute). --}}
        <div class="lesson-content" data-math>
            {!! $section->content !!}
        </div>

        <div class="mt-3">
            <button type="button" class="btn btn-sm btn-outline-primary" data-mark-done
                    @disabled($isDone)>
                @if ($isDone)
                    <i class="bi bi-check2 me-1"></i>Đã hiểu
                @else
                    Tôi đã hiểu phần này
                @endif
            </button>
        </div>
    </div>
</section>
