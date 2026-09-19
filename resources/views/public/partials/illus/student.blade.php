{{-- Minh hoạ: góc học của học sinh (SVG nội tuyến, không phụ thuộc ảnh ngoài). --}}
<svg viewBox="0 0 320 180" role="img" aria-label="Học sinh học Toán trên máy tính" focusable="false">
    <defs>
        <linearGradient id="illus-student-screen" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#eff6ff"/>
            <stop offset="1" stop-color="#dbeafe"/>
        </linearGradient>
    </defs>

    {{-- Bàn --}}
    <rect x="24" y="140" width="272" height="10" rx="5" fill="#cbd5e1"/>

    {{-- Màn hình --}}
    <rect x="86" y="42" width="148" height="94" rx="10" fill="#1e293b"/>
    <rect x="94" y="50" width="132" height="78" rx="6" fill="url(#illus-student-screen)"/>
    <rect x="104" y="60" width="56" height="7" rx="3.5" fill="#93c5fd"/>
    <rect x="104" y="74" width="96" height="6" rx="3" fill="#bfdbfe"/>
    <rect x="104" y="86" width="72" height="6" rx="3" fill="#bfdbfe"/>
    <rect x="104" y="102" width="44" height="14" rx="7" fill="#2563eb"/>
    <rect x="156" y="102" width="44" height="14" rx="7" fill="#e2e8f0"/>
    <rect x="140" y="136" width="40" height="6" rx="3" fill="#94a3b8"/>

    {{-- Sách và bút --}}
    <rect x="30" y="118" width="46" height="22" rx="4" fill="#f97316"/>
    <rect x="34" y="112" width="46" height="22" rx="4" fill="#fb923c"/>
    <rect x="42" y="120" width="30" height="4" rx="2" fill="#fff" opacity=".8"/>
    <rect x="248" y="104" width="8" height="36" rx="4" fill="#16a34a"/>
    <path d="M248 104h8l-4-10z" fill="#065f46"/>

    {{-- Ký hiệu toán bay quanh --}}
    <g class="illus-float illus-float--1">
        <circle cx="60" cy="46" r="18" fill="#dbeafe"/>
        <text x="60" y="53" text-anchor="middle" font-size="20" font-weight="700" fill="#2563eb">π</text>
    </g>
    <g class="illus-float illus-float--2">
        <circle cx="262" cy="52" r="15" fill="#fef3c7"/>
        <text x="262" y="58" text-anchor="middle" font-size="17" font-weight="700" fill="#d97706">+</text>
    </g>
    <g class="illus-float illus-float--3">
        <circle cx="240" cy="22" r="11" fill="#dcfce7"/>
        <text x="240" y="27" text-anchor="middle" font-size="12" font-weight="700" fill="#16a34a">√</text>
    </g>
</svg>
