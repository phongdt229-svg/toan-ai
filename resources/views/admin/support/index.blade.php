@extends('layouts.app', ['portal' => 'admin'])

@php use App\Models\SupportTicket; @endphp

@section('title', 'Hỗ trợ — Quản trị TOÁN AI')
@section('page_title', 'Yêu cầu hỗ trợ')

@section('content')
    <div class="row g-3 mb-3">
        @foreach ([
            ['Yêu cầu mới', $stats['new'], 'bi-inbox', $stats['new'] ? 'primary' : 'secondary'],
            ['Đang xử lý', $stats['in_progress'], 'bi-hourglass-split', 'warning'],
            ['Lỗi nội dung chưa xong', $stats['content_error'], 'bi-exclamation-triangle', $stats['content_error'] ? 'danger' : 'secondary'],
        ] as [$label, $value, $icon, $tone])
            <div class="col-12 col-sm-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="stat-card__label">{{ $label }}</div>
                        <i class="bi {{ $icon }} text-{{ $tone }}"></i>
                    </div>
                    <div class="stat-card__value text-{{ $tone }}">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <form method="GET" class="filter-bar">
        <input name="q" class="form-control" style="max-width:260px" placeholder="Mã, email, tiêu đề" value="{{ $search }}">
        <select name="type" class="form-select" style="max-width:190px">
            <option value="">Mọi loại</option>
            @foreach (SupportTicket::TYPE_LABELS as $value => $label)
                <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status" class="form-select" style="max-width:190px">
            <option value="">Mọi trạng thái</option>
            <option value="open" @selected($status === 'open')>Chưa xong</option>
            @foreach (SupportTicket::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-outline-primary">Lọc</button>
    </form>

    <div class="table-responsive">
        <table class="table table-sm align-middle small">
            <thead>
                <tr><th>Mã</th><th>Loại</th><th>Người gửi</th><th>Tiêu đề</th><th>Trạng thái</th><th>Gửi lúc</th></tr>
            </thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr>
                        <td><a href="{{ route('admin.support.show', $ticket) }}"><code>{{ $ticket->code }}</code></a></td>
                        <td>{{ $ticket->typeLabel() }}</td>
                        <td>
                            <div>{{ $ticket->name }}</div>
                            <div class="text-secondary">{{ $ticket->email }}</div>
                        </td>
                        <td>
                            <a href="{{ route('admin.support.show', $ticket) }}">{{ \Illuminate\Support\Str::limit($ticket->subject, 60) }}</a>
                        </td>
                        <td>
                            <span class="badge text-bg-{{ ['new' => 'primary', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'light border'][$ticket->status] }}">
                                {{ $ticket->statusLabel() }}
                            </span>
                        </td>
                        <td class="text-nowrap">{{ $ticket->created_at->format('H:i d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">Chưa có yêu cầu nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$tickets" label="yêu cầu" />
@endsection
