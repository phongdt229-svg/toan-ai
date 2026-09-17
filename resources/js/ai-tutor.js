/**
 * Widget AI Tutor (§10). Hai lối vào:
 *  - Nút nổi → mở panel chat (kèm ngữ cảnh bài học nếu trang có).
 *  - Nút [data-ai-action] cạnh từng câu hỏi → gợi ý / giải thích / phân tích lỗi / bài tương tự.
 *
 * Nội dung trả về đã được server escape (AiText::toHtml) — ở đây chỉ chèn và render KaTeX.
 */

const ACTION_PATHS = {
    hint: 'hint',
    explain: 'explain',
    check_answer: 'check-answer',
    similar_exercise: 'similar-exercise',
    analyze_mistake: 'analyze-mistake',
};

const ACTION_LABELS = {
    hint: 'Gợi ý cho em câu này',
    explain: 'Giải thích câu này',
    check_answer: 'Kiểm tra đáp án của em',
    similar_exercise: 'Cho em bài tương tự',
    analyze_mistake: 'Em sai ở đâu?',
};

export function initAiTutor() {
    const root = document.getElementById('ai-tutor');
    if (!root) return;

    const apiBase = root.dataset.apiBase;
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const panelEl = document.getElementById('ai-tutor-panel');
    const panel = window.bootstrap.Offcanvas.getOrCreateInstance(panelEl);
    const log = panelEl.querySelector('[data-ai-log]');
    const form = panelEl.querySelector('[data-ai-form]');
    const input = form.querySelector('textarea');
    const usageEl = panelEl.querySelector('[data-ai-usage]');

    let conversationId = null;
    let busy = false;

    const scrollDown = () => { log.scrollTop = log.scrollHeight; };

    function bubble(role, html, extraClass = '') {
        const el = document.createElement('div');
        el.className = role === 'user'
            ? 'align-self-end bg-primary text-white rounded-3 px-3 py-2 small'
            : `align-self-start bg-light border rounded-3 px-3 py-2 ai-bubble ${extraClass}`;
        el.style.maxWidth = '92%';
        el.setAttribute('data-math', '');
        el.innerHTML = html;
        log.appendChild(el);
        window.renderMath?.(log);
        scrollDown();
        return el;
    }

    function textBubble(role, text) {
        const safe = document.createElement('div');
        safe.textContent = text;
        return bubble(role, safe.innerHTML.replace(/\n/g, '<br>'));
    }

    function typing() {
        return bubble('assistant', '<span class="spinner-grow spinner-grow-sm me-1"></span>Đang suy nghĩ…', 'text-secondary');
    }

    function updateUsage(usage) {
        if (!usage || !usageEl) return;
        usageEl.textContent = usage.remaining === null
            ? 'Không giới hạn lượt'
            : `Còn ${usage.remaining}/${usage.limit} lượt hôm nay`;
    }

    async function post(path, body) {
        const res = await fetch(`${apiBase}/${path}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify(body),
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.success) {
            const message = json.message || (json.errors && Object.values(json.errors)[0]?.[0]) || 'Có lỗi xảy ra, thử lại sau nhé.';
            const error = new Error(message);
            error.upgrade = json.upgrade || null; // 402: gợi ý gói nâng cấp
            throw error;
        }
        updateUsage(json.meta?.usage);
        return json.data;
    }

    function renderResult(action, data) {
        switch (action) {
            case 'similar_exercise': {
                const id = `sim-${Date.now()}`;
                return bubble('assistant', `
                    <div class="fw-semibold mb-1">Bài tương tự</div>
                    <div class="mb-2">${data.problem_html}</div>
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#${id}">
                        Xem đáp án
                    </button>
                    <div class="collapse mt-2" id="${id}">
                        <div><strong>Đáp án:</strong> ${data.answer_html}</div>
                        <div class="mt-1">${data.solution_html}</div>
                    </div>`);
            }
            case 'analyze_mistake':
                return bubble('assistant', `
                    <div class="mb-2"><span class="badge text-bg-danger mb-1">Em hiểu sai</span><div>${data.misconception_html}</div></div>
                    <div class="mb-2"><span class="badge text-bg-warning text-dark mb-1">Kiến thức cần ôn</span><div>${escapeText(data.knowledge_gap)}</div></div>
                    <div class="mb-2"><span class="badge text-bg-primary mb-1">Giải thích</span><div>${data.explanation_html}</div></div>
                    <div><span class="badge text-bg-success mb-1">Gợi ý làm lại</span><div>${data.hint_html}</div></div>`);
            case 'check_answer': {
                const tone = data.is_correct === true ? 'success' : (data.is_correct === false ? 'danger' : 'secondary');
                return bubble('assistant', `<span class="badge text-bg-${tone} mb-2">${escapeText(data.verdict)}</span><div>${data.reply_html}</div>`);
            }
            default:
                return bubble('assistant', data.reply_html);
        }
    }

    async function runAction(button) {
        if (busy) return;
        const action = button.dataset.aiAction;
        const body = { question_id: Number(button.dataset.questionId) };

        if (button.dataset.answer) body.answer = JSON.parse(button.dataset.answer);

        // Kiểm tra đáp án ngay trên form đang làm: đọc giá trị học sinh vừa chọn.
        if (action === 'check_answer' && button.dataset.readFrom) {
            body.answer = readAnswer(document.querySelector(button.dataset.readFrom));
        }

        panel.show();
        textBubble('user', `${button.dataset.label || ACTION_LABELS[action]}`);
        const wait = typing();
        busy = true;

        try {
            const data = await post(ACTION_PATHS[action], body);
            wait.remove();
            renderResult(action, data);
        } catch (e) {
            wait.remove();
            const upsell = e.upgrade
                ? `<div class="mt-2"><a class="btn btn-sm btn-warning" href="${encodeURI(e.upgrade.url)}">Xem gói ${escapeText(e.upgrade.name)} · ${escapeText(e.upgrade.price)}</a></div>`
                : '';
            bubble('assistant', escapeText(e.message) + upsell, 'border-warning');
        } finally {
            busy = false;
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = input.value.trim();
        if (!message || busy) return;

        textBubble('user', message);
        input.value = '';
        const wait = typing();
        busy = true;

        try {
            const data = await post('chat', {
                message,
                conversation_id: conversationId,
                context_type: root.dataset.contextType || 'free',
                context_id: root.dataset.contextId ? Number(root.dataset.contextId) : null,
            });
            conversationId = data.conversation_id;
            wait.remove();
            bubble('assistant', data.reply_html);
        } catch (err) {
            wait.remove();
            bubble('assistant', escapeText(err.message), 'border-warning');
        } finally {
            busy = false;
            input.focus();
        }
    });

    // Enter gửi, Shift+Enter xuống dòng.
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    document.addEventListener('click', (e) => {
        const button = e.target.closest('[data-ai-action]');
        if (button) {
            e.preventDefault();
            runAction(button);
        }
    });

    // Mở lại một hội thoại cũ (trang AI Tutor).
    document.addEventListener('click', async (e) => {
        const link = e.target.closest('[data-ai-conversation]');
        if (!link) return;
        e.preventDefault();

        const res = await fetch(`${apiBase}/conversations/${link.dataset.aiConversation}`, { headers: { Accept: 'application/json' } });
        const json = await res.json();
        if (!json.success) return;

        log.innerHTML = '';
        conversationId = json.data.id;
        json.data.messages.forEach((m) => bubble(m.role, m.html));
        panel.show();
    });

    panelEl.addEventListener('shown.bs.offcanvas', () => input.focus());
}

function escapeText(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

/** Đọc đáp án từ khối câu hỏi theo đúng định dạng server mong đợi. */
function readAnswer(card) {
    if (!card) return null;
    const checked = [...card.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked')];
    if (card.querySelector('input[type="checkbox"]')) return checked.map((el) => el.value);
    if (checked.length) return checked[0].value;
    const texts = [...card.querySelectorAll('input[type="text"]')];
    if (texts.length > 1) return texts.map((el) => el.value);
    return card.querySelector('input[type="text"], textarea')?.value ?? null;
}
