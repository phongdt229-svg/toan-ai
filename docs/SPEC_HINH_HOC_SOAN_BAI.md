# Đặc tả: Vẽ hình (2D/3D) trong trình soạn bài học

Nguồn: đề xuất từ một AI khác (dán lại ngày 17/10/2026), đối chiếu với code thật cùng ngày.

> ⚠️ **Đề xuất gốc viết khi CHƯA đọc mã nguồn** — chính văn bản gốc nói rõ "Tôi chưa có mã nguồn
> project trong cuộc trò chuyện này... Bạn tải lên project dưới dạng ZIP". Vì vậy một số giả định
> trong đó **sai** so với repo thật: "tái sử dụng cơ chế lưu bài học... xuất PDF hiện có nếu phù hợp"
> — xuất PDF **không tồn tại** trong repo; "tích hợp theo định dạng nội dung hiện tại" — định dạng
> đó rất hẹp (xem mục 2), nhúng thẻ tuỳ chỉnh trực tiếp sẽ bị lọc mất chứ không "tích hợp" được.
> Đừng copy nguyên các bước trong đề xuất gốc lên Plane — dùng backlog ở mục 4.

---

## 1. Tóm tắt đề xuất gốc

- Thêm nút **"Vẽ hình"** vào trình soạn nội dung bài học của giáo viên.
- Hỗ trợ **hình học phẳng 2D** (đề xuất dùng JSXGraph) và **hình học không gian 3D** (đề xuất dùng
  Three.js — vì repo *hiện đã có* Three.js cho module `/hoc-sinh/hinh-hoc-khong-gian`).
- Vẽ/sửa điểm, cạnh, nhãn, màu, góc nhìn; chèn vào giữa nội dung lý thuyết; lưu cùng bài học; mở lại
  sửa được; học sinh xem lại đúng vị trí; tận dụng xuất PDF nếu có.
- Chưa cần AI giải hình, chưa cần hệ quản lý tài liệu hay DB riêng cho module này.

## 2. Đối chiếu với code hiện tại

✅ có thể tái dùng trực tiếp · 🟡 có một phần, cần sửa · ❌ chưa có, phải xây mới

| Phần đề xuất cần | Trạng thái | Thực tế trong repo |
|---|---|---|
| Nơi lưu nội dung bài học | 🟡 | `lesson_sections.content` (`longText`, HTML đã sanitize) — không phải một khối lớn, mà 9 section theo loại (theory/example/quiz…), xem `app/Models/LessonSection.php`. |
| Định dạng content hiện tại | 🟡 | HTML thường + **cú pháp KaTeX dạng text** (`$...$`, `$$...$$`) — không phải JSON cây node. |
| Bộ lọc HTML lúc lưu | 🟡 | `app/Support/HtmlSanitizer.php`, whitelist RẤT HẸP: `p,br,strong,b,em,i,u,s,sub,sup,h2,h3,h4,ul,ol,li,blockquote,hr,table...,a[href\|title\|rel],img[src\|alt\|width\|height],code,pre,span[class],div[class]`. **Không `data-*` tuỳ ý, không `data:` URI (chặn ảnh base64), không SVG.** Nhúng thẳng `<div data-geometry="...">` hoặc `<svg>` → bị xoá sạch. |
| Trình soạn bài (editor) | 🟡 | Tiptap v3 (`resources/js/lesson-editor.js`). Submit ra **HTML thô**, không phải JSON Tiptap — xem mục 3. |
| Hiển thị cho học sinh | ✅ | `resources/views/student/lesson/partials/section.blade.php`: in raw `{!! $section->content !!}` trong `<div class="lesson-content" data-math">`, JS tự render công thức sau khi DOM load. |
| Xuất PDF | ❌ | **Không tồn tại.** Không có `dompdf`/`snappy` trong `composer.json`, không route/controller nào liên quan. Đề xuất "tận dụng nếu có" — không có gì để tận dụng. |
| Renderer 3D tái dùng được | 🟡 | `resources/js/solid-geometry.js` (865 dòng) đã chạy tốt ở trang riêng `/hoc-sinh/hinh-hoc-khong-gian`, nhưng là **IIFE gắn cứng DOM một trang** (`const root = document.querySelector('[data-solid-geometry]'); if (root) { ...toàn bộ logic... }`), không `export` gì, state ở biến module-scope. Không nhúng được vào chỗ khác nguyên trạng — phải refactor thành factory function. |
| Renderer 2D | ❌ | Chưa có JSXGraph hay bất kỳ thư viện hình học phẳng nào trong `package.json`. Phải thêm dependency mới hoàn toàn. |
| Giới hạn theo gói (package) | ❌ | Soạn bài học hiện chỉ kiểm permission (`LessonPolicy`), không có khoá `PackageFeature` nào liên quan "vẽ hình"/"lesson authoring". Nếu muốn giới hạn tính năng vẽ hình theo gói giáo viên thì đây là khái niệm mới, chưa có hạ tầng. |

### Mẫu có sẵn cần noi theo: công thức Toán ($...$)

Công thức Toán giải đúng bài toán tương tự ("nhúng nội dung tương tác vào HTML bị lọc hẹp") rồi —
nên noi theo chứ không phát minh lại:

- **Lưu trong DB / hiển thị cho học sinh**: text thuần `$x^2$` — qua `HtmlSanitizer` nguyên vẹn vì
  không phải markup (comment ngay trong `HtmlSanitizer.php:12`: *"Cú pháp KaTeX là text thuần nên đi
  qua purifier nguyên vẹn"*). Học sinh xem: KaTeX auto-render quét `[data-math]`, thay `$...$` bằng
  công thức (`resources/js/app.js`, hàm `renderMath()`).
- **Trong trình soạn**: `resources/js/editor/math-html.js` có 2 hàm đối xứng —
  `toEditorHtml()` (mở bài: quét text `$...$` trong HTML, thay bằng `<span data-math-inline
  data-latex="...">` để Tiptap hiển thị thành node bấm-sửa-được) và `fromEditorHtml()` (lưu bài:
  ngược lại, node → text `$...$`). Textarea ẩn giữ **HTML thô cuối cùng** — server/Sanitizer không
  cần biết gì về Tiptap hay node công thức.

**Hình vẽ sẽ làm hệt vậy** — xem thiết kế ở mục 3.

## 3. Thiết kế đề xuất

### 3.1. Định dạng lưu — token text, không phải thẻ HTML tuỳ chỉnh

```
[[hinh:eyJ0eXBlIjoiM2QiLCJzaGFwZSI6ImJveCIsImRpbXMiOnsiYSI6NH0sLi4ufQ==]]
```

Một đoạn text `[[hinh:BASE64_JSON]]` nằm giữa nội dung (trong `<p>` như công thức), JSON mã hoá
base64 trước khi nhúng — base64 chỉ có `A-Z a-z 0-9 + / =`, không ký tự HTML đặc biệt, không trùng
`$` của KaTeX, nên chắc chắn đi qua `HtmlSanitizer` mà không cần sửa whitelist (an toàn hơn phương
án mở `data-*`/SVG — mở thêm bề mặt cho HTML lạ lọt qua đúng nguyên tắc "lọc một lần lúc lưu" đang
cố giữ). Cấu trúc JSON bên trong (ví dụ):

```jsonc
{
  "type": "3d",              // "3d" (V1) | "2d" (V2)
  "shape": "box",            // khớp SHAPES trong resources/js/solid-geometry.js
  "dims": { "a": 4, "b": 3, "c": 2 },
  "lines": [{ "from": "A", "to": "D" }],
  "points": [{ "name": "M", "from": "A", "to": "B", "ratio": 0.25 }],
  "regions": [{ "points": ["A", "B", "C", "D"] }],
  "camera": { "position": [6, 5, 9] }  // góc nhìn lúc giáo viên lưu
}
```

**Không cần bảng DB mới** — toàn bộ cấu hình nằm trong chính `lesson_sections.content` như công
thức Toán, nhất quán với nguyên tắc "không hard-code, nhưng cũng không phình kiến trúc cho một tính
năng còn chưa rõ có cần tái sử dụng hình giữa nhiều bài hay không". Nếu sau này thật sự cần thư viện
hình dùng chung nhiều bài, tách bảng riêng lúc đó — đừng xây trước khi có nhu cầu thật.

### 3.2. Trong trình soạn — Tiptap Node mới `geometryBlock`

Y hệt mẫu `math-html.js`, thêm `resources/js/editor/geometry-html.js`:

- `toEditorHtml(html)`: regex tìm `[[hinh:BASE64]]` trong text node → thay bằng
  `<div data-geometry-block data-config="BASE64">` (NodeView Tiptap render ra khung xem trước nhỏ +
  nút "Sửa hình" mở lại modal vẽ).
- `fromEditorHtml(html)`: ngược lại, `[data-geometry-block]` → text `[[hinh:...]]`.
- Node mới trong `lesson-editor.js` (cạnh `MathInline`/`MathBlock` đã có): toolbar thêm nút **"Vẽ
  hình"** → mở modal full-screen chứa renderer (xem 3.3) ở chế độ chỉnh sửa; bấm "Chèn vào bài" ghi
  JSON vào node, đóng modal.

### 3.3. Renderer dùng chung — widget hoá `solid-geometry.js`

Refactor file hiện tại từ IIFE gắn cứng DOM thành factory:

```js
// resources/js/solid-geometry-widget.js (MỚI — thay cho IIFE cũ)
export function mountSolidGeometry(container, { initialConfig, onChange, readOnly } = {}) { ... }
```

- `/hoc-sinh/hinh-hoc-khong-gian` (trang độc lập, đang chạy tốt) đổi sang gọi
  `mountSolidGeometry(canvasHost)` không `initialConfig` — **hành vi y hệt hiện tại, không bớt tính
  năng của trang này.**
- Modal "Vẽ hình" trong lesson editor gọi `mountSolidGeometry(modalCanvas, { initialConfig, onChange })`
  — `onChange` nhận state hiện tại (shape/dims/lines/points/regions/camera) để nút "Chèn vào bài" lấy
  ra JSON.
- Trang xem bài học gọi `mountSolidGeometry(el, { initialConfig, readOnly: true })` — xoay/zoom xem
  được nhưng không vẽ thêm (đúng tinh thần "học sinh xem hình", không phải công cụ tự học nữa ở đây).

**Không viết lại từ đầu** — 865 dòng đang chạy đúng (đã test kỹ qua Playwright ở các đợt trước) chỉ
cần tách phần truy cập DOM (`document.querySelector('[data-solid-geometry]')`, các biến module-scope
`auxiliaryLines`/`customPoints`/...) thành tham số/state cục bộ của factory.

### 3.4. Hiển thị cho học sinh — mở rộng auto-render

`resources/js/app.js::renderMath()` đang quét `[data-math]` cho KaTeX. Thêm `renderGeometry()` cùng
cơ chế: quét `[[hinh:...]]` trong `[data-math]` (dùng chung container, không cần thêm thuộc tính
mới), parse JSON, gọi `mountSolidGeometry(..., { readOnly: true })` thay chỗ token bằng khung hình.
Idempotent qua `dataset.geometryRendered` giống `dataset.mathRendered`.

### 3.5. Xuất PDF — NGOÀI PHẠM VI, ghi chú cho sau

Không có gì để "tận dụng". Nếu làm sau: PDF không chạy JS nên Three.js/JSXGraph sống không hiển thị
được — cần "chụp" hình thành ảnh tĩnh (PNG) **lúc giáo viên bấm "Chèn vào bài"** (canvas.toDataURL()),
lưu ảnh đó riêng (storage, không nhúng base64 vào content vì `HtmlSanitizer` chặn `data:` URI — xem
2), PDF renderer dùng ảnh tĩnh, trang học sinh vẫn dùng renderer sống (tương tác được, không phải PDF).

## 4. Quyết định cần chốt trước khi code

- **D-1 · Làm 2D (JSXGraph) ở V1 luôn, hay chỉ 3D trước?** ~~Đề xuất ban đầu: chỉ 3D trước~~ —
  **đã chốt 17/10: làm cả hai**, không tách V1/V2 nữa. Vẫn giữ nguyên thứ tự thực hiện bên dưới
  (3D xong trước — GH-01 đến GH-07 — rồi mới 2D — GH-08) vì 2D phụ thuộc đúng cùng một "token
  format"/pipeline soạn-lưu-hiển thị mà 3D dựng trước sẽ kiểm chứng; làm xong đường ống đó một lần
  cho 3D rồi lắp 2D vào cùng đường ống, không phải xây lại từ đầu.
- **D-2 · Quyền dùng "Vẽ hình"** — mọi giáo viên đã được tạo bài (permission `lesson.create`) đều
  dùng được, hay giới hạn theo gói? Hiện chưa có khoá `PackageFeature` nào cho lesson authoring;
  nếu cần giới hạn phải thêm khoá mới vào `PackageFeature::KEYS` + `KEY_TYPES` (theo đúng quy tắc ở
  CLAUDE.md, không hard-code). Đề xuất: **không giới hạn ở V1** — thêm sau nếu có nhu cầu kinh doanh.
- **D-3 · Chụp ảnh tĩnh cho PDF** có cần ngay không, hay để khi nào thật sự làm PDF mới tính? Đề
  xuất: để sau (mục 3.5), vì chưa có PDF thì chụp ảnh trước cũng không dùng vào đâu.

## 5. Backlog

Quy ước: **Ưu tiên** Urgent/High/Medium/Low · **Ước lượng** theo buổi làm (≈ 4h) · ✅ = tiêu chí nghiệm thu.

### Module: Widget hoá renderer 3D

**GH-01 · Tách `solid-geometry.js` thành factory `mountSolidGeometry()`** — High · 1–2 buổi
- Không đổi hành vi hiện có; `/hoc-sinh/hinh-hoc-khong-gian` vẫn chạy y hệt sau refactor.
- State (shape/dims/auxiliaryLines/customPoints/regions/camera) đọc/ghi được từ bên ngoài qua
  `initialConfig`/`onChange`, không còn biến module-scope dùng chung giữa nhiều instance.
- ✅ Test Playwright hiện có của trang `/hoc-sinh/hinh-hoc-khong-gian` vẫn xanh không sửa gì; thêm
  test mount 2 instance cùng lúc trên 1 trang không đụng state của nhau.

**GH-02 · Chế độ `readOnly` (xem, không sửa)** — Medium · nửa buổi
- Ẩn toolbar chế độ (Nối đường/Điểm trên đoạn/Đo vùng), ẩn ô nhập số liệu — chỉ xoay/zoom xem.
- ✅ Trang xem bài học dùng chế độ này, không có nút sửa nào lộ ra cho học sinh.

### Module: Soạn bài học (giáo viên)

**GH-03 · `resources/js/editor/geometry-html.js` (toEditorHtml/fromEditorHtml)** — High · 1 buổi
- Mirror đúng `math-html.js`; base64 JSON roundtrip không mất dữ liệu (test: lưu → tải lại → cấu
  hình giống hệt).
- ✅ Token `[[hinh:...]]` nằm trong đoạn `<code>`/`<pre>` KHÔNG bị parse (giống `$` trong code của
  `math-html.js`).

**GH-04 · Tiptap Node `geometryBlock` + nút "Vẽ hình" trong toolbar** — High · 1–2 buổi
- NodeView hiện khung xem trước nhỏ (ảnh tĩnh render 1 lần lúc mount node, không chạy Three.js đầy
  đủ trong mỗi node — editor có nhiều hình cùng lúc không được lag) + nút "Sửa".
- Bấm "Vẽ hình" hoặc "Sửa" → modal full-screen mount `mountSolidGeometry()` chế độ chỉnh sửa.
- ✅ Chèn hình → bấm Lưu bài → tải lại trang soạn → mở lại đúng hình, đúng số liệu, sửa tiếp được.

**GH-05 · Giáo viên xoá hình đã chèn** — Low · nửa buổi
- Nút xoá trên NodeView (dùng `window.confirmDialog()`, **không** `confirm()` mặc định — đúng quy
  tắc CLAUDE.md).
- ✅ Xoá xong lưu lại, token biến mất khỏi `content`.

### Module: Hiển thị cho học sinh

**GH-06 · `renderGeometry()` trong `resources/js/app.js`** — High · 1 buổi
- Quét `[[hinh:...]]` trong `[data-math]`, mount readOnly đúng vị trí trong dòng chảy nội dung.
- ✅ Bài học có hình hiện đúng chỗ đã chèn (không nhảy xuống cuối bài); hình xoay/zoom được; không
  lỗi console khi trang có nhiều hình.

**GH-07 · Test tổng hợp luồng end-to-end** — High · 1 buổi
- Giáo viên tạo bài → chèn hình hộp chữ nhật → lưu → học sinh xem bài → thấy đúng hình, đúng số
  liệu → quay lại sửa (đổi kích thước) → học sinh xem lại thấy số liệu mới.
- ✅ Test Playwright phủ đúng luồng trên (có tiền lệ nhiều lần trong session này: giả lập chuột thật,
  không chỉ click element, vì renderer dùng raycasting theo toạ độ pixel).

### Module: 2D (làm sau 3D — cùng V1, không phải V2, xem D-1)

**GH-08 · Thêm JSXGraph, widget `mountPlaneGeometry()` cùng kiểu API với 3D** — High · 2–3 buổi
- Vẽ được: đoạn thẳng, tam giác, đường tròn, góc; đặt tên điểm A/B/C; đổi màu.
- `type: "2d"` trong cùng định dạng token `[[hinh:...]]` ở mục 3.1 — `geometry-html.js`/
  `renderGeometry()` không cần sửa gì, chỉ thêm nhánh `type === '2d'` gọi widget 2D thay vì 3D.
- ✅ Cùng tiêu chí nghiệm thu như GH-04/GH-06 nhưng cho hình phẳng.

## 6. Việc KHÔNG làm ở V1 (và vì sao)

- **Xuất PDF** — chưa tồn tại hạ tầng, không có gì "tận dụng"; làm khi có yêu cầu PDF thật, lúc đó
  mới biết cần ảnh tĩnh chụp sẵn hay render HTML→PDF trực tiếp.
- **AI giải hình / gợi ý hình tự động** — đúng như đề xuất gốc đã loại trừ.
- **Giới hạn vẽ hình theo gói học** — chưa có nhu cầu kinh doanh rõ ràng (D-2), thêm khoá
  `PackageFeature` sau nếu cần.
- **Bảng DB riêng cho thư viện hình dùng chung nhiều bài** — token nhúng trong content đủ dùng cho
  V1 (mục 3.1); tách bảng khi có nhu cầu tái sử dụng thật, không xây trước.
