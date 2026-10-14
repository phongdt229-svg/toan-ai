/**
 * Công cụ vẽ hình 3D tương tác — chương "Hình học không gian" lớp 11–12.
 * Thuần công cụ tự học (không chấm điểm), nên toán tính thẳng ở đây, không cần server:
 * không có "đáp án đúng" phải giữ kín như một câu hỏi thi.
 *
 * Độ chính xác dựng hình/công thức ở đây được kiểm tay bằng cách so khớp số hiện ra với
 * máy tính cầm tay, vì PHPUnit không chạy được WebGL — xem PROJECT_PLAN.md mục công cụ này.
 */
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { CSS2DRenderer, CSS2DObject } from 'three/addons/renderers/CSS2DRenderer.js';

const root = document.querySelector('[data-solid-geometry]');
if (root) {
    const SQRT3 = Math.sqrt(3);

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

    // Kéo-thả nối điểm trực tiếp trên hình: mỗi điểm có một "tay cầm" (mesh hình cầu nhỏ, vô hình
    // trong mắt người dùng vì trùng vị trí nhãn) để bắt tia click/chạm — CSS2DObject (nhãn chữ) không
    // nằm trong scene WebGL nên raycaster không "thấy" được, phải có mesh thật để bắt trúng.
    let pointHandles = []; // {name, mesh}[] của hình đang hiện, dựng lại mỗi lần renderShape
    let dragFromPoint = null;
    let previewLine = null;
    let hoveredPoint = null;
    const raycaster = new THREE.Raycaster();

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
        if (dragFromPoint) setHandleOpacity(dragFromPoint, 0);
        dragFromPoint = null;
        controls.enabled = true;
        renderer.domElement.style.cursor = 'default';
    }

    // Capture: phải chạy TRƯỚC listener nội bộ của OrbitControls (gắn trên cùng phần tử) để có thể
    // chặn nó bắt đầu xoay camera khi người dùng đang thật sự bấm vào một điểm để kéo nối đường.
    renderer.domElement.addEventListener('pointerdown', (event) => {
        const handle = pickHandle(event);
        if (!handle) return;

        event.stopImmediatePropagation();
        controls.enabled = false;
        dragFromPoint = handle.name;
        setHandleOpacity(handle.name, 0.9);

        previewLine = new THREE.Line(
            new THREE.BufferGeometry().setFromPoints([handle.mesh.position, handle.mesh.position]),
            new THREE.LineDashedMaterial({ color: 0xf97316, dashSize: 0.15, gapSize: 0.1 }),
        );
        scene.add(previewLine);
    }, { capture: true });

    renderer.domElement.addEventListener('pointermove', (event) => {
        if (!dragFromPoint) {
            // Chỉ để lộ tay cầm (đang trong suốt) khi rê chuột qua — gợi ý "điểm này kéo được"
            // mà không làm rối mắt hình lúc không tương tác.
            const hover = pickHandle(event);
            if (hoveredPoint && hoveredPoint !== hover?.name) setHandleOpacity(hoveredPoint, 0);
            hoveredPoint = hover?.name ?? null;
            if (hover) setHandleOpacity(hover.name, 0.5);
            renderer.domElement.style.cursor = hover ? 'grab' : 'default';

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
        if (!dragFromPoint) return;

        const from = dragFromPoint;
        const target = pickHandle(event);
        endDrag();

        if (target && target.name !== from) {
            auxiliaryLines.push({ from, to: target.name });
            renderShape(currentShapeKey, readDims(currentShapeKey));
        }
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
        const handleRadius = Math.max(0.12, geometry.boundingSphere.radius * 0.05);
        const handleGeometry = new THREE.SphereGeometry(handleRadius, 12, 10);

        const pointMap = {};
        pointHandles = [];
        labels.forEach(([text, position]) => {
            pointMap[text] = position;

            const el = document.createElement('div');
            el.className = 'solid-geometry__point-label';
            el.textContent = text;
            const point = new CSS2DObject(el);
            point.position.set(...position);
            group.add(point);

            // Tay cầm bắt tia cho kéo-thả — trong suốt khi đứng yên, chỉ lộ ra (đổi màu) lúc rê chuột
            // qua hoặc đang kéo, xem setHandleHighlight().
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

        renderLinePicker(Object.keys(pointMap));
        renderLineList(pointMap);
    }

    function distance(a, b) {
        return Math.hypot(a[0] - b[0], a[1] - b[1], a[2] - b[2]);
    }

    function renderLinePicker(pointNames) {
        const fromSelect = root.querySelector('[data-line-from]');
        const toSelect = root.querySelector('[data-line-to]');
        const options = pointNames.map((name) => `<option value="${name}">${name}</option>`).join('');

        [fromSelect, toSelect].forEach((select, i) => {
            const previous = select.value;
            select.innerHTML = options;
            // Mặc định 2 ô khác điểm nhau (điểm đầu/cuối danh sách) cho dễ bấm "Thêm" ngay lần đầu.
            select.value = pointNames.includes(previous) ? previous : pointNames[i === 0 ? 0 : pointNames.length - 1];
        });
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
            input.addEventListener('input', () => renderShape(shapeKey, readDims(shapeKey)));
        });
    }

    function selectShape(shapeKey) {
        root.querySelectorAll('[data-shape]').forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.shape === shapeKey);
        });
        auxiliaryLines = []; // tên điểm của hình khác không còn khớp
        renderInputs(shapeKey);
        renderShape(shapeKey, readDims(shapeKey));
    }

    root.querySelectorAll('[data-shape]').forEach((btn) => {
        btn.addEventListener('click', () => selectShape(btn.dataset.shape));
    });

    root.querySelector('[data-add-line]').addEventListener('click', () => {
        const from = root.querySelector('[data-line-from]').value;
        const to = root.querySelector('[data-line-to]').value;
        if (!from || !to || from === to) return;

        auxiliaryLines.push({ from, to });
        renderShape(currentShapeKey, readDims(currentShapeKey));
    });

    resize();
    selectShape(root.querySelector('[data-shape]').dataset.shape);
    render();
}
