@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Lớp của tôi — TOÁN AI')
@section('page_title', 'Lớp của tôi')

@section('content')
    <div class="card border border-primary mb-4">
        <div class="card-body">
            <h2 class="h6 fw-bold mb-2">Tham gia lớp</h2>
            <p class="text-secondary small">Nhập mã 6 ký tự giáo viên đưa cho bạn.</p>

            <form method="POST" action="{{ route('student.classes.join') }}" class="d-flex gap-2">
                @csrf
                <input type="text" name="code" value="{{ old('code') }}" maxlength="12" required
                       autocomplete="off" autocapitalize="characters" placeholder="VD: K7M2QX"
                       class="form-control form-control-lg text-uppercase font-monospace @error('code') is-invalid @enderror"
                       aria-label="Mã lớp">
                <button class="btn btn-primary btn-lg flex-shrink-0">Tham gia</button>
            </form>
            @error('code') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
    </div>

    <h2 class="h6 fw-bold mb-2">Các lớp đang học</h2>

    @forelse ($classes as $class)
        <div class="card border mb-2">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="feature-card__icon mb-0"><i class="bi bi-people"></i></div>
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ $class->name }}</div>
                    <div class="text-secondary small">{{ $class->grade->name }} · GV {{ $class->owner->name }}</div>
                </div>
            </div>
        </div>
    @empty
        <p class="text-secondary">Bạn chưa tham gia lớp nào.</p>
    @endforelse
@endsection
