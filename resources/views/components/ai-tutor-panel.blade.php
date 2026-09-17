{{--
    Widget AI Tutor cho học sinh. Trang nào muốn chat có ngữ cảnh bài học thì
    @push('ai-context') data-context-type="lesson" data-context-id="…" @endpush
--}}
@php
    // loadMissing: layout dùng chung mọi trang, không phải controller nào cũng nạp sẵn hồ sơ.
    $persona = auth()->user()->loadMissing('studentProfile')->studentProfile?->tutor_persona === 'thay' ? 'Thầy' : 'Cô';
@endphp

<div id="ai-tutor" data-api-base="{{ url('api/v1/ai') }}" @stack('ai-context')>
    <button type="button" class="ai-fab btn btn-primary rounded-circle shadow"
            data-bs-toggle="offcanvas" data-bs-target="#ai-tutor-panel" aria-controls="ai-tutor-panel"
            aria-label="Mở AI Tutor">
        <i class="bi bi-robot fs-4"></i>
    </button>

    <div class="offcanvas offcanvas-end ai-panel" tabindex="-1" id="ai-tutor-panel" aria-labelledby="ai-tutor-title">
        <div class="offcanvas-header border-bottom py-2">
            <div>
                <h2 class="offcanvas-title h6 fw-bold mb-0" id="ai-tutor-title">
                    <i class="bi bi-robot text-primary me-1"></i>{{ $persona }} giáo AI
                </h2>
                <div class="small text-secondary" data-ai-usage></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Đóng"></button>
        </div>

        <div class="offcanvas-body d-flex flex-column gap-2 p-3" data-ai-log aria-live="polite">
            <div class="align-self-start bg-light border rounded-3 px-3 py-2 small" style="max-width:92%">
                Chào em! {{ $persona }} sẽ gợi ý để em tự tìm ra lời giải, chứ không làm bài hộ đâu nhé.
                Em đang vướng ở đâu?
            </div>
        </div>

        <form class="border-top p-2 d-flex gap-2 align-items-end" data-ai-form>
            <textarea class="form-control" rows="2" maxlength="2000" placeholder="Hỏi về bài học…"
                      aria-label="Câu hỏi cho AI"></textarea>
            <button class="btn btn-primary" type="submit" aria-label="Gửi"><i class="bi bi-send"></i></button>
        </form>
        <div class="small text-secondary text-center pb-2 px-2">AI có thể sai — hãy kiểm tra lại với giáo viên khi cần.</div>
    </div>
</div>
