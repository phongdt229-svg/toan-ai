@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Audit log — Quản trị TOÁN AI')
@section('page_title', 'Audit log')

@section('content')
    <form method="GET" class="row g-2 mb-3 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label small mb-1" for="action">Hành động</label>
            <select id="action" name="action" class="form-select form-select-sm">
                <option value="">Tất cả</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>
                        {{ \App\Models\AuditLog::ACTION_LABELS[$action] ?? $action }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label small mb-1" for="actor">Email người thực hiện</label>
            <input id="actor" name="actor" class="form-control form-control-sm" value="{{ $filters['actor'] ?? '' }}">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="from">Từ ngày</label>
            <input id="from" name="from" type="date" class="form-control form-control-sm" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="to">Đến ngày</label>
            <input id="to" name="to" type="date" class="form-control form-control-sm" value="{{ $filters['to'] ?? '' }}">
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-sm btn-primary flex-grow-1">Lọc</button>
            <a href="{{ route('admin.audit-logs.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary" title="Xuất CSV theo bộ lọc">
                <i class="bi bi-download"></i>
            </a>
        </div>
    </form>

    <p class="small text-secondary">Nhật ký chỉ đọc — không sửa hay xoá được. Mỗi dòng bấm để xem giá trị trước/sau.</p>

    @forelse ($logs as $log)
        <details class="card border mb-2">
            <summary class="card-body py-2 small d-flex flex-wrap gap-2 align-items-center">
                <span class="text-secondary text-nowrap">{{ $log->created_at->format('H:i:s d/m/Y') }}</span>
                <span class="fw-semibold">{{ $log->actionLabel() }}</span>
                @if ($log->subjectLabel())<code class="small">{{ $log->subjectLabel() }}</code>@endif
                <span class="ms-auto text-secondary">
                    @if ($log->user)
                        <a href="{{ route('admin.users.show', $log->user_id) }}">{{ $log->user->name }}</a>
                    @else
                        Hệ thống
                    @endif
                    · {{ $log->ip_address ?? '—' }}
                </span>
            </summary>
            <div class="row g-0 border-top small">
                @foreach (['Trước' => $log->old_values, 'Sau' => $log->new_values] as $label => $values)
                    <div class="col-12 col-md-6 p-2">
                        <div class="text-secondary mb-1">{{ $label }}</div>
                        <pre class="bg-light p-2 m-0" style="white-space:pre-wrap">{{ $values ? json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—' }}</pre>
                    </div>
                @endforeach
                <div class="col-12 px-2 pb-2 text-secondary">{{ $log->action }} · {{ \Illuminate\Support\Str::limit($log->user_agent, 120) }}</div>
            </div>
        </details>
    @empty
        <div class="card border"><div class="card-body text-center text-secondary">Không có bản ghi phù hợp.</div></div>
    @endforelse

    <x-pagination :paginator="$logs" label="bản ghi" class="mt-3" />
@endsection
