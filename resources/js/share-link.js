/**
 * Nút sao chép / chia sẻ dùng chung:
 *   <button data-copy-text="...">  — chép vào clipboard, đổi nhãn thành "Đã chép" 2 giây
 *   <button data-share-url="..." data-share-text="..."> — điện thoại có Web Share (Zalo, Messenger…) thì mở
 *     bảng chia sẻ của máy; máy tính không có thì chép link.
 * Không dùng alert() (xem CLAUDE.md).
 */
async function copy(text, button) {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const area = document.createElement('textarea');
        area.value = text;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
    }
    const label = button.querySelector('[data-copy-label]') ?? button;
    const original = label.textContent;
    label.textContent = 'Đã chép';
    setTimeout(() => { label.textContent = original; }, 2000);
}

document.addEventListener('click', async (event) => {
    const copyBtn = event.target.closest('[data-copy-text]');
    if (copyBtn) {
        event.preventDefault();
        await copy(copyBtn.dataset.copyText, copyBtn);
        return;
    }

    const shareBtn = event.target.closest('[data-share-url]');
    if (shareBtn) {
        event.preventDefault();
        const url = shareBtn.dataset.shareUrl;
        if (navigator.share) {
            try {
                await navigator.share({ title: document.title, text: shareBtn.dataset.shareText ?? '', url });
            } catch {
                // Người dùng đóng bảng chia sẻ — không làm gì.
            }
            return;
        }
        await copy(url, shareBtn);
    }
});
