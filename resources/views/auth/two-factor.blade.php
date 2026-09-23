@extends('layouts.guest')

@section('title', 'Xác thực 2 bước — TOÁN AI')

@section('content')
    <div class="card border mx-auto" style="max-width:26rem">
        <div class="card-body">
            <h1 class="h5 fw-bold mb-2">Xác thực 2 bước</h1>
            <p class="small text-secondary">Nhập mã 6 số từ ứng dụng xác thực, hoặc một mã dự phòng.</p>

            <form method="POST" action="{{ route('two-factor.verify') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="code">Mã xác thực</label>
                    <input id="code" name="code" type="text" inputmode="text" autocomplete="one-time-code" autofocus required
                           class="form-control form-control-lg @error('code') is-invalid @enderror" maxlength="32">
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary btn-touch w-100">Xác nhận</button>
            </form>

            <a href="{{ route('login') }}" class="d-block text-center small mt-3">Quay lại đăng nhập</a>
        </div>
    </div>
@endsection
