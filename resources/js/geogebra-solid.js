/**
 * Công cụ hình học không gian — nhúng GeoGebra 3D (script deployggb.js tải riêng ở
 * resources/views/student/solid_geometry/index.blade.php) thay vì tự dựng WebGL.
 *
 * Việc của file này chỉ có 2: (1) dựng sẵn 1 trong 7 hình chương trình lớp 11–12 bằng lệnh
 * GeoGebra đúng theo số liệu nhập, (2) đọc Volume()/SurfaceArea() GeoGebra đã tính sẵn để hiện
 * lại ở khung "Kết quả" cho giống trải nghiệm cũ. Thêm điểm/vẽ đường/đo vùng không cần tự viết
 * nữa — học sinh dùng thẳng thanh công cụ GeoGebra (Điểm, Đoạn thẳng, Trung điểm, Đa giác,
 * Khoảng cách, Diện tích…), vốn đã chín và quen thuộc hơn nhiều so với bản tự dựng trước đó.
 */
const root = document.querySelector('[data-solid-geometry]');
if (root && typeof GGBApplet === 'undefined') {
    // deployggb.js tải từ geogebra.org không lên được (mạng kém, chặn quảng cáo, tường lửa…) —
    // báo rõ thay vì im lặng vỡ cả trang (GGBApplet undefined sẽ ném lỗi nếu cứ chạy tiếp).
    root.querySelector('[data-solid-canvas]').innerHTML = `
        <div class="d-flex align-items-center justify-content-center h-100 text-center text-secondary small p-3">
            Không tải được công cụ dựng hình (geogebra.org). Kiểm tra kết nối mạng hoặc trình chặn
            quảng cáo rồi tải lại trang.
        </div>
    `;
} else if (root) {
    // Mỗi hình: inputs (nhãn + giá trị mặc định + chặn dưới) + commands(dims) trả mảng lệnh GeoGebra
    // dựng hình đó, cộng dòng cuối luôn là "vol=Volume(...)" và "area=SurfaceArea(...)" — 2 tên biến
    // CỐ ĐỊNH để readResults() đọc lại sau khi dựng, không cần biết chi tiết hình bên trong.
    const SHAPES = {
        box: {
            label: 'Hình hộp chữ nhật',
            inputs: [
                { key: 'a', label: 'Chiều dài a', value: 4 },
                { key: 'b', label: 'Chiều rộng b', value: 3 },
                { key: 'c', label: 'Chiều cao c', value: 2 },
            ],
            commands: ({ a, b, c }) => [
                'A=(0,0,0)', `B=(${a},0,0)`, `C=(${a},${b},0)`, `D=(0,${b},0)`,
                'poly1=Polygon(A,B,C,D)',
                `solid1=Prism(poly1,${c})`,
            ],
        },

        cube: {
            label: 'Hình lập phương',
            inputs: [{ key: 'a', label: 'Cạnh a', value: 3 }],
            commands: ({ a }) => SHAPES.box.commands({ a, b: a, c: a }),
        },

        pyramid3: {
            label: 'Chóp tam giác đều',
            inputs: [
                { key: 'a', label: 'Cạnh đáy a', value: 4 },
                { key: 'h', label: 'Chiều cao h', value: 5 },
            ],
            // Dùng Pyramid(poly, ĐIỂM) chứ không phải Pyramid(poly, chiều cao) — dạng chiều cao để
            // GeoGebra tự đặt tên đỉnh (không theo "S" của sách giáo khoa); tự định nghĩa S trước
            // để đặt tên đúng ý. S = tâm đáy (trung bình 3 đỉnh) nâng lên h theo trục Z.
            commands: ({ a, h }) => [
                'A=(0,0,0)', `B=(${a},0,0)`, 'C=Rotate(B,60°,A)',
                'poly1=Polygon(A,B,C)',
                `S=(A+B+C)/3+(0,0,${h})`,
                'solid1=Pyramid(poly1,S)',
            ],
        },

        pyramid4: {
            label: 'Chóp tứ giác đều',
            inputs: [
                { key: 'a', label: 'Cạnh đáy a', value: 4 },
                { key: 'h', label: 'Chiều cao h', value: 5 },
            ],
            commands: ({ a, h }) => [
                'A=(0,0,0)', `B=(${a},0,0)`, `C=(${a},${a},0)`, `D=(0,${a},0)`,
                'poly1=Polygon(A,B,C,D)',
                `S=(A+B+C+D)/4+(0,0,${h})`,
                'solid1=Pyramid(poly1,S)',
            ],
        },

        cylinder: {
            label: 'Hình trụ',
            inputs: [
                { key: 'r', label: 'Bán kính đáy r', value: 2 },
                { key: 'h', label: 'Chiều cao h', value: 5 },
            ],
            // GeoGebra không cho dấu "'" trong tên biến — đặt tên nội bộ "Op", nhưng hiện CHÚ THÍCH
            // "O'" thay cho tên thật (SetLabelMode mode 3 = hiện chú thích thay vì tên biến).
            commands: ({ r, h }) => [
                'O=(0,0,0)', `Op=(0,0,${h})`,
                `solid1=Cylinder(O,Op,${r})`,
                'SetCaption(Op,"O\'")',
                'SetLabelMode(Op,3)',
            ],
        },

        cone: {
            label: 'Hình nón',
            inputs: [
                { key: 'r', label: 'Bán kính đáy r', value: 2.5 },
                { key: 'h', label: 'Chiều cao h', value: 5 },
            ],
            commands: ({ r, h }) => [
                'O=(0,0,0)', `S=(0,0,${h})`,
                `solid1=Cone(O,S,${r})`,
            ],
        },

        sphere: {
            label: 'Hình cầu',
            inputs: [{ key: 'r', label: 'Bán kính r', value: 3 }],
            commands: ({ r }) => [
                'O=(0,0,0)',
                `solid1=Sphere(O,${r})`,
            ],
        },
    };

    const canvasHost = root.querySelector('[data-solid-canvas]');
    let ggbApplet = null;

    function formatNumber(value) {
        return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(value);
    }

    function resize() {
        if (!ggbApplet) return;
        const width = Math.round(canvasHost.clientWidth);
        const height = Math.round(canvasHost.clientHeight);
        if (width && height) ggbApplet.setSize(width, height);
    }

    function readDims(shapeKey) {
        const dims = {};
        SHAPES[shapeKey].inputs.forEach(({ key }) => {
            const input = root.querySelector(`[data-dim="${key}"]`);
            dims[key] = Math.max(0.1, Number(input.value) || 0.1);
        });

        return dims;
    }

    function renderInputs(shapeKey) {
        const host = root.querySelector('[data-solid-inputs]');
        host.innerHTML = '';

        SHAPES[shapeKey].inputs.forEach(({ key, label, value }) => {
            const wrap = document.createElement('div');
            wrap.className = 'mb-2';
            wrap.innerHTML = `
                <label class="form-label small mb-1">${label}</label>
                <input type="number" min="0.1" step="0.1" value="${value}"
                       data-dim="${key}" class="form-control form-control-sm">
            `;
            host.appendChild(wrap);
        });

        host.querySelectorAll('input').forEach((input) => {
            input.addEventListener('input', () => buildShape(shapeKey, readDims(shapeKey)));
        });
    }

    function renderResults() {
        const host = root.querySelector('[data-solid-results]');
        host.innerHTML = '';

        [['vol', 'Thể tích', 'đvtt'], ['area', 'Diện tích toàn phần', 'đvdt']].forEach(([name, label, unit]) => {
            const value = ggbApplet.getValue(name);
            if (!Number.isFinite(value)) return;

            const row = document.createElement('div');
            row.className = 'solid-geometry__result';
            row.innerHTML = `<div class="small text-secondary">${label}</div>
                <div class="fw-bold">${formatNumber(value)} ${unit}</div>`;
            host.appendChild(row);
        });
    }

    function buildShape(shapeKey, dims) {
        ggbApplet.newConstruction(); // xoá sạch hình cũ, giữ nguyên cấu hình applet (toolbar, góc nhìn…)
        SHAPES[shapeKey].commands(dims).forEach((cmd) => ggbApplet.evalCommand(cmd));
        ggbApplet.evalCommand('vol=Volume(solid1)');
        ggbApplet.evalCommand('area=SurfaceArea(solid1)');
        renderResults();
    }

    function selectShape(shapeKey) {
        root.querySelectorAll('[data-shape]').forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.shape === shapeKey);
        });
        renderInputs(shapeKey);
        buildShape(shapeKey, readDims(shapeKey));
    }

    root.querySelectorAll('[data-shape]').forEach((btn) => {
        btn.addEventListener('click', () => selectShape(btn.dataset.shape));
    });

    const params = {
        appName: '3d',
        width: 600,
        height: 480,
        showToolBar: true,
        showAlgebraInput: false,
        showMenuBar: false,
        showResetIcon: false,
        enableRightClick: false,
        language: 'vi',
        appletOnLoad: (api) => {
            ggbApplet = api;
            new ResizeObserver(resize).observe(canvasHost);
            resize();
            selectShape(root.querySelector('[data-shape]').dataset.shape);
        },
    };

    // eslint-disable-next-line no-undef -- GGBApplet là biến toàn cục do deployggb.js nạp (xem Blade).
    const applet = new GGBApplet(params, true);
    // inject() nhận ID chuỗi (cách dùng chính thức, tài liệu GeoGebra) — không truyền thẳng node DOM.
    applet.inject(canvasHost.id);
}
