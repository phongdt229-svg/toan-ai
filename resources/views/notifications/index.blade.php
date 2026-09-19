@php
    // Trang dùng chung 4 vai trò — chọn layout portal giống hệt cách User::homeRoute() chọn route.
    $portal = match (true) {
        auth()->user()->isAdmin() => 'admin',
        auth()->user()->isTeacher() => 'teacher',
        auth()->user()->isParent() => 'parent',
        default => 'student',
    };
@endphp

@extends('layouts.app', ['portal' => $portal])

@section('title', 'Thông báo — TOÁN AI')
@section('page_title', 'Thông báo')

@section('content')
    @if ($notifications->where('read_at', null)->isNotEmpty())
        <form method="POST" action="{{ route('notifications.read_all') }}" class="mb-3 text-end">
            @csrf
            <button class="btn btn-sm btn-outline-primary">Đánh dấu tất cả đã đọc</button>
        </form>
    @endif

    <div class="card border">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $n)
                <a href="{{ route('notifications.open', $n) }}"
                   class="list-group-item list-group-item-action d-flex gap-3 py-3 {{ $n->read_at ? '' : 'bg-light' }}">
                    <i class="bi {{ $n->data['icon'] ?? 'bi-bell' }} text-primary fs-5 mt-1"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $n->data['title'] }}</div>
                        <div class="text-secondary small">{{ $n->data['message'] }}</div>
                        <div class="text-secondary small mt-1">{{ $n->created_at->format('H:i d/m/Y') }}</div>
                    </div>
                    @unless ($n->read_at)
                        <span class="badge bg-primary align-self-start">Mới</span>
                    @endunless
                </a>
            @empty
                <div class="text-secondary text-center py-5">Chưa có thông báo nào.</div>
            @endforelse
        </div>
    </div>

    <x-pagination :paginator="$notifications" label="thông báo" />
@endsection
