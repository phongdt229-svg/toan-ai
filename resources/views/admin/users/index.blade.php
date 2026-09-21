@extends('layouts.app', ['portal' => 'admin'])

@php
    use App\Http\Controllers\Admin\UserController;
    use App\Services\Auth\AccountDeletionService;

    $deletedView = $status === UserController::FILTER_DELETED;
@endphp

@push('head')
    @vite('resources/js/charts.js')
@endpush

@section('title', 'Người dùng — Quản trị TOÁN AI')
@section('page_title', 'Người dùng')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-4">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Theo vai trò</div>
                    <canvas data-chart-type="subscription-donut" data-chart='@json($roleChart)'
                            role="img" aria-label="Biểu đồ phân bố người dùng theo vai trò"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Tài khoản mới, 14 ngày gần đây</div>
                    <canvas data-chart-type="signups-daily" data-chart='@json($signupsDaily)'
                            role="img" aria-label="Biểu đồ số tài khoản mới đăng ký mỗi ngày"></canvas>
                </div>
            </div>
        </div>
    </div>

    <form method="GET" class="filter-bar">
        <input name="q" class="form-control" style="max-width:280px" placeholder="Tên, email, số điện thoại" value="{{ $search }}">
        <select name="role" class="form-select" style="max-width:170px">
            <option value="">Mọi vai trò</option>
            @foreach (UserController::ROLE_LABELS as $value => $label)
                <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status" class="form-select" style="max-width:170px">
            <option value="">Mọi trạng thái</option>
            @foreach (UserController::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
            <option value="{{ UserController::FILTER_DELETED }}" @selected($status === UserController::FILTER_DELETED)>
                Chờ xoá ({{ AccountDeletionService::GRACE_DAYS }} ngày)
            </option>
        </select>
        <button class="btn btn-outline-primary">Lọc</button>
    </form>

    <div class="table-meta">{{ number_format($users->total(), 0, ',', '.') }} người dùng</div>

    <div class="table-responsive">
        <table class="table table-sm align-middle small">
            <thead>
                <tr>
                    <th>Người dùng</th><th>Vai trò</th><th>Trạng thái</th><th>Lớp</th>
                    <th>{{ $deletedView ? 'Yêu cầu xoá' : 'Đăng nhập gần nhất' }}</th>
                    <th>{{ $deletedView ? 'Ẩn danh vào' : 'Ngày tạo' }}</th>
                    @if ($deletedView)<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td>
                            {{-- Ràng buộc route của trang chi tiết không nhận tài khoản đã xoá mềm. --}}
                            @if ($deletedView)
                                <span class="fw-semibold">{{ $u->name }}</span>
                            @else
                                <a href="{{ route('admin.users.show', $u) }}" class="fw-semibold text-decoration-none">{{ $u->name }}</a>
                            @endif
                            <div class="text-secondary">{{ $u->email }}</div>
                        </td>
                        <td>{{ $u->roles->map(fn ($r) => UserController::ROLE_LABELS[$r->name] ?? $r->name)->implode(', ') }}</td>
                        <td>
                            @if ($deletedView)
                                <span class="badge text-bg-dark">Chờ xoá</span>
                            @else
                                <span class="badge text-bg-{{ ['active' => 'success', 'pending' => 'warning', 'suspended' => 'danger', 'rejected' => 'secondary'][$u->status] ?? 'light' }}">
                                    {{ UserController::STATUS_LABELS[$u->status] ?? $u->status }}
                                </span>
                            @endif
                        </td>
                        <td>{{ $u->studentProfile?->grade?->name ?? '—' }}</td>
                        @if ($deletedView)
                            <td class="text-nowrap">{{ $u->deleted_at->format('d/m/Y') }}</td>
                            <td class="text-nowrap">
                                {{ $u->deleted_at->copy()->addDays(AccountDeletionService::GRACE_DAYS)->format('d/m/Y') }}
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.users.restore', $u) }}"
                                      onsubmit="return confirm('Khôi phục tài khoản {{ $u->name }}?')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">Khôi phục</button>
                                </form>
                            </td>
                        @else
                            <td>{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
                            <td class="text-nowrap">{{ $u->created_at->format('d/m/Y') }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $deletedView ? 7 : 6 }}" class="text-center text-secondary py-4">
                            {{ $deletedView ? 'Không có tài khoản nào đang chờ xoá.' : 'Không có người dùng phù hợp.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$users" label="người dùng" />
@endsection
