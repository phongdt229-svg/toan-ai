{{-- Minh hoạ: bảng lớp học của giáo viên. --}}
<svg viewBox="0 0 320 180" role="img" aria-label="Giáo viên soạn bài và theo dõi lớp" focusable="false">
    {{-- Bảng --}}
    <rect x="34" y="24" width="252" height="112" rx="10" fill="#065f46"/>
    <rect x="42" y="32" width="236" height="96" rx="6" fill="#047857"/>

    {{-- Công thức trên bảng --}}
    <rect x="58" y="48" width="58" height="7" rx="3.5" fill="#a7f3d0"/>
    <rect x="58" y="64" width="92" height="6" rx="3" fill="#6ee7b7" opacity=".85"/>
    <rect x="58" y="78" width="66" height="6" rx="3" fill="#6ee7b7" opacity=".6"/>
    <rect x="58" y="96" width="40" height="6" rx="3" fill="#a7f3d0" opacity=".5"/>

    {{-- Danh sách lớp: đã nộp / chưa nộp --}}
    <rect x="176" y="46" width="84" height="66" rx="8" fill="#ecfdf5"/>
    @foreach ([0, 1, 2] as $i)
        <circle cx="190" cy="{{ 60 + $i * 18 }}" r="6" fill="{{ $i === 2 ? '#f59e0b' : '#16a34a' }}"/>
        <rect x="202" y="{{ 56 + $i * 18 }}" width="{{ 46 - $i * 8 }}" height="6" rx="3" fill="#a7f3d0"/>
    @endforeach

    {{-- Chân bảng + bút --}}
    <rect x="150" y="136" width="20" height="26" rx="4" fill="#94a3b8"/>
    <rect x="120" y="158" width="80" height="8" rx="4" fill="#cbd5e1"/>
    <rect x="236" y="126" width="34" height="8" rx="4" fill="#f8fafc"/>
    <rect x="262" y="126" width="12" height="8" rx="3" fill="#2563eb"/>
</svg>
