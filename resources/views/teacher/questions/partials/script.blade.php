<script>
(function () {
    const typeSelect = document.querySelector('[data-question-type]');
    if (!typeSelect) return;

    // Loại câu hỏi nào dùng khối đáp án nào.
    const blockFor = {
        single_choice: 'choice',
        multiple_choice: 'choice',
        true_false: 'true_false',
        fill_blank: 'fill_blank',
        short_answer: 'short_answer',
        essay: 'essay',
    };

    const blocks = document.querySelectorAll('[data-answer-block]');

    function syncBlocks() {
        const wanted = blockFor[typeSelect.value];

        blocks.forEach((el) => {
            el.hidden = el.dataset.answerBlock !== wanted;
        });

        // "Một đáp án đúng" chỉ cho tick một ô.
        const single = typeSelect.value === 'single_choice';
        document.querySelectorAll('#option-rows input[type="checkbox"]').forEach((cb) => {
            cb.dataset.singleMode = single ? '1' : '';
        });
    }

    typeSelect.addEventListener('change', syncBlocks);
    syncBlocks();

    // Tick ô này thì bỏ tick các ô khác, khi đang ở chế độ một đáp án.
    document.getElementById('option-rows')?.addEventListener('change', (e) => {
        if (e.target.type !== 'checkbox' || !e.target.checked) return;
        if (e.target.dataset.singleMode !== '1') return;

        document.querySelectorAll('#option-rows input[type="checkbox"]').forEach((cb) => {
            if (cb !== e.target) cb.checked = false;
        });
    });

    // Chỉ số dòng phải liên tục vì server đối chiếu correct_options theo chỉ số.
    function reindexOptions() {
        document.querySelectorAll('#option-rows [data-option-row]').forEach((row, i) => {
            row.querySelector('input[type="checkbox"]').value = i;
            row.querySelector('input[type="text"]').name = `options[${i}][content]`;
            row.querySelector('input[type="text"]').placeholder = `Nội dung lựa chọn ${i + 1}`;
        });
    }

    document.getElementById('add-option')?.addEventListener('click', () => {
        const rows = document.getElementById('option-rows');
        const first = rows.querySelector('[data-option-row]');
        const clone = first.cloneNode(true);

        clone.querySelector('input[type="checkbox"]').checked = false;
        clone.querySelector('input[type="text"]').value = '';
        rows.appendChild(clone);
        reindexOptions();
    });

    document.getElementById('option-rows')?.addEventListener('click', (e) => {
        if (!e.target.closest('[data-remove-option]')) return;

        const rows = document.getElementById('option-rows');
        if (rows.querySelectorAll('[data-option-row]').length <= 2) return;

        e.target.closest('[data-option-row]').remove();
        reindexOptions();
    });

    document.getElementById('add-blank')?.addEventListener('click', () => {
        const rows = document.getElementById('blank-rows');
        const clone = rows.querySelector('[data-blank-row]').cloneNode(true);

        clone.querySelector('input[type="text"]').value = '';
        rows.appendChild(clone);
        rows.querySelectorAll('[data-blank-row]').forEach((row, i) => {
            row.querySelector('.input-group-text').textContent = `Chỗ ${i + 1}`;
        });
    });

    document.getElementById('blank-rows')?.addEventListener('click', (e) => {
        if (!e.target.closest('[data-remove-blank]')) return;

        const rows = document.getElementById('blank-rows');
        if (rows.querySelectorAll('[data-blank-row]').length <= 1) return;

        e.target.closest('[data-blank-row]').remove();
        rows.querySelectorAll('[data-blank-row]').forEach((row, i) => {
            row.querySelector('.input-group-text').textContent = `Chỗ ${i + 1}`;
        });
    });

    // Xem trước công thức KaTeX.
    document.querySelectorAll('[data-preview-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const editor = document.querySelector('[data-math-editor]');
            const target = document.querySelector('[data-preview-target]');
            if (!editor || !target) return;

            if (!target.classList.contains('d-none')) {
                target.classList.add('d-none');
                btn.textContent = 'Xem trước';
                return;
            }

            target.innerHTML = editor.value;
            delete target.dataset.mathRendered;
            target.classList.remove('d-none');
            btn.textContent = 'Ẩn xem trước';
            window.renderMath(target.parentElement);
        });
    });
})();
</script>
