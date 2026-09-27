<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nén HTML trả về (bỏ khoảng trắng thừa giữa thẻ, bỏ comment) khi `MINIFY_HTML=true` (§ SEO/hiệu
 * năng, kế hoạch 27/09). Mặc định TẮT — chỉ bật ở production, để HTML lúc dev vẫn đọc/debug được
 * qua "View source".
 *
 * An toàn là trên hết, không cố nén tối đa: nội dung trong <script>, <style>, <pre>, <textarea>
 * được giữ NGUYÊN VĂN — đụng vào JS/CSS/khoảng trắng có ý nghĩa (vd trong <pre>) là dễ vỡ hơn là
 * lợi. Không đụng JSON/XML (sitemap.xml, RSS, API) vì chỉ áp dụng cho response Content-Type
 * text/html.
 */
class MinifyHtml
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('site.minify_html')) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '');

        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (is_string($content) && $content !== '') {
            $response->setContent($this->minify($content));
        }

        return $response;
    }

    private function minify(string $html): string
    {
        // Giữ nguyên nội dung 4 loại thẻ này — thay tạm bằng placeholder, nén phần còn lại,
        // rồi trả nguyên văn lại. Không dùng placeholder trùng nội dung thật có thể xuất hiện.
        $placeholders = [];

        $html = preg_replace_callback(
            '/<(script|style|pre|textarea)\b[^>]*>.*?<\/\1>/is',
            function (array $m) use (&$placeholders): string {
                $key = "\0MINIFY_PH".count($placeholders)."\0";
                $placeholders[$key] = $m[0];

                return $key;
            },
            $html,
        ) ?? $html;

        // Bỏ comment HTML thường — giữ lại comment điều kiện kiểu <!--[if ...]--> (IE cũ) cho an toàn.
        $html = preg_replace('/<!--(?!\[if\s).*?-->/s', '', $html) ?? $html;

        // Khoảng trắng thuần giữa hai thẻ (">   <") không ảnh hưởng cách trình duyệt render — bỏ hẳn.
        $html = preg_replace('/>\s+</', '><', $html) ?? $html;

        // Phần khoảng trắng còn sót (kể cả xuống dòng đơn lẻ) — vd giữa cuối text node và thẻ kế
        // tiếp, hoặc cạnh placeholder ở trên (placeholder không phải ">"/"<" nên bước trước không
        // bắt được). Trình duyệt vốn đã gộp mọi chuỗi khoảng trắng thành 1 dấu cách khi hiển thị
        // text thường, nên thay bằng đúng 1 dấu cách là an toàn — không đổi cách hiển thị.
        $html = preg_replace('/\s+/', ' ', $html) ?? $html;

        return trim(strtr($html, $placeholders));
    }
}
