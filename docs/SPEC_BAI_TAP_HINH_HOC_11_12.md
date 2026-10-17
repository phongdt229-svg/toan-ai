# Kế hoạch: Bài tập hình học không gian lớp 11–12 (có hình tương tác)

Soạn ngày 17/10/2026, đối chiếu code cùng ngày. Dùng để đưa lên Plane — backlog ở mục 5.
Tiếp nối [SPEC_HINH_HOC_SOAN_BAI.md](SPEC_HINH_HOC_SOAN_BAI.md) (GH-01…GH-08).

## 1. Mục tiêu

Học sinh lớp 11–12 làm **bài tập có hình 3D xoay được** ngay trong luyện tập / đề / bài giao, và có
công cụ để tự dựng-đo hình khi giải. Giáo viên gắn hình vào câu hỏi mà không cần chèn ảnh tay.

## 2. Phạm vi nội dung (lấy từ [database/data/curriculum.php](../database/data/curriculum.php))

| Lớp | Chương | Chủ đề dùng hình |
|---|---|---|
| 11 | Quan hệ song song trong không gian | giao tuyến, đường∥mặt, mặt∥mặt, thiết diện, phép chiếu song song |
| 11 | Quan hệ vuông góc trong không gian | đường⊥đường, đường⊥mặt, mặt⊥mặt, khoảng cách, thể tích chóp/lăng trụ |
| 12 | Vectơ và hệ toạ độ trong không gian | Oxyz, vectơ, tích vô hướng/có hướng |
| 12 | Phương pháp toạ độ trong không gian | mặt phẳng, đường thẳng, mặt cầu, góc & khoảng cách |
| 12 | Nguyên hàm và tích phân (ứng dụng) | thể tích vật thể tròn xoay (2D, đã có widget) |

## 3. Hiện trạng (đã kiểm)

| Cần | Trạng thái | Ghi chú |
|---|---|---|
| Widget 3D | 🟡 | `mountSolidGeometry()` ([solid-geometry-widget.js](../resources/js/solid-geometry-widget.js)) chỉ có 7 khối **đều/đơn giản**: hộp, lập phương, chóp tam giác đều, chóp tứ giác đều, trụ, nón, cầu. |
| Widget 2D | ✅ | `mountPlaneGeometry()` — dùng cho tích phân/thiết diện phẳng. |
| Hình trong **câu hỏi** | ❌ | `questions` chỉ có `content` (HTML qua `HtmlSanitizer`) — chưa có chỗ gắn hình. Token `[[hinh:...]]` (GH-03/06) **chưa làm** nên chưa hiện được ở luyện tập/đề/bài giao. |
| Hình bài tập 11–12 điển hình | ❌ | Đề thật hầu hết là **S.ABCD đáy chữ nhật/hình vuông/thang/hình thoi, SA ⊥ đáy**, lăng trụ đứng đáy tam giác vuông, tứ diện vuông/đều — chưa có. |
| Thiết diện, góc, khoảng cách | ❌ | Widget mới có nối đoạn, điểm theo tỉ lệ, đo vùng. |
| Hệ toạ độ Oxyz | ❌ | Chưa có trục, chưa nhập toạ độ điểm/vectơ/mặt phẳng. |
| Chấm đúng/sai | ✅ | `GradingService` — AI/hình **không** quyết định đáp án (đúng nguyên tắc CLAUDE.md). |

## 4. Quyết định cần chốt

- **D-1 · Hình gắn vào câu hỏi bằng cách nào?** Đề xuất: **dùng lại token `[[hinh:BASE64]]`** nằm trong
  `questions.content`/`explanation` (đã chốt ở spec trước: qua `HtmlSanitizer` nguyên vẹn, không cần
  cột/bảng mới). Không thêm cột `figure`.
- **D-2 · Hình trong bài tập là tương tác hay ảnh tĩnh?** Đề xuất: tương tác `readOnly` (xoay/zoom),
  hiển thị ở luyện tập/đề/bài giao. Ảnh tĩnh chỉ cần khi làm PDF (ngoài phạm vi).
- **D-3 · Bài tập sinh bằng AI?** Đề xuất: **không ở đợt này**. Dựng ngân hàng bài tay/seeder trước;
  AI chỉ soạn nháp qua luồng draft có sẵn (người duyệt mới publish).
- **D-4 · Có tách tính năng theo gói không?** Đề xuất: không; dùng đúng quyền hiện có của luyện tập/đề.

## 5. Backlog (thứ tự thực hiện)

Quy ước: Ưu tiên Urgent/High/Medium/Low · ước lượng theo buổi (≈ 4h) · ✅ = nghiệm thu.

### Module A — Hoàn thiện đường ống hình trong nội dung (điều kiện tiên quyết)

> **17/10 — code xong GH-03/04/06 (chỉ 3D), `npm run build` xanh, codec base64 đã thử bằng node. CHƯA kiểm trong
> trình duyệt và CHƯA chạy `php artisan test`** (máy này không có PHP/Docker). Việc còn lại: chạy `GeometryEmbedTest`,
> thử tay trên `/giao-vien/...bai-hoc` (Vẽ hình → Lưu → mở lại → học sinh xem), rồi tới GH-09.
> Đã làm thêm: widget có `readOnly` (ẩn mọi khối sửa **và khối Kết quả** để không lộ thể tích = đáp án),
> khung HTML tách ra `partials/solid-geometry-widget.blade.php`, route `geometry.solid-markup` cho JS nhúng tải khi cần.

**GH-03 · `geometry-html.js` (toEditorHtml/fromEditorHtml)** — High · 1 buổi — *đã có trong spec cũ*
**GH-04 · Tiptap node `geometryBlock` + nút "Vẽ hình" cho trình soạn** — High · 1–2 buổi
**GH-06 · `renderGeometry()` quét `[[hinh:...]]` trong `[data-math]`** — High · 1 buổi
- **Mở rộng so với spec cũ**: `[data-math]` còn xuất hiện ở practice/exams/assignments/placement/Q&A
  (xem `grep data-math resources/views`) → `renderGeometry()` chạy ở **mọi** chỗ này, không chỉ bài học.
- ✅ Câu hỏi luyện tập có token → học sinh thấy hình 3D xoay được; đề đang làm giờ vẫn đếm đúng,
  hình không làm lộ đáp án (dữ liệu hình là phần đề bài, không chứa đáp án).

**GH-09 · Editor câu hỏi (giáo viên) dùng được nút "Vẽ hình"** — High · 1 buổi
- Trình soạn câu hỏi dùng lại node `geometryBlock` ở `content` và `explanation`.
- ✅ Lưu → mở lại sửa được; HTML qua `HtmlSanitizer`, token còn nguyên (test sanitizer có token base64).

> **17/10 (tối)** — token `type: "2d"` đã nối vào hộp thoại Vẽ hình (chọn 2D/3D), trình soạn bài, form câu hỏi và trang xem (chỉ-xem, ẩn số đo). Đã thử bằng Playwright trên local.

### Module B — Thêm khối hình đúng kiểu đề 11–12

> **17/10 — code xong GH-09** (`question-geometry.js`: nút "Vẽ hình 3D" + danh sách Sửa/Xoá dưới ô nội dung và lời giải),
> **GH-10, GH-11, và tứ diện vuông của GH-12** (`pyramidRect`, `prismRight`, `tetraRight`). Build xanh; chưa xem hình thật
> trên trình duyệt, chưa chạy test PHP (máy không có PHP). Còn lại GH-12: tứ diện đều, chóp đáy thang/hình thoi.

**GH-10 · Chóp S.ABCD đáy chữ nhật/hình vuông, SA ⊥ đáy** — High · 1 buổi
- Input: AB, AD, SA. Nhãn S, A, B, C, D. Kết quả: thể tích, đường chéo.
- ✅ Khớp số liệu tính tay (V = ⅓·AB·AD·SA); ghi vào `SHAPES` cùng pattern 7 khối cũ.

**GH-11 · Lăng trụ đứng đáy tam giác (thường / vuông / đều)** — High · 1 buổi
**GH-12 · Tứ diện (đều, vuông ở đỉnh O — OA⊥OB⊥OC), chóp đáy thang/hình thoi** — Medium · 1–2 buổi
- ✅ Mỗi khối có test dựng đỉnh/nhãn đúng, thể tích khớp công thức.

### Module C — Công cụ giải toán trên hình

**GH-13 · Thiết diện của mặt phẳng đi qua 3 điểm** — High · 2 buổi
- Chọn 3 điểm (đỉnh hoặc điểm theo tỉ lệ đã có) → tính giao với các cạnh → tô đa giác thiết diện,
  hiện diện tích. Dùng lại cơ chế "đo vùng" + "điểm trên đoạn".
- ✅ Thiết diện hộp qua trung điểm 3 cạnh = lục giác đều (kiểm số đỉnh + diện tích).

**GH-14 · Đo góc & khoảng cách** — High · 2 buổi
- Góc đường–đường, đường–mặt, mặt–mặt (giữa 2 mặt/2 mặt phẳng chọn từ điểm); khoảng cách điểm–mặt,
  điểm–đường. Hiện cách tính (công thức) cạnh kết quả để học sinh đối chiếu, không điền đáp án hộ.
- ✅ Kiểm với S.ABCD: d(A,(SBC)) ra đúng số tính tay.

### Module D — Hệ toạ độ Oxyz (lớp 12)

**GH-15 · Chế độ Oxyz: trục, lưới, điểm theo toạ độ** — High · 2 buổi
- Mount mới `mountCoordinateSpace()` (cùng API `initialConfig/onChange/readOnly`), nhập điểm A(x;y;z),
  vẽ vectơ AB, độ dài, tích vô hướng, trung điểm.
- ✅ Nhập 2 điểm → độ dài/vectơ khớp tính tay; hình kéo xoay được.

**GH-16 · Vẽ mặt phẳng (ax+by+cz+d=0), đường thẳng, mặt cầu trong Oxyz** — Medium · 2–3 buổi
- Hiện giao điểm, khoảng cách điểm–mặt, vị trí tương đối. Mặt cầu tái dùng sphere sẵn có.
- ✅ Test số học (service JS thuần) tách khỏi đồ hoạ để unit-test được.

### Module E — Ngân hàng bài tập

**GH-17 · Seeder bài tập mẫu có hình (lớp 11: 10 bài; lớp 12: 10 bài)** — Medium · 2 buổi
- Mỗi bài: `Question` (trắc nghiệm 4 đáp án / trả lời ngắn) + token hình + lời giải từng bước ở
  `explanation`. Gắn đúng `topic_id` (chương ở mục 2), 3 mức Dễ→TB→Khó.
- Câu có đáp án số học → `short_answer` (chấm tự động), không dùng tự luận chấm tay trừ bài chứng minh.
- ✅ Seeder idempotent (không nhân đôi khi chạy lại); mỗi câu chấm đúng qua `GradingService`.

**GH-18 · Bộ đề mẫu "Hình học không gian 11" & "Toạ độ Oxyz 12" (trạng thái Nháp)** — Low · 1 buổi
- ✅ Giáo viên mở thấy ở danh sách đề, tự xuất bản (đề luôn Nháp lúc tạo — đúng quy tắc).

### Module F — Kiểm thử

**GH-19 · Test E2E học sinh làm bài có hình** — High · 1 buổi
- Playwright (chuột thật vì raycasting theo pixel): làm 1 câu luyện tập 11 có hình → xoay hình →
  trả lời → xem lời giải; làm 1 đề 12 có Oxyz.
- ✅ Không lỗi console khi trang có nhiều hình; hết giờ/nộp bài vẫn đúng.

## 6. Thứ tự đề xuất

1. GH-03 → GH-04 → GH-06 → GH-09 (đường ống; mở khoá tất cả phần sau)
2. GH-10, GH-11 (khối hình phổ biến nhất ở lớp 11) song song với GH-17 phần lớp 11
3. GH-14 → GH-13 (công cụ giải)
4. GH-15 → GH-16 (lớp 12) + GH-17 phần lớp 12
5. GH-12, GH-18, GH-19

Tổng ước lượng: ~20–25 buổi. Có thể phát hành từng đợt: sau bước 2 là đã có bài tập 11 có hình.

## 7. Không làm ở đợt này

- AI tự giải/tự sinh hình, tự chấm hình vẽ tay của học sinh (đáp án chỉ từ DB).
- Xuất PDF đề có hình (cần ảnh tĩnh — xem mục 3.5 spec cũ).
- Bảng DB riêng cho thư viện hình dùng chung.
- Giới hạn theo gói.
