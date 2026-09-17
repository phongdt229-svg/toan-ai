<?php

namespace App\Support;

/**
 * Hiển thị câu trả lời của AI. KHÔNG tin HTML từ model: escape toàn bộ, rồi chỉ
 * bật lại vài định dạng an toàn (in đậm, xuống dòng). LaTeX giữ nguyên để KaTeX render phía client.
 */
final class AiText
{
    public static function toHtml(?string $text): string
    {
        $escaped = e(trim((string) $text));

        // **đậm** → <strong>. Chạy SAU e() nên không thể chèn thẻ tuỳ ý.
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;

        return nl2br($escaped, false);
    }
}
