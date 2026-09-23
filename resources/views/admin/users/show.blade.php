@extends('layouts.app', ['portal' => 'admin'])

@php use App\Http\Controllers\Admin\UserController; @endphp

@section('title', $user->name . ' — Quản trị TOÁN AI')
@section('page_title', 'Chi tiết người dùng')

@section('content')
    <a href="{{ route('admin.users.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Người dùng</a>

    <div class="card border mt-2 mb-3">
        <div class="card-body d-flex flex-wrap gap-3 align-items-start">
            <div class="flex-grow-1">
                <h2 class="h5 fw-bold mb-1">{{ $user->name }}</h2>
                <div class="small text-secondary">{{ $user->email }} @if ($user->phone) · {{ $user->phone }} @endif</div>
                <div class="mt-2 d-flex flex-wrap gap-1">
                    @foreach ($user->roles as $r)
                        <span class="badge text-bg-light border">{{ UserController::ROLE_LABELS[$r->name] ?? $r->name }}</span>
                    @endforeach
                    <span class="badge text-bg-{{ ['active' => 'success', 'pending' => 'warning', 'suspended' => 'danger', 'rejected' => 'secondary'][$user->status] ?? 'light' }}">
                        {{ UserController::STATUS_LABELS[$user->status] ?? $user->status }}
                    </span>
                </div>
                <div class="small text-secondary mt-2">
                    Tạo {{ $user->created_at->format('d/m/Y') }} · Đăng nhập gần nhất {{ $user->last_login_at?->format('H:i d/m/Y') ?? '—' }}
                </div>
            </div>

            <div>
                @if ($user->status === 'suspended')
                    <form method="POST" action="{{ route('admin.users.reactivate', $user) }}">
                        @csrf
                        <button class="btn btn-success"><i class="bi bi-unlock me-1"></i>Mở khoá</button>
                    </form>
                @elseif (! $user->is(auth()->user()))
                    <button class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#suspend-form">
                        <i class="bi bi-lock me-1"></i>Khoá tài khoản
                    </button>
                @endif
                @if (! $user->is(auth()->user()) && ! $user->hasRole('admin') && $user->status !== 'suspended')
                    <form method="POST" action="{{ route('admin.users.impersonate', $user) }}" class="d-inline"
                          data-confirm="Đăng nhập hộ {{ $user->name }}? Thao tác được ghi vào audit log." data-confirm-ok="Đăng nhập hộ">
                        @csrf
                        <button class="btn btn-outline-primary ms-1"><i class="bi bi-person-badge me-1"></i>Đăng nhập hộ</button>
                    </form>
                @endif
                <button class="btn btn-outline-secondary ms-1" data-bs-toggle="collapse" data-bs-target="#email-form">
                    <i class="bi bi-envelope-at me-1"></i>Đổi email
                </button>
                @if ($user->isTeacher() && $user->isPending())
                    <a href="{{ route('admin.teachers.pending') }}" class="btn btn-warning ms-1">Duyệt hồ sơ</a>
                @endif
            </div>
        </div>

        <div class="collapse" id="email-form">
            <form method="POST" action="{{ route('admin.users.email', $user) }}"
                  class="card-body border-top d-flex flex-column flex-sm-row gap-2"
                  data-confirm="Đổi email đăng nhập của {{ $user->name }}?" data-confirm-ok="Đổi email">
                @csrf
                <input name="email" type="email" class="form-control" maxlength="191" required
                       placeholder="Email mới" value="{{ old('email') }}">
                <button class="btn btn-primary flex-shrink-0">Xác nhận đổi</button>
            </form>
            <div class="px-3 pb-3 small text-secondary">
                Chỉ dùng khi người dùng không vào được hộp thư cũ nên không tự đổi được.
                Địa chỉ mới sẽ ở trạng thái <strong>chưa xác thực</strong> và nhận thư xác thực ngay —
                thao tác này ghi vào audit log.
            </div>
        </div>

        <div class="collapse" id="suspend-form">
            <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="card-body border-top d-flex flex-column flex-sm-row gap-2">
                @csrf
                <input name="reason" class="form-control" maxlength="191" placeholder="Lý do khoá (ghi vào audit log)" required>
                <button class="btn btn-danger flex-shrink-0">Xác nhận khoá</button>
            </form>
            <div class="px-3 pb-3 small text-secondary">Người dùng bị đăng xuất khỏi mọi thiết bị và không đăng nhập lại được cho tới khi mở khoá.</div>
        </div>
    </div>

    <div class="row g-3">
        @if ($student)
            <div class="col-12 col-lg-6">
                <div class="card border h-100"><div class="card-body small d-grid gap-2 align-content-start">
                    <div class="fw-semibold fs-6">Học tập</div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">Lớp</span><span>{{ $user->studentProfile?->grade?->name ?? '—' }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">Hoàn thành chương trình</span><span>{{ $student['curriculum_percent'] !== null ? $student['curriculum_percent'] . '%' : '—' }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">Điểm TB đề kiểm tra</span><span>{{ $student['average_score'] ?? '—' }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">Lớp học tham gia</span><span>{{ $student['classes']->pluck('name')->implode(', ') ?: '—' }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">Phụ huynh liên kết</span><span>{{ $student['parents']->pluck('name')->implode(', ') ?: '—' }}</span></div>
                </div></div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card border h-100"><div class="card-body small">
                    <div class="d-flex align-items-center mb-2">
                        <div class="fw-semibold fs-6">Gói học</div>
                        <a href="{{ route('admin.subscriptions.index', ['q' => $user->email]) }}" class="ms-auto">Quản lý</a>
                    </div>
                    <div class="mb-2">
                        Hiện tại: <strong>{{ $student['current']?->package->name ?? 'Free' }}</strong>
                        @if ($student['current']) (đến {{ $student['current']->ends_at->format('d/m/Y') }}) @endif
                    </div>
                    @foreach ($student['subscriptions'] as $sub)
                        <div class="d-flex justify-content-between border-top py-1">
                            <span>{{ $sub->package->name }}</span><span class="text-secondary">{{ $sub->statusLabel() }}</span>
                        </div>
                    @endforeach
                </div></div>
            </div>
        @endif

        @if ($children->isNotEmpty())
            <div class="col-12 col-lg-6">
                <div class="card border h-100"><div class="card-body small">
                    <div class="fw-semibold fs-6 mb-2">Con đã liên kết</div>
                    @foreach ($children as $child)
                        <div class="border-top py-1"><a href="{{ route('admin.users.show', $child) }}">{{ $child->name }}</a> · {{ $child->studentProfile?->grade?->name }}</div>
                    @endforeach
                </div></div>
            </div>
        @endif

        @if ($user->isTeacher())
            <div class="col-12 col-lg-6">
                <div class="card border h-100"><div class="card-body small">
                    <div class="fw-semibold fs-6 mb-2">Giảng dạy</div>
                    <div class="text-secondary mb-2">{{ $user->teacherProfile?->school ?? 'Chưa khai báo trường' }}</div>
                    @forelse ($teachingClasses as $class)
                        <div class="d-flex justify-content-between border-top py-1"><span>{{ $class->name }}</span><span>{{ $class->active_students_count }} học sinh</span></div>
                    @empty
                        <div class="text-secondary">Chưa có lớp.</div>
                    @endforelse
                </div></div>
            </div>
        @endif

        @if ($payments->isNotEmpty())
            <div class="col-12 col-lg-6">
                <div class="card border h-100"><div class="card-body small">
                    <div class="fw-semibold fs-6 mb-2">Thanh toán</div>
                    @foreach ($payments as $payment)
                        <div class="d-flex justify-content-between border-top py-1">
                            <a href="{{ route('admin.payments.show', $payment) }}"><code>{{ $payment->order_code }}</code></a>
                            <span>{{ $payment->amountLabel() }} · {{ $payment->statusLabel() }}</span>
                        </div>
                    @endforeach
                </div></div>
            </div>
        @endif

        <div class="col-12">
            <div class="card border"><div class="card-body small">
                <div class="d-flex align-items-center mb-2">
                    <div class="fw-semibold fs-6">Nhật ký liên quan</div>
                    <a href="{{ route('admin.audit-logs.index', ['actor' => $user->email]) }}" class="ms-auto">Xem tất cả thao tác của người này</a>
                </div>
                @forelse ($auditLogs as $log)
                    <div class="d-flex flex-wrap gap-2 border-top py-1">
                        <span class="text-secondary text-nowrap">{{ $log->created_at->format('H:i d/m/Y') }}</span>
                        <span class="fw-semibold">{{ $log->actionLabel() }}</span>
                        <span class="text-secondary">bởi {{ $log->user?->name ?? 'Hệ thống' }}</span>
                        @if (! empty($log->new_values['reason']))<span>— {{ $log->new_values['reason'] }}</span>@endif
                    </div>
                @empty
                    <div class="text-secondary">Chưa có.</div>
                @endforelse
            </div></div>
        </div>
    </div>
@endsection
