/**
 * Công cụ vẽ hình học phẳng (2D) tương tác — dựng bằng JSXGraph (tự host qua npm, không CDN ngoài,
 * giống cách three.js đang dùng cho hình không gian — không phải nạp script từ geogebra.org, thứ đã
 * xác nhận bị chặn DNS cả ở sandbox dev lẫn mạng thật, xem docs/SPEC_HINH_HOC_SOAN_BAI.md).
 *
 * Module THUẦN LOGIC, không tự mount gì — nơi dùng tự gọi `mountPlaneGeometry(root, options)`,
 * cùng kiểu API với `solid-geometry-widget.js` (GH-08, docs/SPEC_HINH_HOC_SOAN_BAI.md).
 *
 * QUAN TRỌNG: file này KHÔNG được khai trong `vite.config.js → input` — xem lý do (Rollup cắt mất
 * export của entry không ai import tĩnh) ở đầu solid-geometry-widget.js, áp dụng y hệt ở đây.
 *
 * Khác hình không gian: JSXGraph tự lo kéo-thả điểm (không cần tự viết raycasting như Three.js) —
 * điểm mặc định kéo được ngay sau khi tạo, bấm vào điểm có sẵn ở các chế độ khác chỉ cần nghe sự
 * kiện 'up' của chính điểm đó, không phải tự tính khoảng cách bấm/kéo như bên 3D.
 */
import JXG from 'jsxgraph';
// jsxgraph không cho import sâu "jsxgraph/distrib/jsxgraph.css" qua Vite (package.json của nó chỉ
// export root) — dùng bản vendor, xem comment đầu resources/scss/vendor/jsxgraph.css.
import '../scss/vendor/jsxgraph.css';

export function mountPlaneGeometry(root, options = {}) {
    const { initialConfig = null, onChange = null, readOnly = false } = options;

    const canvasHost = root.querySelector('[data-plane-canvas]');
    const board = JXG.JSXGraph.initBoard(canvasHost, {
        boundingbox: [-8, 8, 8, -8],
        axis: true,
        showCopyright: false,
        showNavigation: !readOnly,
        keepAspectRatio: true,
    });

    let activeMode = 'point'; // 'point' | 'segment' | 'triangle' | 'circle' | 'angle'
    let pendingPoints = []; // tên điểm đã bấm, đang chờ đủ số lượng cho chế độ hiện tại
    const points = {}; // name -> JXG.Point
    // {kind, names, element}[] — mọi thứ đã dựng thêm (đoạn/tam giác/tròn/góc), để liệt kê + xoá.
    let constructions = [];

    const MODE_HINTS = {
        point: 'Bấm vào khoảng trống trên lưới để thêm điểm mới — kéo được điểm sau khi tạo.',
        segment: 'Bấm 2 điểm để nối đoạn thẳng.',
        triangle: 'Bấm lần lượt 3 điểm để dựng tam giác.',
        circle: 'Bấm điểm làm tâm, rồi bấm điểm thứ hai để làm bán kính.',
        angle: 'Bấm 3 điểm theo thứ tự cạnh–đỉnh–cạnh để đo góc.',
    };

    const POINTS_NEEDED = { point: 0, segment: 2, triangle: 3, circle: 2, angle: 3 };

    function formatNumber(value) {
        return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(value);
    }

    function currentColor() {
        return root.querySelector('[data-plane-color]').value || '#2563eb';
    }

    function suggestPointName() {
        const used = Object.keys(points);
        const letters = 'ABCDEFGHIJKLMNPQRSTUVWXYZ';
        for (const letter of letters) {
            if (!used.includes(letter)) return letter;
        }

        return `P${used.length}`;
    }

    function updateModeHint() {
        const hint = root.querySelector('[data-mode-hint]');
        if (activeMode !== 'point' && pendingPoints.length > 0) {
            hint.textContent = `Đã chọn ${pendingPoints.join(', ')} — bấm thêm ${POINTS_NEEDED[activeMode] - pendingPoints.length} điểm nữa.`;
        } else {
            hint.textContent = MODE_HINTS[activeMode];
        }
    }

    function notifyChange() {
        if (!onChange) return;

        onChange({
            points: Object.entries(points).map(([name, p]) => ({ name, x: p.X(), y: p.Y() })),
            constructions: constructions.map((c) => ({ kind: c.kind, names: c.names, color: c.color })),
        });
    }

    function addPoint(x, y, name) {
        const pointName = name || suggestPointName();
        const point = board.create('point', [x, y], { name: pointName, size: 4, fixed: readOnly });
        points[pointName] = point;

        // Bấm vào MỘT điểm có sẵn ở chế độ đang dựng (đoạn/tam giác/tròn/góc) — tính là "chọn điểm
        // này", không phải kéo. JSXGraph tự phân biệt kéo/bấm, 'up' chỉ bắn khi thả tại chỗ.
        point.on('up', () => {
            if (readOnly || activeMode === 'point') return;
            handlePointPicked(pointName);
        });

        notifyChange();

        return point;
    }

    function removeConstruction(index) {
        const [removed] = constructions.splice(index, 1);
        board.removeObject(removed.element);
        renderResults();
        notifyChange();
    }

    /** Dựng 1 đoạn/tam giác/tròn/góc từ tên điểm đã có sẵn — dùng chung cho bấm tay và nạp initialConfig. */
    function buildConstruction(kind, names, color) {
        const pts = names.map((n) => points[n]);
        let element;

        if (kind === 'segment') {
            element = board.create('segment', pts, { strokeColor: color, strokeWidth: 2 });
        } else if (kind === 'triangle') {
            element = board.create('polygon', pts, { fillColor: color, fillOpacity: 0.25, strokeColor: color });
        } else if (kind === 'circle') {
            element = board.create('circle', pts, { strokeColor: color, strokeWidth: 2 });
        } else if (kind === 'angle') {
            element = board.create('angle', pts, {
                radius: 1,
                fillColor: color,
                name: () => `${JXG.Math.Geometry.trueAngle(pts[0], pts[1], pts[2]).toFixed(1)}°`,
            });
        }

        constructions.push({ kind, names, element, color });
    }

    function handlePointPicked(name) {
        if (pendingPoints.includes(name)) return; // bấm lại đúng điểm đang chờ — bỏ qua
        pendingPoints.push(name);
        updateModeHint();

        if (pendingPoints.length < POINTS_NEEDED[activeMode]) return;

        const names = [...pendingPoints];
        pendingPoints = [];
        buildConstruction(activeMode, names, currentColor());
        updateModeHint();
        renderResults();
        notifyChange();
    }

    function renderResults() {
        const host = root.querySelector('[data-plane-results]');
        host.innerHTML = '';

        constructions.forEach((c, index) => {
            const [a, b, cPoint] = c.names.map((n) => points[n]);
            let label = c.names.join('');
            let value = '';

            if (c.kind === 'segment') {
                value = `${formatNumber(Math.hypot(a.X() - b.X(), a.Y() - b.Y()))} đv`;
            } else if (c.kind === 'triangle') {
                label = `Tam giác ${label}`;
                value = `chu vi ${formatNumber(c.element.Perimeter())} đv, diện tích ${formatNumber(c.element.Area())} đvdt`;
            } else if (c.kind === 'circle') {
                label = `Đường tròn tâm ${c.names[0]}`;
                value = `bán kính ${formatNumber(c.element.Radius())} đv`;
            } else if (c.kind === 'angle') {
                label = `Góc ${label}`;
                value = `${formatNumber(JXG.Math.Geometry.trueAngle(a, b, cPoint))}°`;
            }

            const row = document.createElement('div');
            row.className = 'd-flex align-items-center justify-content-between small py-1';
            row.innerHTML = `
                <span>${label}: ${value}</span>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove-construction="${index}">Xoá</button>
            `;
            host.appendChild(row);
        });

        host.querySelectorAll('[data-remove-construction]').forEach((btn) => {
            btn.addEventListener('click', () => removeConstruction(Number(btn.dataset.removeConstruction)));
        });

        root.querySelector('[data-clear-all]').classList.toggle(
            'd-none',
            Object.keys(points).length === 0 && constructions.length === 0,
        );
    }

    function setActiveMode(mode) {
        pendingPoints = [];
        activeMode = mode;
        root.querySelectorAll('[data-mode]').forEach((btn) => {
            const isActive = btn.dataset.mode === mode;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-pressed', String(isActive));
        });
        updateModeHint();
    }

    // Bấm vào khoảng TRỐNG của lưới (không trúng điểm nào) — chỉ chế độ "Thêm điểm" mới tạo điểm
    // mới ở đó; điểm lúc nào cũng bấm/kéo được bất kể chế độ, xem addPoint()'s 'up' listener.
    board.on('up', (event) => {
        if (readOnly || activeMode !== 'point') return;
        if (board.getAllObjectsUnderMouse(event).some((el) => el.elementClass === JXG.OBJECT_CLASS_POINT)) return;

        const [x, y] = board.getUsrCoordsOfMouse(event);
        addPoint(x, y);
    });

    root.querySelectorAll('[data-mode]').forEach((btn) => {
        btn.addEventListener('click', () => setActiveMode(btn.dataset.mode));
    });

    root.querySelector('[data-clear-all]').addEventListener('click', async () => {
        const confirmed = await window.confirmDialog('Xoá hết điểm và hình đã vẽ?', {
            ok: 'Xoá tất cả',
            variant: 'danger',
        });
        if (!confirmed) return;

        board.suspendUpdate();
        constructions.forEach((c) => board.removeObject(c.element));
        Object.values(points).forEach((p) => board.removeObject(p));
        board.unsuspendUpdate();

        constructions = [];
        Object.keys(points).forEach((name) => delete points[name]);
        pendingPoints = [];
        renderResults();
        notifyChange();
    });

    setActiveMode('point');

    if (initialConfig) {
        board.suspendUpdate();
        (initialConfig.points || []).forEach((p) => addPoint(p.x, p.y, p.name));
        (initialConfig.constructions || []).forEach((c) => {
            buildConstruction(c.kind, c.names, c.color || '#2563eb');
        });
        board.unsuspendUpdate();
        renderResults();
        notifyChange();
    }

    updateModeHint();

    // Chỉ xem: bỏ khối công cụ + Kết quả (số đo có thể là đáp án của bài), hình chiếm trọn chiều ngang.
    if (readOnly) {
        root.querySelectorAll('[data-edit-only]').forEach((el) => { el.style.display = 'none'; });
        root.querySelectorAll('[data-canvas-col]').forEach((el) => el.classList.replace('col-lg-8', 'col-lg-12'));
    }
}
