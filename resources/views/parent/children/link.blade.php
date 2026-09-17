@extends('layouts.app', ['portal' => 'parent'])

@section('title', 'Liên kết con — TOÁN AI')
@section('page_title', 'Liên kết con')

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card border">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold mb-2">Liên kết với tài khoản của con</h2>
                    <p class="text-secondary small mb-4">
                        Nhờ con mở mục <strong>Phụ huynh</strong> trong tài khoản học sinh. Ở đó có
                        <strong>mã 8 ký tự</strong>, <strong>link</strong> và <strong>mã QR</strong> — dùng cách nào cũng được.
                    </p>

                    <form method="POST" action="{{ route('parent.children.link.store') }}">
                        @csrf
                        <label for="link_code" class="form-label">Mã liên kết</label>
                        <input type="text" id="link_code" name="link_code" value="{{ old('link_code') }}"
                               maxlength="16" required autocomplete="off" autocapitalize="characters"
                               class="form-control form-control-lg text-uppercase font-monospace @error('link_code') is-invalid @enderror"
                               placeholder="VD: AB7KQ2MX">
                        @error('link_code') <div class="invalid-feedback">{{ $message }}</div> @enderror

                        <button class="btn btn-primary btn-lg w-100 btn-touch mt-3">Liên kết</button>
                    </form>

                    <hr class="my-4">

                    <div class="small text-secondary">
                        <div class="fw-semibold text-body mb-1"><i class="bi bi-qr-code-scan me-1"></i>Dùng mã QR</div>
                        Mở camera điện thoại quét mã QR trên màn hình của con, rồi làm theo hướng dẫn.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
