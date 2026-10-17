/**
 * Công cụ vẽ hình 3D tương tác — chương "Hình học không gian" lớp 11–12 (GH-01,
 * docs/SPEC_HINH_HOC_SOAN_BAI.md). Module THUẦN LOGIC, không tự mount gì — nơi dùng
 * (resources/js/solid-geometry.js cho trang độc lập; sau này lesson editor/trang xem bài học)
 * tự gọi `mountSolidGeometry(root, options)`.
 *
 * QUAN TRỌNG: file này KHÔNG được khai trong `vite.config.js → input` — nếu khai, Rollup build
 * production sẽ coi nó là "entry" độc lập và CẮT MẤT `export` vì không thấy ai import tĩnh trong
 * cùng entry đó (đã tự kiểm chứng: `Object.keys(module)` rỗng khi thử). Để nguyên làm module dùng
 * chung, Vite tự gộp thành 1 chunk chia sẻ giữa mọi entry có `import` nó.
 *
 * Thuần công cụ tự học (không chấm điểm), nên toán tính thẳng ở đây, không cần server: không có
 * "đáp án đúng" phải giữ kín như một câu hỏi thi.
 *
 * Độ chính xác dựng hình/công thức ở đây được kiểm tay bằng cách so khớp số hiện ra với
 * máy tính cầm tay, vì PHPUnit không chạy được WebGL — xem PROJECT_PLAN.md mục công cụ này.
 *
 * `root` phải chứa đúng cấu trúc markup của resources/views/student/solid_geometry/index.blade.php
 * (nút chọn hình [data-shape], khung canvas [data-solid-canvas], v.v.). `options.initialConfig` nạp
 * sẵn một hình đã lưu (dùng khi mở lại để sửa); `options.onChange(state)` gọi mỗi khi hình/số liệu/
 * đường/điểm/vùng đổi, để nơi gọi (vd trình soạn bài) lấy state hiện tại đem lưu.
 */
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { CSS2DRenderer, CSS2DObject } from 'three/addons/renderers/CSS2DRenderer.js';

const SQRT3 = Math.sqrt(3);

// Dịch cả hình về tâm hộp bao quanh gốc toạ độ để camera luôn thấy đủ (như các hình khác).
function centerPoints(points) {
    const lo = [0, 1, 2].map((i) => Math.min(...points.map((p) => p[i])));
    const hi = [0, 1, 2].map((i) => Math.max(...points.map((p) => p[i])));

    return points.map((p) => p.map((v, i) => v - (lo[i] + hi[i]) / 2));
}

function meshFrom(points, index) {
    const geometry = new THREE.BufferGeometry();
    geometry.setFromPoints(points.map((p) => new THREE.Vector3(...p)));
    geometry.setIndex(index);
    geometry.computeVertexNormals();

    return geometry;
}

// Mỗi hình: inputs (nhãn + giá trị mặc định + chặn dưới), build(dims) trả mesh + nhãn đỉnh +
// số liệu. Toạ độ Three.js: Y là chiều cao — mọi hình dựng CÂN GIỮA gốc để camera luôn thấy đủ.
const SHAPES = {
    box: {
        label: 'Hình hộp chữ nhật',
        inputs: [
            { key: 'a', label: 'Chiều dài a', value: 4 },
            { key: 'b', label: 'Chiều rộng b', value: 3 },
            { key: 'c', label: 'Chiều cao c', value: 2 },
        ],
        build({ a, b, c }) {
            const geometry = new THREE.BoxGeometry(a, c, b);
            const [hx, hy, hz] = [a / 2, c / 2, b / 2];
            const labels = [
                ['A', [-hx, -hy, hz]], ['B', [hx, -hy, hz]], ['C', [hx, -hy, -hz]], ['D', [-hx, -hy, -hz]],
                ["A'", [-hx, hy, hz]], ["B'", [hx, hy, hz]], ["C'", [hx, hy, -hz]], ["D'", [-hx, hy, -hz]],
            ];
            const volume = a * b * c;
            const total = 2 * (a * b + b * c + c * a);

            return {
                geometry,
                labels,
                results: [
                    { text: 'Thể tích V = a·b·c', value: volume, unit: 'đvtt' },
                    { text: 'Diện tích toàn phần Stp = 2(ab + bc + ca)', value: total, unit: 'đvdt' },
                ],
            };
        },
    },

    cube: {
        label: 'Hình lập phương',
        inputs: [{ key: 'a', label: 'Cạnh a', value: 3 }],
        build({ a }) {
            return SHAPES.box.build({ a, b: a, c: a });
        },
    },

    pyramid3: {
        label: 'Chóp tam giác đều',
        inputs: [
            { key: 'a', label: 'Cạnh đáy a', value: 4 },
            { key: 'h', label: 'Chiều cao h', value: 5 },
        ],
        build({ a, h }) {
            const R = a / SQRT3; // bán kính đường tròn ngoại tiếp đáy
            const r = a / (2 * SQRT3); // bán kính đường tròn nội tiếp đáy (trung đoạn đáy)
            const y0 = -h / 2;
            const A = [0, y0, R];
            const B = [-a / 2, y0, -r];
            const C = [a / 2, y0, -r];
            const S = [0, h / 2, 0];

            const geometry = new THREE.BufferGeometry();
            geometry.setFromPoints([A, B, C, S].map((p) => new THREE.Vector3(...p)));
            geometry.setIndex([0, 1, 2, 0, 1, 3, 1, 2, 3, 2, 0, 3]);
            geometry.computeVertexNormals();

            const baseArea = (a * a * SQRT3) / 4;
            const volume = (baseArea * h) / 3;
            const slant = Math.sqrt(h * h + r * r); // trung đoạn (đỉnh → trung điểm cạnh đáy)
            const lateral = (3 * a * slant) / 2;

            return {
                geometry,
                labels: [['A', A], ['B', B], ['C', C], ['S', S]],
                results: [
                    { text: 'Thể tích V = (1/3)·Sđáy·h', value: volume, unit: 'đvtt' },
                    { text: 'Diện tích xung quanh Sxq = (1/2)·Pđáy·trung đoạn', value: lateral, unit: 'đvdt' },
                    { text: 'Diện tích toàn phần Stp = Sxq + Sđáy', value: lateral + baseArea, unit: 'đvdt' },
                ],
            };
        },
    },

    pyramid4: {
        label: 'Chóp tứ giác đều',
        inputs: [
            { key: 'a', label: 'Cạnh đáy a', value: 4 },
            { key: 'h', label: 'Chiều cao h', value: 5 },
        ],
        build({ a, h }) {
            const y0 = -h / 2;
            const A = [-a / 2, y0, a / 2];
            const B = [a / 2, y0, a / 2];
            const C = [a / 2, y0, -a / 2];
            const D = [-a / 2, y0, -a / 2];
            const S = [0, h / 2, 0];

            const geometry = new THREE.BufferGeometry();
            geometry.setFromPoints([A, B, C, D, S].map((p) => new THREE.Vector3(...p)));
            geometry.setIndex([
                0, 1, 2, 0, 2, 3, // đáy (2 tam giác)
                0, 1, 4, 1, 2, 4, 2, 3, 4, 3, 0, 4, // 4 mặt bên
            ]);
            geometry.computeVertexNormals();

            const baseArea = a * a;
            const volume = (baseArea * h) / 3;
            const apothem = a / 2; // trung đoạn đáy (tâm → trung điểm cạnh)
            const slant = Math.sqrt(h * h + apothem * apothem);
            const lateral = 2 * a * slant;

            return {
                geometry,
                labels: [['A', A], ['B', B], ['C', C], ['D', D], ['S', S]],
                results: [
                    { text: 'Thể tích V = (1/3)·Sđáy·h', value: volume, unit: 'đvtt' },
                    { text: 'Diện tích xung quanh Sxq = 2·a·trung đoạn', value: lateral, unit: 'đvdt' },
                    { text: 'Diện tích toàn phần Stp = Sxq + Sđáy', value: lateral + baseArea, unit: 'đvdt' },
                ],
            };
        },
    },

    // Chóp S.ABCD đáy chữ nhật, SA ⊥ đáy — hình quen thuộc nhất của chương quan hệ vuông góc (lớp 11).
    pyramidRect: {
        label: 'Chóp S.ABCD (đáy chữ nhật, SA ⊥ đáy)',
        inputs: [
            { key: 'a', label: 'AB = a', value: 4 },
            { key: 'b', label: 'AD = b', value: 3 },
            { key: 'h', label: 'SA = h', value: 5 },
        ],
        build({ a, b, h }) {
            const pts = centerPoints([[0, 0, 0], [a, 0, 0], [a, 0, -b], [0, 0, -b], [0, h, 0]]);
            const [A, B, C, D, S] = pts;
            const geometry = meshFrom(pts, [0, 1, 2, 0, 2, 3, 0, 1, 4, 1, 2, 4, 2, 3, 4, 3, 0, 4]);

            return {
                geometry,
                labels: [['A', A], ['B', B], ['C', C], ['D', D], ['S', S]],
                results: [
                    { text: 'Thể tích V = (1/3)·AB·AD·SA', value: (a * b * h) / 3, unit: 'đvtt' },
                    { text: 'SC = √(a² + b² + h²)', value: Math.sqrt(a * a + b * b + h * h), unit: '' },
                ],
            };
        },
    },

    // Lăng trụ đứng ABC.A'B'C', đáy là tam giác vuông tại A.
    prismRight: {
        label: "Lăng trụ đứng ABC.A'B'C' (vuông tại A)",
        inputs: [
            { key: 'a', label: 'AB = a', value: 3 },
            { key: 'b', label: 'AC = b', value: 4 },
            { key: 'h', label: "AA' = h", value: 5 },
        ],
        build({ a, b, h }) {
            const pts = centerPoints([
                [0, 0, 0], [a, 0, 0], [0, 0, -b],
                [0, h, 0], [a, h, 0], [0, h, -b],
            ]);
            const [A, B, C, A1, B1, C1] = pts;
            const geometry = meshFrom(pts, [
                0, 1, 2, 3, 4, 5, // 2 đáy
                0, 1, 4, 0, 4, 3, 1, 2, 5, 1, 5, 4, 2, 0, 3, 2, 3, 5, // 3 mặt bên
            ]);

            return {
                geometry,
                labels: [['A', A], ['B', B], ['C', C], ["A'", A1], ["B'", B1], ["C'", C1]],
                results: [
                    { text: 'Thể tích V = (1/2)·AB·AC·AA\'', value: (a * b * h) / 2, unit: 'đvtt' },
                    { text: 'BC = √(a² + b²)', value: Math.hypot(a, b), unit: '' },
                ],
            };
        },
    },

    // Tứ diện vuông OABC: OA, OB, OC đôi một vuông góc.
    tetraRight: {
        label: 'Tứ diện vuông OABC',
        inputs: [
            { key: 'a', label: 'OA = a', value: 3 },
            { key: 'b', label: 'OB = b', value: 4 },
            { key: 'c', label: 'OC = c', value: 5 },
        ],
        build({ a, b, c }) {
            const pts = centerPoints([[0, 0, 0], [a, 0, 0], [0, 0, -b], [0, c, 0]]);
            const [O, A, B, C] = pts;
            const geometry = meshFrom(pts, [0, 1, 2, 0, 1, 3, 0, 2, 3, 1, 2, 3]);

            return {
                geometry,
                labels: [['O', O], ['A', A], ['B', B], ['C', C]],
                results: [
                    { text: 'Thể tích V = (1/6)·OA·OB·OC', value: (a * b * c) / 6, unit: 'đvtt' },
                ],
            };
        },
    },

    cylinder: {
        label: 'Hình trụ',
        inputs: [
            { key: 'r', label: 'Bán kính đáy r', value: 2 },
            { key: 'h', label: 'Chiều cao h', value: 5 },
        ],
        build({ r, h }) {
            const geometry = new THREE.CylinderGeometry(r, r, h, 48);
            const volume = Math.PI * r * r * h;
            const lateral = 2 * Math.PI * r * h;
            const bases = 2 * Math.PI * r * r;

            return {
                geometry,
                labels: [
                    ['O', [0, -h / 2, 0]], ["O'", [0, h / 2, 0]],
                    ['A', [r, -h / 2, 0]], ["A'", [r, h / 2, 0]],
                ],
                results: [
                    { text: 'Thể tích V = π·r²·h', value: volume, unit: 'đvtt' },
                    { text: 'Diện tích xung quanh Sxq = 2π·r·h', value: lateral, unit: 'đvdt' },
                    { text: 'Diện tích toàn phần Stp = Sxq + 2π·r²', value: lateral + bases, unit: 'đvdt' },
                ],
            };
        },
    },

    cone: {
        label: 'Hình nón',
        inputs: [
            { key: 'r', label: 'Bán kính đáy r', value: 2.5 },
            { key: 'h', label: 'Chiều cao h', value: 5 },
        ],
        build({ r, h }) {
            const geometry = new THREE.ConeGeometry(r, h, 48);
            const slant = Math.sqrt(r * r + h * h); // đường sinh
            const volume = (Math.PI * r * r * h) / 3;
            const lateral = Math.PI * r * slant;
            const base = Math.PI * r * r;

            return {
                geometry,
                labels: [
                    ['S', [0, h / 2, 0]], ['O', [0, -h / 2, 0]], ['A', [r, -h / 2, 0]],
                ],
                results: [
                    { text: 'Đường sinh l = √(r² + h²)', value: slant, unit: 'đv' },
                    { text: 'Thể tích V = (1/3)·π·r²·h', value: volume, unit: 'đvtt' },
                    { text: 'Diện tích xung quanh Sxq = π·r·l', value: lateral, unit: 'đvdt' },
                    { text: 'Diện tích toàn phần Stp = Sxq + π·r²', value: lateral + base, unit: 'đvdt' },
                ],
            };
        },
    },

    sphere: {
        label: 'Hình cầu',
        inputs: [{ key: 'r', label: 'Bán kính r', value: 3 }],
        build({ r }) {
            const geometry = new THREE.SphereGeometry(r, 32, 24);
            const volume = (4 / 3) * Math.PI * r * r * r;
            const area = 4 * Math.PI * r * r;

            return {
                geometry,
                labels: [['O', [0, 0, 0]]],
                results: [
                    { text: 'Thể tích V = (4/3)·π·r³', value: volume, unit: 'đvtt' },
                    { text: 'Diện tích mặt cầu S = 4π·r²', value: area, unit: 'đvdt' },
                ],
            };
        },
    },
};

export function mountSolidGeometry(root, options = {}) {
    const { initialConfig = null, onChange = null, readOnly = false } = options;

    // --- Khởi tạo scene (một lần) ------------------------------------------------
    const canvasHost = root.querySelector('[data-solid-canvas]');
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0xf8fafc);

    const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 1000);
    camera.position.set(6, 5, 9);

    const renderer = new THREE.WebGLRenderer({ antialias: true });
    canvasHost.appendChild(renderer.domElement);

    const labelRenderer = new CSS2DRenderer();
    labelRenderer.domElement.style.position = 'absolute';
    labelRenderer.domElement.style.top = '0';
    labelRenderer.domElement.style.pointerEvents = 'none';
    canvasHost.style.position = 'relative';
    canvasHost.appendChild(labelRenderer.domElement);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;

    scene.add(new THREE.AmbientLight(0xffffff, 0.7));
    const sun = new THREE.DirectionalLight(0xffffff, 0.9);
    sun.position.set(5, 8, 6);
    scene.add(sun);

    let currentGroup = null;
    let currentShapeKey = null;
    // {from, to}[] — tên điểm (khớp nhãn của hình hiện tại). Giữ khi đổi SỐ LIỆU (tính lại toạ độ
    // theo số mới), xoá sạch khi đổi sang HÌNH khác vì tên điểm không còn khớp.
    let auxiliaryLines = [];
    // {name, from, to}[] — điểm tự tạo = trung điểm của 2 điểm đã có (có thể là điểm tự tạo khác,
    // miễn điểm đó được thêm TRƯỚC trong mảng). Cùng quy tắc giữ/xoá như auxiliaryLines ở trên.
    let customPoints = [];
    // {points: string[]}[] — tên điểm theo đúng thứ tự quanh vùng (đa giác phẳng, vd thiết diện).
    let regions = [];

    // Toàn bộ thao tác (nối đường / trung điểm / đo vùng) đều bấm thẳng lên hình — mỗi điểm có một
    // "tay cầm" (mesh hình cầu nhỏ, vô hình trong mắt người dùng vì trùng vị trí nhãn) để bắt tia
    // click/chạm — CSS2DObject (nhãn chữ) không nằm trong scene WebGL nên raycaster không "thấy" được.
    let pointHandles = []; // {name, mesh}[] của hình đang hiện, dựng lại mỗi lần renderShape
    let activeMode = 'line'; // 'line' | 'point' | 'region'
    let pendingFirstPoint = null; // điểm đã bấm, đang chờ điểm thứ hai (chế độ line/point)
    let regionSelection = []; // các điểm đã bấm theo thứ tự, đang gom cho một vùng (chế độ region)
    let dragFromPoint = null;
    let previewLine = null;
    let hoveredPoint = null;
    let pointerDownAt = null;
    let pointerDownHandle = null;
    const CLICK_MOVE_THRESHOLD = 6; // px — dưới mức này tính là "bấm", không phải "kéo"
    const raycaster = new THREE.Raycaster();

    const MODE_HINTS = {
        line: 'Bấm (hoặc bấm giữ rồi kéo) một điểm trên hình, sau đó bấm điểm thứ hai để nối đường.',
        point: 'Bấm 2 điểm bất kỳ — điểm mới tạo ra nằm trên đoạn nối chúng, đúng theo tỉ lệ ở ô bên dưới (để 50% là trung điểm).',
        region: 'Bấm lần lượt các điểm quanh vùng cần đo (ít nhất 3 điểm), rồi bấm "Xong".',
    };

    /** Đọc tỉ lệ từ ô nhập (0–100%), trả về số thập phân 0–1, mặc định 0.5 (trung điểm) nếu để trống/sai. */
    function readPointRatio() {
        const input = root.querySelector('[data-point-ratio]');
        const percent = Number(input.value);

        return Number.isFinite(percent) && percent >= 0 && percent <= 100 ? percent / 100 : 0.5;
    }

    function resize() {
        const width = canvasHost.clientWidth;
        const height = canvasHost.clientHeight;
        if (!width || !height) return;

        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        renderer.setSize(width, height);
        labelRenderer.setSize(width, height);
    }

    new ResizeObserver(resize).observe(canvasHost);

    function render() {
        // Khung bị gỡ khỏi trang (đóng hộp thoại, đổi trang) → dừng vòng lặp và nhả bộ nhớ WebGL.
        if (!root.isConnected) {
            renderer.dispose();

            return;
        }

        controls.update();
        renderer.render(scene, camera);
        labelRenderer.render(scene, camera);
        requestAnimationFrame(render);
    }

    function formatNumber(value) {
        return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(value);
    }

    // --- Kéo-thả nối điểm trực tiếp trên hình ------------------------------------
    function pointerNDC(event) {
        const rect = renderer.domElement.getBoundingClientRect();

        return new THREE.Vector2(
            ((event.clientX - rect.left) / rect.width) * 2 - 1,
            -((event.clientY - rect.top) / rect.height) * 2 + 1,
        );
    }

    function pickHandle(event) {
        // Chế độ chỉ xem: không có tay cầm nào bấm được → mọi thao tác nối/đo tự vô hiệu, chỉ còn xoay/zoom.
        if (readOnly) return null;

        raycaster.setFromCamera(pointerNDC(event), camera);
        const hit = raycaster.intersectObjects(pointHandles.map((h) => h.mesh), false)[0];

        return hit ? pointHandles.find((h) => h.mesh === hit.object) : null;
    }

    function setHandleOpacity(name, opacity) {
        const handle = pointHandles.find((h) => h.name === name);
        if (handle) handle.mesh.material.opacity = opacity;
    }

    function endDrag() {
        if (previewLine) {
            scene.remove(previewLine);
            previewLine.geometry.dispose();
            previewLine.material.dispose();
            previewLine = null;
        }
        dragFromPoint = null;
        controls.enabled = true;
        renderer.domElement.style.cursor = 'default';
    }

    function updateModeHint() {
        const hint = root.querySelector('[data-mode-hint]');
        if (activeMode !== 'region' && pendingFirstPoint) {
            const action = activeMode === 'line' ? 'nối đường' : 'tạo điểm';
            hint.textContent = `Đã chọn điểm ${pendingFirstPoint} — bấm điểm thứ hai để ${action}.`;
        } else {
            hint.textContent = MODE_HINTS[activeMode];
        }
    }

    function updateRegionProgress() {
        const box = root.querySelector('[data-region-progress]');
        box.classList.toggle('d-none', activeMode !== 'region' || regionSelection.length === 0);
        root.querySelector('[data-region-progress-list]').textContent = regionSelection.join(', ');
        root.querySelector('[data-region-finish]').disabled = regionSelection.length < 3;
    }

    /** Chốt một điểm vừa bấm (tap) hoặc vừa kéo xong — việc tiếp theo tuỳ chế độ đang bật. */
    function handlePointSelected(name) {
        if (activeMode === 'region') {
            if (!regionSelection.includes(name)) {
                regionSelection.push(name);
                setHandleOpacity(name, 0.9);
            }
            updateRegionProgress();

            return;
        }

        if (!pendingFirstPoint) {
            pendingFirstPoint = name;
            setHandleOpacity(name, 0.9);
            updateModeHint();

            return;
        }

        if (pendingFirstPoint === name) return; // bấm lại đúng điểm đang chờ — bỏ qua

        const first = pendingFirstPoint;
        setHandleOpacity(first, 0);
        pendingFirstPoint = null;

        if (activeMode === 'line') {
            auxiliaryLines.push({ from: first, to: name });
        } else {
            customPoints.push({
                name: suggestPointName(pointHandles.map((h) => h.name)),
                from: first,
                to: name,
                ratio: readPointRatio(),
            });
        }

        updateModeHint();
        renderShape(currentShapeKey, readDims(currentShapeKey));
    }

    function setActiveMode(mode) {
        // Dọn trạng thái dở dang của chế độ cũ trước khi đổi — không thì lẫn điểm "đang chờ" của
        // chế độ này sang chế độ khác, rất khó hiểu cho người dùng.
        if (pendingFirstPoint) setHandleOpacity(pendingFirstPoint, 0);
        pendingFirstPoint = null;
        regionSelection.forEach((name) => setHandleOpacity(name, 0));
        regionSelection = [];
        updateRegionProgress();

        activeMode = mode;
        root.querySelectorAll('[data-mode]').forEach((btn) => {
            const isActive = btn.dataset.mode === mode;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-pressed', String(isActive));
        });
        root.querySelector('[data-point-ratio-field]').classList.toggle('d-none', mode !== 'point');
        updateModeHint();
    }

    // Capture: phải chạy TRƯỚC listener nội bộ của OrbitControls (gắn trên cùng phần tử) để có thể
    // chặn nó bắt đầu xoay camera khi người dùng đang thật sự bấm vào một điểm để kéo nối đường.
    // Chỉ chế độ "Nối đường" mới cần preview kéo — 2 chế độ kia chỉ cần phát hiện cú bấm ở pointerup.
    renderer.domElement.addEventListener('pointerdown', (event) => {
        pointerDownAt = { x: event.clientX, y: event.clientY };
        pointerDownHandle = pickHandle(event);
        if (activeMode !== 'line' || !pointerDownHandle) return;

        event.stopImmediatePropagation();
        controls.enabled = false;
        dragFromPoint = pointerDownHandle.name;
        setHandleOpacity(pointerDownHandle.name, 0.9);

        previewLine = new THREE.Line(
            new THREE.BufferGeometry().setFromPoints([pointerDownHandle.mesh.position, pointerDownHandle.mesh.position]),
            new THREE.LineDashedMaterial({ color: 0xf97316, dashSize: 0.15, gapSize: 0.1 }),
        );
        scene.add(previewLine);
    }, { capture: true });

    renderer.domElement.addEventListener('pointermove', (event) => {
        if (!dragFromPoint) {
            // Chỉ để lộ tay cầm (đang trong suốt) khi rê chuột qua — gợi ý "điểm này bấm được"
            // mà không làm rối mắt hình lúc không tương tác. Điểm đang "chờ"/đã chọn vẫn giữ sáng.
            const hover = pickHandle(event);
            if (hoveredPoint && hoveredPoint !== hover?.name && hoveredPoint !== pendingFirstPoint
                && !regionSelection.includes(hoveredPoint)) {
                setHandleOpacity(hoveredPoint, 0);
            }
            hoveredPoint = hover?.name ?? null;
            if (hover) setHandleOpacity(hover.name, hover.name === pendingFirstPoint ? 0.9 : 0.5);
            renderer.domElement.style.cursor = hover ? 'pointer' : 'default';

            return;
        }

        const startHandle = pointHandles.find((h) => h.name === dragFromPoint);
        const hoverHandle = pickHandle(event);
        let endPosition;

        if (hoverHandle && hoverHandle.name !== dragFromPoint) {
            endPosition = hoverHandle.mesh.position;
            renderer.domElement.style.cursor = 'grab';
        } else {
            // Không rê trúng điểm nào khác — chiếu tia chuột lên mặt phẳng hướng camera đi qua điểm
            // bắt đầu, để đường xem trước vẫn bám theo chuột một cách tự nhiên trong không gian 3D.
            renderer.domElement.style.cursor = 'grabbing';
            const normal = new THREE.Vector3();
            camera.getWorldDirection(normal);
            const plane = new THREE.Plane().setFromNormalAndCoplanarPoint(normal, startHandle.mesh.position);
            const hit = new THREE.Vector3();
            raycaster.setFromCamera(pointerNDC(event), camera);
            endPosition = raycaster.ray.intersectPlane(plane, hit) || startHandle.mesh.position;
        }

        previewLine.geometry.setFromPoints([startHandle.mesh.position, endPosition]);
        previewLine.computeLineDistances();
    });

    window.addEventListener('pointerup', (event) => {
        const moved = pointerDownAt
            ? Math.hypot(event.clientX - pointerDownAt.x, event.clientY - pointerDownAt.y)
            : Infinity;
        // Ngón tay rê nhẹ lúc chạm vẫn phải tính là "bấm" — tay cầm nhỏ, chạm không đứng yên
        // tuyệt đối như chuột được. Chuột giữ mức chặt (6px) để không lẫn với kéo thật.
        const threshold = event.pointerType === 'touch' ? CLICK_MOVE_THRESHOLD * 2.5 : CLICK_MOVE_THRESHOLD;

        if (dragFromPoint) {
            const from = dragFromPoint;
            const target = pickHandle(event);
            endDrag();

            if (moved < threshold) {
                // Bấm-thả tại chỗ (không kéo đi đâu) — coi như một cú "bấm chọn điểm" bình thường.
                handlePointSelected(from);
            } else if (target && target.name !== from) {
                auxiliaryLines.push({ from, to: target.name });
                renderShape(currentShapeKey, readDims(currentShapeKey));
            } else {
                setHandleOpacity(from, 0);
            }
        } else if (moved < threshold && pointerDownHandle) {
            handlePointSelected(pointerDownHandle.name);
        }

        pointerDownAt = null;
        pointerDownHandle = null;
    });

    function renderShape(shapeKey, dims) {
        currentShapeKey = shapeKey;

        if (currentGroup) {
            scene.remove(currentGroup);
            currentGroup.traverse((obj) => {
                if (obj.geometry) obj.geometry.dispose();
                if (obj.material) obj.material.dispose();
                // CSS2DRenderer không tự gỡ <div> nhãn khỏi DOM khi object rời scene — tự xoá tay,
                // không thì nhãn hình cũ (A, B, C…) vẫn hiện chồng lên hình mới mỗi lần đổi hình.
                if (obj.isCSS2DObject) obj.element.remove();
            });
        }

        const { geometry, labels, results } = SHAPES[shapeKey].build(dims);
        const group = new THREE.Group();

        const mesh = new THREE.Mesh(geometry, new THREE.MeshStandardMaterial({
            color: 0x2563eb,
            transparent: true,
            opacity: 0.35,
            side: THREE.DoubleSide,
        }));
        group.add(mesh);

        const edges = new THREE.LineSegments(
            new THREE.EdgesGeometry(geometry),
            new THREE.LineBasicMaterial({ color: 0x0f172a }),
        );
        group.add(edges);

        geometry.computeBoundingSphere();
        // Lớn hơn ngưỡng cũ (0.12 → 0.2) — bán kính tính theo % hình để chạm được trên màn hình nhỏ,
        // tay cầm vẫn vô hình khi đứng yên (opacity: 0 ở addPoint()) nên không làm rối mắt hình.
        const handleRadius = Math.max(0.2, geometry.boundingSphere.radius * 0.06);
        const handleGeometry = new THREE.SphereGeometry(handleRadius, 12, 10);

        // Nhãn chữ + tay cầm bắt tia cho một điểm — dùng chung cho điểm gốc của hình VÀ điểm tự tạo
        // (trung điểm), để cả hai loại đều kéo-thả/nối đường/làm đỉnh vùng được như nhau.
        function addPoint(text, position, isCustom) {
            pointMap[text] = position;

            const el = document.createElement('div');
            el.className = 'solid-geometry__point-label'
                + (isCustom ? ' solid-geometry__point-label--custom' : '');
            el.textContent = text;
            const point = new CSS2DObject(el);
            point.position.set(...position);
            group.add(point);

            // Trong suốt khi đứng yên, chỉ lộ ra (đổi màu) lúc rê chuột qua hoặc đang kéo.
            const handle = new THREE.Mesh(handleGeometry, new THREE.MeshBasicMaterial({
                color: 0xf97316,
                transparent: true,
                opacity: 0,
                depthTest: false,
            }));
            handle.position.set(...position);
            handle.userData.pointName = text;
            group.add(handle);
            pointHandles.push({ name: text, mesh: handle });
        }

        const pointMap = {};
        pointHandles = [];
        labels.forEach(([text, position]) => addPoint(text, position, false));

        // Điểm tự tạo (nằm trên đoạn from-to theo tỉ lệ ratio, 0.5 = trung điểm) — xử lý THEO THỨ TỰ
        // đã thêm nên điểm sau được phép dựa vào một điểm tự tạo trước đó. Thiếu from/to (đổi hình,
        // hoặc điểm gốc đã bị xoá) thì bỏ qua — mọi thứ dựa vào điểm bị bỏ qua này (đường, vùng, điểm
        // khác) cũng tự động biến mất theo, không cần dọn tay từng chỗ.
        customPoints.forEach(({ name, from, to, ratio }) => {
            const a = pointMap[from];
            const b = pointMap[to];
            if (!a || !b) return;

            addPoint(name, a.map((v, i) => v + ratio * (b[i] - v)), true);
        });

        // Đoạn tự vẽ (nối đường chéo, hạ đường cao…) — màu cam để phân biệt cạnh hình (đen).
        auxiliaryLines.forEach(({ from, to }) => {
            const a = pointMap[from];
            const b = pointMap[to];
            if (!a || !b) return; // phòng khi đổi hình mà lỡ chưa dọn danh sách

            const line = new THREE.Line(
                new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(...a), new THREE.Vector3(...b)]),
                new THREE.LineBasicMaterial({ color: 0xf97316 }),
            );
            group.add(line);

            const midpoint = a.map((v, i) => (v + b[i]) / 2);
            const el = document.createElement('div');
            el.className = 'solid-geometry__point-label solid-geometry__point-label--line';
            el.textContent = `${from}${to}`;
            const label = new CSS2DObject(el);
            label.position.set(...midpoint);
            group.add(label);
        });

        // Vùng/mặt cắt (≥ 3 điểm theo thứ tự quanh vùng) — tô màu xanh lục, tam giác hoá hình quạt từ
        // điểm đầu (đúng với vùng LỒI — đa số thiết diện trong bài thật là tam giác/tứ giác lồi).
        regions.forEach((region) => {
            const positions = region.points.map((name) => pointMap[name]);
            if (positions.length < 3 || positions.some((p) => !p)) return;

            const vertices = positions.map((p) => new THREE.Vector3(...p));
            const regionGeometry = new THREE.BufferGeometry().setFromPoints(vertices);
            const indices = [];
            for (let i = 1; i < vertices.length - 1; i++) indices.push(0, i, i + 1);
            regionGeometry.setIndex(indices);
            regionGeometry.computeVertexNormals();

            group.add(new THREE.Mesh(regionGeometry, new THREE.MeshBasicMaterial({
                color: 0x16a34a,
                transparent: true,
                opacity: 0.35,
                side: THREE.DoubleSide,
                depthTest: false,
            })));
            group.add(new THREE.LineLoop(
                new THREE.BufferGeometry().setFromPoints(vertices),
                new THREE.LineBasicMaterial({ color: 0x16a34a }),
            ));
        });

        // Vẽ lại xong, dựng lại toàn bộ tay cầm mới — phục hồi trạng thái "đang chọn" (nếu có) vì
        // đổi SỐ LIỆU (không phải đổi hình) vẫn giữ nguyên điểm đang chờ/vùng đang gom dở.
        if (pendingFirstPoint) setHandleOpacity(pendingFirstPoint, 0.9);
        regionSelection.forEach((name) => setHandleOpacity(name, 0.9));

        scene.add(group);
        currentGroup = group;

        const resultsHost = root.querySelector('[data-solid-results]');
        resultsHost.innerHTML = '';
        results.forEach(({ text, value, unit }) => {
            const row = document.createElement('div');
            row.className = 'solid-geometry__result';
            row.innerHTML = `<div class="small text-secondary">${text}</div>
                <div class="fw-bold">${formatNumber(value)} ${unit}</div>`;
            resultsHost.appendChild(row);
        });

        renderLineList(pointMap);
        renderPointList(pointMap);
        renderRegionList(pointMap);

        const hasAnything = auxiliaryLines.length > 0 || customPoints.length > 0 || regions.length > 0;
        root.querySelector('[data-clear-all]').classList.toggle('d-none', !hasAnything);

        // GH-01: nơi gọi (vd trình soạn bài) lấy state hiện tại đem lưu — snapshot thuần dữ liệu,
        // không kèm object Three.js nào, an toàn để JSON.stringify/nhúng vào content bài học.
        if (onChange) {
            onChange({
                shape: shapeKey,
                dims: { ...dims },
                lines: auxiliaryLines.map((l) => ({ ...l })),
                points: customPoints.map((p) => ({ ...p })),
                regions: regions.map((r) => ({ points: [...r.points] })),
            });
        }
    }

    /** Chu vi đa giác phẳng = tổng cạnh liên tiếp, khép kín về điểm đầu. */
    function polygonPerimeter(vertices) {
        let perimeter = 0;
        for (let i = 0; i < vertices.length; i++) {
            perimeter += vertices[i].distanceTo(vertices[(i + 1) % vertices.length]);
        }

        return perimeter;
    }

    /**
     * Diện tích đa giác phẳng trong không gian 3D — công thức Newell: cho đúng kết quả dù mặt
     * phẳng nằm nghiêng bất kỳ, miễn các điểm thật sự đồng phẳng và được nhập ĐÚNG THỨ TỰ quanh vùng.
     */
    function polygonArea(vertices) {
        const normal = new THREE.Vector3();
        for (let i = 0; i < vertices.length; i++) {
            const current = vertices[i];
            const next = vertices[(i + 1) % vertices.length];
            normal.x += (current.y - next.y) * (current.z + next.z);
            normal.y += (current.z - next.z) * (current.x + next.x);
            normal.z += (current.x - next.x) * (current.y + next.y);
        }

        return normal.length() / 2;
    }

    function distance(a, b) {
        return Math.hypot(a[0] - b[0], a[1] - b[1], a[2] - b[2]);
    }

    /** Chữ cái gợi ý cho điểm mới — bỏ qua tên đã dùng, tránh trùng với đỉnh gốc (A, B, S…). */
    function suggestPointName(usedNames) {
        const letters = 'MNPQRTUVWXYZHKL';
        for (const letter of letters) {
            if (!usedNames.includes(letter)) return letter;
        }

        return '';
    }

    function renderLineList(pointMap) {
        const host = root.querySelector('[data-solid-line-list]');
        host.innerHTML = '';

        auxiliaryLines.forEach((line, index) => {
            const a = pointMap[line.from];
            const b = pointMap[line.to];
            if (!a || !b) return;

            const row = document.createElement('div');
            row.className = 'd-flex align-items-center justify-content-between small py-1';
            row.innerHTML = `
                <span>${line.from}${line.to} = ${formatNumber(distance(a, b))} đv</span>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove-line="${index}">Xoá</button>
            `;
            host.appendChild(row);
        });

        host.querySelectorAll('[data-remove-line]').forEach((btn) => {
            btn.addEventListener('click', () => {
                auxiliaryLines.splice(Number(btn.dataset.removeLine), 1);
                renderShape(currentShapeKey, readDims(currentShapeKey));
            });
        });
    }

    function renderPointList(pointMap) {
        const host = root.querySelector('[data-solid-point-list]');
        host.innerHTML = '';

        customPoints.forEach((point, index) => {
            if (!pointMap[point.name]) return; // from/to không còn tồn tại — điểm này đang bị bỏ qua

            const percent = Math.round(point.ratio * 100);
            const description = percent === 50
                ? `trung điểm ${point.from}${point.to}`
                : `trên ${point.from}${point.to}, cách ${point.from} ${percent}% đoạn`;

            const row = document.createElement('div');
            row.className = 'd-flex align-items-center justify-content-between small py-1';
            row.innerHTML = `
                <span>${point.name} = ${description}</span>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove-point="${index}">Xoá</button>
            `;
            host.appendChild(row);
        });

        host.querySelectorAll('[data-remove-point]').forEach((btn) => {
            btn.addEventListener('click', () => {
                customPoints.splice(Number(btn.dataset.removePoint), 1);
                renderShape(currentShapeKey, readDims(currentShapeKey));
            });
        });
    }

    function renderRegionList(pointMap) {
        const host = root.querySelector('[data-solid-region-list]');
        host.innerHTML = '';

        regions.forEach((region, index) => {
            const positions = region.points.map((name) => pointMap[name]);
            if (positions.length < 3 || positions.some((p) => !p)) return;

            const vertices = positions.map((p) => new THREE.Vector3(...p));
            const row = document.createElement('div');
            row.className = 'd-flex align-items-center justify-content-between small py-1';
            row.innerHTML = `
                <span>
                    Vùng ${region.points.join('')}: chu vi ${formatNumber(polygonPerimeter(vertices))} đv,
                    diện tích ${formatNumber(polygonArea(vertices))} đvdt
                </span>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove-region="${index}">Xoá</button>
            `;
            host.appendChild(row);
        });

        host.querySelectorAll('[data-remove-region]').forEach((btn) => {
            btn.addEventListener('click', () => {
                regions.splice(Number(btn.dataset.removeRegion), 1);
                renderShape(currentShapeKey, readDims(currentShapeKey));
            });
        });
    }

    function readDims(shapeKey) {
        const dims = {};
        SHAPES[shapeKey].inputs.forEach(({ key }) => {
            const input = root.querySelector(`[data-dim="${key}"]`);
            dims[key] = Math.max(0.1, Number(input.value) || 0.1);
        });

        return dims;
    }

    function renderInputs(shapeKey, dimsOverride) {
        const host = root.querySelector('[data-solid-inputs]');
        host.innerHTML = '';

        SHAPES[shapeKey].inputs.forEach(({ key, label, value }) => {
            const wrap = document.createElement('div');
            wrap.className = 'mb-2';
            const startValue = dimsOverride?.[key] ?? value;
            wrap.innerHTML = `
                <label class="form-label small mb-1">${label}</label>
                <input type="number" min="0.1" step="0.1" value="${startValue}"
                       data-dim="${key}" class="form-control form-control-sm">
            `;
            host.appendChild(wrap);
        });

        host.querySelectorAll('input').forEach((input) => {
            input.addEventListener('input', () => renderShape(shapeKey, readDims(shapeKey)));
        });
    }

    function selectShape(shapeKey, dimsOverride) {
        root.querySelectorAll('[data-shape]').forEach((btn) => {
            const isActive = btn.dataset.shape === shapeKey;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-pressed', String(isActive));
        });
        // Tên điểm của hình khác không còn khớp — xoá sạch mọi thứ dựa trên tên điểm.
        auxiliaryLines = [];
        customPoints = [];
        regions = [];
        regionSelection = [];
        pendingFirstPoint = null;
        updateRegionProgress();
        renderInputs(shapeKey, dimsOverride);
        renderShape(shapeKey, readDims(shapeKey));
    }

    root.querySelectorAll('[data-shape]').forEach((btn) => {
        btn.addEventListener('click', () => selectShape(btn.dataset.shape));
    });

    root.querySelectorAll('[data-mode]').forEach((btn) => {
        btn.addEventListener('click', () => setActiveMode(btn.dataset.mode));
    });

    root.querySelector('[data-region-finish]').addEventListener('click', () => {
        if (regionSelection.length < 3) return;

        regions.push({ points: [...regionSelection] });
        regionSelection.forEach((name) => setHandleOpacity(name, 0));
        regionSelection = [];
        updateRegionProgress();
        renderShape(currentShapeKey, readDims(currentShapeKey));
    });

    root.querySelector('[data-region-cancel]').addEventListener('click', () => {
        regionSelection.forEach((name) => setHandleOpacity(name, 0));
        regionSelection = [];
        updateRegionProgress();
    });

    // Nút bấm JS thường (không phải <form>/<a>) nên gọi thẳng window.confirmDialog() theo đúng
    // cách confirm-dialog.js hướng dẫn, thay vì data-confirm (chỉ tự gắn cho form/link).
    root.querySelector('[data-clear-all]').addEventListener('click', async () => {
        const confirmed = await window.confirmDialog('Xoá hết đường, điểm và vùng đã thêm trên hình này?', {
            ok: 'Xoá tất cả',
            variant: 'danger',
        });
        if (!confirmed) return;

        auxiliaryLines = [];
        customPoints = [];
        regions = [];
        renderShape(currentShapeKey, readDims(currentShapeKey));
    });

    // Chỉ xem (trang học sinh): bỏ mọi khối chỉnh sửa VÀ khối "Kết quả" — trong bài tập, thể tích/diện
    // tích hiện sẵn có thể chính là đáp án. Canvas chiếm trọn chiều ngang.
    if (readOnly) {
        root.querySelectorAll('[data-edit-only]').forEach((el) => { el.style.display = 'none'; });
        root.querySelectorAll('[data-canvas-col]').forEach((el) => el.classList.replace('col-lg-8', 'col-lg-12'));
    }

    // Hình bắt đầu: initialConfig.shape nếu hợp lệ (mở lại hình đã lưu), không thì hình đầu tiên
    // trong danh sách nút — y hệt hành vi cũ khi không truyền options.
    const startShapeKey = (initialConfig?.shape && SHAPES[initialConfig.shape])
        ? initialConfig.shape
        : root.querySelector('[data-shape]').dataset.shape;

    resize();
    setActiveMode('line');
    selectShape(startShapeKey, initialConfig?.dims);

    if (initialConfig) {
        auxiliaryLines = initialConfig.lines ? initialConfig.lines.map((l) => ({ ...l })) : [];
        customPoints = initialConfig.points ? initialConfig.points.map((p) => ({ ...p })) : [];
        regions = initialConfig.regions ? initialConfig.regions.map((r) => ({ points: [...r.points] })) : [];
        renderShape(startShapeKey, readDims(startShapeKey));
    }

    render();
}
