{{-- Dùng chung 4 portal — nhận $user (model đang đăng nhập) và $profileRoute (route đích của form). --}}
<div class="card border mb-3" style="max-width:36rem">
    <div class="card-body">
        <h3 class="h6 fw-bold mb-3">Hồ sơ</h3>

        {{-- Ảnh đại diện: form riêng vì gửi file (multipart), không lẫn với form tên/số điện thoại. --}}
        <div class="d-flex align-items-center gap-3 mb-3">
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="Ảnh đại diện" width="64" height="64" class="rounded-circle border" style="object-fit:cover">
            @else
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light border text-secondary" style="width:64px;height:64px"><i class="bi bi-person fs-3"></i></span>
            @endif
            <div class="flex-grow-1">
                <form method="POST" action="{{ route('account.avatar.store') }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
                    @csrf
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required
                           class="form-control form-control-sm @error('avatar') is-invalid @enderror" style="max-width:240px" aria-label="Chọn ảnh đại diện">
                    <button class="btn btn-outline-primary btn-sm">Tải lên</button>
                    @error('avatar')<div class="text-danger small w-100">{{ $message }}</div>@enderror
                </form>
                <div class="form-text">JPG, PNG hoặc WebP, tối đa 2 MB. Ảnh được cắt vuông và xoá thông tin vị trí.</div>
                @if ($user->avatar)
                    <form method="POST" action="{{ route('account.avatar.destroy') }}" class="mt-1" data-confirm="Xoá ảnh đại diện?" data-confirm-ok="Xoá">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-link btn-sm text-danger p-0">Xoá ảnh</button>
                    </form>
                @endif
            </div>
        </div>
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
                <label class="form-label" for="phone">Số điện thoại</label>
                <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone"
                       value="{{ old('phone', $user->phone) }}" placeholder="0912345678">
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <button class="btn btn-primary btn-touch">Lưu hồ sơ</button>
        </form>
    </div>
</div>
