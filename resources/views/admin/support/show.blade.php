@extends('layouts.app', ['portal' => 'admin'])

@php use App\Models\SupportTicket; @endphp

@section('title', $ticket->code . ' — Quản trị TOÁN AI')
@section('page_title', 'Chi tiết yêu cầu')

@section('content')
    <a href="{{ route('admin.support.index') }}" class="small text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>Danh sách yêu cầu
    </a>

    <div class="row g-3 mt-1">
        <div class="col-12 col-lg-8">
            <div class="card border">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <code>{{ $ticket->code }}</code>
                        <span class="badge text-bg-light border">{{ $ticket->typeLabel() }}</span>
                        <span class="badge text-bg-{{ ['new' => 'primary', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'light border'][$ticket->status] }}">
                            {{ $ticket->statusLabel() }}
                        </span>
                        <span class="text-secondary small ms-auto">{{ $ticket->created_at->format('H:i d/m/Y') }}</span>
                    </div>

                    <h2 class="h5 fw-bold">{{ $ticket->subject }}</h2>
                    <p class="mb-0" style="white-space:pre-wrap">{{ $ticket->message }}</p>
                </div>
            </div>

            <div class="card border mt-3">
                <div class="card-body">
                    <h3 class="h6 fw-bold mb-3">Xử lý</h3>

                    <form method="POST" action="{{ route('admin.support.update', $ticket) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label" for="status">Trạng thái</label>
                            <select id="status" name="status" class="form-select" style="max-width:240px">
                                @foreach (SupportTicket::STATUS_LABELS as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $ticket->status) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="admin_note">Ghi chú nội bộ</label>
                            <textarea id="admin_note" name="admin_note" rows="3" class="form-control"
                                      placeholder="Đã trả lời email ngày…, đã sửa câu hỏi #123…">{{ old('admin_note', $ticket->admin_note) }}</textarea>
                            <div class="form-text">Người gửi không nhìn thấy ghi chú này.</div>
                        </div>

                        <button class="btn btn-primary">Lưu</button>
                        <a href="mailto:{{ $ticket->email }}?subject={{ rawurlencode('[' . $ticket->code . '] ' . $ticket->subject) }}"
                           class="btn btn-outline-primary ms-2">
                            <i class="bi bi-reply me-1"></i>Trả lời qua email
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border">
                <div class="card-body small d-grid gap-2">
                    <div class="fw-semibold fs-6 mb-1">Người gửi</div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">Tên</span><span>{{ $ticket->name }}</span></div>
                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">Email</span>
                        <a href="mailto:{{ $ticket->email }}">{{ $ticket->email }}</a>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">Tài khoản</span>
                        <span>
                            @if ($ticket->user)
                                <a href="{{ route('admin.users.show', $ticket->user) }}">{{ $ticket->user->name }}</a>
                            @else
                                Khách
                            @endif
                        </span>
                    </div>
                    @if ($ticket->context_url)
                        <div>
                            <div class="text-secondary">Trang liên quan</div>
                            <a href="{{ $ticket->context_url }}" class="text-break">{{ $ticket->context_url }}</a>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between"><span class="text-secondary">IP</span><span>{{ $ticket->ip_address ?? '—' }}</span></div>
                    @if ($ticket->handler)
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Người xử lý</span>
                            <span>{{ $ticket->handler->name }} · {{ $ticket->handled_at?->format('d/m/Y') }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
