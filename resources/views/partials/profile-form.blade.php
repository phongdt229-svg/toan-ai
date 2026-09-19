{{-- Dùng chung 4 portal — nhận $user (model đang đăng nhập) và $profileRoute (route đích của form). --}}
<div class="card border mb-3" style="max-width:36rem">
    <div class="card-body">
        <h3 class="h6 fw-bold mb-3">Hồ sơ</h3>
        <form method="POST" action="{{ $profileRoute }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label" for="name">Họ tên</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                       value="{{ old('name', $user->name) }}" required maxlength="100">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control" id="email" value="{{ $user->email }}" disabled>
                <div class="form-text">Không đổi được email tài khoản.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="phone">Số điện thoại</label>
                <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone"
                       value="{{ old('phone', $user->phone) }}" placeholder="0912345678">
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <button class="btn btn-primary btn-touch">Lưu hồ sơ</button>
        </form>
    </div>
</div>
