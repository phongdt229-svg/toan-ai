@extends('layouts.app', ['portal' => 'admin'])

@php use App\Http\Controllers\Admin\UserController; @endphp

@section('title', 'Người dùng — Quản trị TOÁN AI')
@section('page_title', 'Người dùng')

@section('content')
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
        </select>
        <button class="btn btn-outline-primary">Lọc</button>
    </form>

    <div class="table-meta">{{ number_format($users->total(), 0, ',', '.') }} người dùng</div>

    <div class="table-responsive">
        <table class="table table-sm align-middle small">
            <thead>
                <tr><th>Người dùng</th><th>Vai trò</th><th>Trạng thái</th><th>Lớp</th><th>Đăng nhập gần nhất</th><th>Ngày tạo</th></tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td>
                            <a href="{{ route('admin.users.show', $u) }}" class="fw-semibold text-decoration-none">{{ $u->name }}</a>
                            <div class="text-secondary">{{ $u->email }}</div>
                        </td>
                        <td>{{ $u->roles->map(fn ($r) => UserController::ROLE_LABELS[$r->name] ?? $r->name)->implode(', ') }}</td>
                        <td>
                            <span class="badge text-bg-{{ ['active' => 'success', 'pending' => 'warning', 'suspended' => 'danger', 'rejected' => 'secondary'][$u->status] ?? 'light' }}">
                                {{ UserController::STATUS_LABELS[$u->status] ?? $u->status }}
                            </span>
                        </td>
                        <td>{{ $u->studentProfile?->grade?->name ?? '—' }}</td>
                        <td>{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
                        <td class="text-nowrap">{{ $u->created_at->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">Không có người dùng phù hợp.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
@endsection
