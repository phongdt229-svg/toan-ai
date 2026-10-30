# SEO từ khoá — "học toán online" & "học toán với AI"

Kế hoạch 30/10/2026. Mỗi trang nhắm **một** ý định tìm kiếm chính để các trang của mình không tranh hạng nhau.
Số lượng tìm kiếm chưa đo — kiểm tra bằng Google Keyword Planner / Search Console → Hiệu suất trước khi đổi ưu tiên.

## 1. Bản đồ từ khoá → trang

| Cụm | Từ khoá chính | Từ khoá phụ | Trang |
|---|---|---|---|
| A. Học toán online | học toán online | web học toán online, học toán online miễn phí, luyện toán online | Trang chủ `/` |
| A theo lớp | học toán lớp N online | toán lớp N kết nối tri thức / chân trời sáng tạo / cánh diều | `/chuong-trinh/{lop}` |
| A theo kỳ thi | ôn thi vào 10 môn toán online | ôn thi THPT môn toán online | Trang lớp 9 / 12 + bài trụ cột |
| B. Học toán với AI | học toán với AI | học toán bằng AI, gia sư AI môn toán, AI dạy toán | `/hoc-toan-voi-ai` |
| B so sánh | AI giải toán từng bước | app giải toán AI, ChatGPT giải toán có nên không | Bài viết → trỏ về `/hoc-toan-voi-ai` |
| C. Phụ huynh | cách giúp con học giỏi toán | con học toán kém phải làm sao, kèm con học toán lớp 1 | Bài viết → trỏ về trang chủ / gói học |

**Góc khác biệt cụm B:** đối thủ là app chụp ảnh ra đáp án. Math AI = "AI dạy hiểu, không giải hộ"
(gợi ý từng bước, khoá khi làm bài kiểm tra) — có bằng chứng: nghiên cứu PNAS 2025 trích ở trang `/hoc-toan-voi-ai`.

## 2. Đã làm trong code (30/10)

- Trang chủ: title "Học Toán Online Lớp 1–12 Cùng AI Tutor | Math AI", H1 "Học Toán online thông minh cùng AI",
  description chứa "học toán online" + "GDPT 2018". Quản trị → SEO vẫn ghi đè được title/description —
  **nếu production đã đặt title cho trang chủ ở đó thì title mới trong code không hiện**, sửa luôn ở Quản trị.
- Trang mới `/hoc-toan-voi-ai` (`public.ai-learning`): đoạn trả lời nhanh đầu trang, 6 cách dùng AI, bảng so sánh
  "AI giải hộ ↔ AI dạy từng bước", nghiên cứu PNAS + trích dẫn chuyên gia, an toàn cho con, FAQ (`AiTutorFaq`,
  khai FAQPage), nguồn tham khảo. Có ở menu ("Học với AI"), footer, sitemap, llms.txt, nút ở khối AI Tutor trang chủ.
- Trang lớp: title "Học Toán lớp N online — chương trình GDPT 2018", H1 "Học Toán lớp N online", đoạn giới thiệu
  nêu cấp học + ba bộ sách + link Thông tư 32/2018.
- E-E-A-T: `<meta name="author">` mọi trang (bài tin tức lấy byline), dòng "Biên soạn · Cập nhật" + WebPage
  `author`/`dateModified` ở trang chủ, trang AI, trang lớp; khối "Cơ sở nội dung" có nguồn ngoài ở trang chủ.
  Người biên soạn thật: `SITE_EDITOR_NAME` / `SITE_EDITOR_TITLE` (trống = "Đội ngũ biên soạn Math AI").
  **Sửa nội dung trang chủ / trang AI thì sửa ngày** `$landingUpdatedAt` / `$updatedAt` trong view.

## 3. Lịch nội dung 12 tuần (2 bài/tuần)

Mỗi bài: trả lời ngay 2–3 câu đầu, có người biên soạn thật (`byline_name`), có ít nhất một nguồn ngoài uy tín,
link về trang đích của cụm bằng anchor đúng từ khoá.

| Tuần | Bài (cụm) |
|---|---|
| 2 | Học toán online miễn phí: 7 cách hiệu quả (A) · Gia sư AI môn toán là gì, có thay giáo viên được không? (B) |
| 3 | Có nên cho con dùng ChatGPT giải toán? (B) · Con học toán kém phải làm sao (C) |
| 4 | Lộ trình ôn thi vào 10 môn Toán trong 3 tháng (A) · AI giải toán từng bước khác app chụp ảnh thế nào (B) |
| 5–6 | Nhập bài cho lớp 1, 6, 9, 12 trước (lớp đầu cấp + lớp thi) — mỗi lớp có bài là thêm trang được lập chỉ mục |
| 7–8 | Chuyên đề: phân số lớp 4–5, phương trình bậc hai lớp 9, đạo hàm lớp 11 (A, link về trang lớp) |
| 9–10 | Cách kèm con học toán lớp 1 khi bố mẹ bận (C) · Học 15 phút mỗi ngày với AI: kế hoạch mẫu (B) |
| 11–12 | Ôn thi THPT môn Toán online: lộ trình 6 tháng (A) · Cập nhật bài cũ theo số liệu Search Console |

## 4. Ngoài code

- **Dọn bài mẫu** trên production (`SampleBlogSeeder`): sự kiện/khuyến mãi không có thật (livestream, cuộc thi,
  vinh danh, "đang soạn"…) — gỡ xuất bản ở Quản trị → Bài viết. Bài giữ lại đổi "TOÁN AI" → "Math AI".
- Server chậm (TTFB 1–8s, có lần 522) — `php artisan optimize`, OPcache, PHP-FPM. Đây là cản trở lớn nhất.
- Redirect `www` → miền chính (Cloudflare Redirect Rule), bật IndexNow, Bing Webmaster Tools, MX cho `lienhe@`.
- Hồ sơ Google Doanh nghiệp + Facebook/YouTube/TikTok thật → `SITE_SOCIAL_*`. Shorts/TikTok "AI gợi ý từng bước".
- Xin được nhắc trong các bài "top web học toán online" / "top app giải toán".

## 5. Đo lường

Search Console mỗi 2 tuần: hiển thị + vị trí cho các từ khoá chính. Mục tiêu 3 tháng: top 20 "học toán với AI",
top 10 từ khoá dài theo lớp. "Học toán online" cạnh tranh với Hocmai, VioEdu, Tuyensinh247 → 6–12 tháng.
GEO: hỏi ChatGPT / Perplexity / Gemini "nền tảng học toán có AI" sau 2–4 tuần; GA4 theo dõi nguồn chatgpt.com, perplexity.ai.
