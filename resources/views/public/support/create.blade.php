@extends('layouts.public')

@section('title', 'Gửi yêu cầu hỗ trợ — TOÁN AI')
@section('meta_description', 'Gửi yêu cầu hỗ trợ hoặc báo lỗi nội dung cho đội ngũ TOÁN AI.')

@section('body')
    @include('public.partials.header')

    <main class="section">
        <div class="container" style="max-width:720px">
            @include('components.flash')

            <span class="section__eyebrow"><i class="bi bi-life-preserver"></i>Hỗ trợ</span>
            <h1 class="section__title mb-2">Chúng tôi <span class="hl">giúp gì</span> được cho bạn?</h1>
            <p class="section__subtitle mb-4">
                Gặp trục trặc khi học, thấy bài giảng hay câu hỏi bị sai, hoặc thanh toán chưa lên gói —
                hãy mô tả giúp chúng tôi. Phản hồi gửi qua email trong vòng 7 ngày làm việc.
            </p>

            <form method="POST" action="{{ route('support.store') }}" class="card border" novalidate>
                @csrf
                <input type="hidden" name="context_url" value="{{ $contextUrl }}">

                {{-- Bẫy bot: người dùng thật không thấy ô này. --}}
                <div class="d-none" aria-hidden="true">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label" for="type">Loại yêu cầu</label>
                        <select id="type" name="type" class="form-select @error('type') is-invalid @enderror">
                            @foreach (\App\Models\SupportTicket::TYPE_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    @guest
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="name">Họ tên</label>
                                <input id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" required autocomplete="name">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="email">Email nhận phản hồi</label>
                                <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror"
                                       value="{{ old('email') }}" required autocomplete="email">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    @else
                        <div class="alert alert-light border small mb-3">
                            <i class="bi bi-person-check me-1"></i>
                            Gửi với tài khoản <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}) —
                            chúng tôi sẽ trả lời vào email này.
                        </div>
                    @endguest

                    <div class="mb-3">
                        <label class="form-label" for="subject">Tiêu đề</label>
                        <input id="subject" name="subject" class="form-control @error('subject') is-invalid @enderror"
                               value="{{ old('subject') }}" maxlength="191" required
                               placeholder="Ví dụ: Câu hỏi về phân số có đáp án sai">
                        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="message">Mô tả chi tiết</label>
                        <textarea id="message" name="message" rows="6" required
                                  class="form-control @error('message') is-invalid @enderror"
                                  placeholder="Bạn đang ở trang nào, thao tác gì, kết quả ra sao? Nếu báo lỗi nội dung, ghi rõ tên bài học / câu hỏi.">{{ old('message') }}</textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Ít nhất 20 ký tự. Đừng gửi mật khẩu hay mã OTP trong nội dung này.</div>
                    </div>

                    {{-- Captcha: một phép tính, đáp án nằm trong session chứ không có trong HTML. --}}
                    <div class="mb-4">
                        <label class="form-label" for="captcha">
                            Xác nhận bạn không phải robot: <strong>{{ $captcha }} = ?</strong>
                        </label>
                        <input id="captcha" name="captcha" type="number" inputmode="numeric"
                               class="form-control @error('captcha') is-invalid @enderror"
                               style="max-width:180px" required autocomplete="off">
                        @error('captcha')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 btn-touch">
                        <i class="bi bi-send"></i>Gửi yêu cầu
                    </button>

                    <p class="small text-secondary text-center mt-3 mb-0">
                        Chúng tôi dùng thông tin này chỉ để xử lý yêu cầu của bạn —
                        xem <a href="{{ route('legal.privacy') }}">Chính sách bảo mật</a>.
                    </p>
                </div>
            </form>
        </div>
    </main>

    @include('public.partials.footer')
@endsection
