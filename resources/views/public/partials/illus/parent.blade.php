{{-- Minh hoạ: phụ huynh xem báo cáo của con trên điện thoại. --}}
<svg viewBox="0 0 320 180" role="img" aria-label="Phụ huynh xem báo cáo học tập của con" focusable="false">
    {{-- Điện thoại --}}
    <rect x="108" y="16" width="104" height="150" rx="16" fill="#1e293b"/>
    <rect x="115" y="26" width="90" height="130" rx="10" fill="#fff"/>
    <rect x="146" y="20" width="28" height="4" rx="2" fill="#475569"/>

    {{-- Tiêu đề báo cáo --}}
    <rect x="124" y="36" width="52" height="7" rx="3.5" fill="#1e293b"/>
    <rect x="124" y="48" width="36" height="5" rx="2.5" fill="#cbd5e1"/>

    {{-- Biểu đồ cột tiến bộ --}}
    @foreach ([[0, 22], [1, 34], [2, 28], [3, 46], [4, 58]] as [$i, $h])
        <rect x="{{ 126 + $i * 16 }}" y="{{ 122 - $h }}" width="10" height="{{ $h }}" rx="4"
              fill="{{ $i === 4 ? '#16a34a' : '#93c5fd' }}"/>
    @endforeach
    <rect x="124" y="126" width="72" height="4" rx="2" fill="#e2e8f0"/>

    {{-- Thẻ điểm trung bình --}}
    <rect x="124" y="136" width="72" height="14" rx="7" fill="#dcfce7"/>
    <text x="160" y="146" text-anchor="middle" font-size="9" font-weight="700" fill="#15803d">Điểm TB 8.1</text>

    {{-- Thẻ nổi: liên kết con + thông báo --}}
    <g>
        <rect x="18" y="52" width="86" height="34" rx="10" fill="#fff7ed" stroke="#fed7aa"/>
        <circle cx="36" cy="69" r="9" fill="#f97316"/>
        <text x="36" y="73" text-anchor="middle" font-size="10" font-weight="700" fill="#fff">👪</text>
        <rect x="50" y="62" width="44" height="5" rx="2.5" fill="#fdba74"/>
        <rect x="50" y="72" width="30" height="5" rx="2.5" fill="#fed7aa"/>
    </g>
    <g>
        <rect x="216" y="96" width="86" height="34" rx="10" fill="#eff6ff" stroke="#bfdbfe"/>
        <circle cx="234" cy="113" r="9" fill="#2563eb"/>
        <text x="234" y="117" text-anchor="middle" font-size="10" font-weight="700" fill="#fff">✓</text>
        <rect x="248" y="106" width="44" height="5" rx="2.5" fill="#93c5fd"/>
        <rect x="248" y="116" width="30" height="5" rx="2.5" fill="#bfdbfe"/>
    </g>
</svg>
