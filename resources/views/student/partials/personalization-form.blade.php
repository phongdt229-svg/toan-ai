{{-- Thầy/cô, màu yêu thích, sở thích — nhận $profile (StudentProfile|null). Tài khoản học sinh cũ chưa có hồ sơ thì ẩn. --}}
@if ($profile)
    <div class="card border mb-3" style="max-width:36rem">
        <div class="card-body">
            <h3 class="h6 fw-bold mb-1">Cá nhân hoá</h3>
            <p class="small text-secondary mb-3">AI Tutor xưng hô và lấy ví dụ theo lựa chọn này; màu yêu thích là màu nhấn của trang học.</p>

            <form method="POST" action="{{ route('student.settings.personalization') }}">
                @csrf
                @method('PUT')

                <fieldset class="mb-3">
                    <legend class="form-label fs-6">Học cùng</legend>
                    <div class="row g-2">
                        @foreach (\App\Models\StudentProfile::PERSONAS as $value => $label)
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="tutor_persona" id="set-persona-{{ $value }}"
                                       value="{{ $value }}" @checked(old('tutor_persona', $profile->tutor_persona) === $value) required>
                                <label class="btn btn-outline-primary w-100 btn-touch" for="set-persona-{{ $value }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                    @error('tutor_persona')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </fieldset>

                <div class="mb-3">
                    <label for="set-favorite-color" class="form-label">Màu yêu thích</label>
                    <input type="color" id="set-favorite-color" name="favorite_color"
                           value="{{ old('favorite_color', $profile->favorite_color ?: '#2563eb') }}"
                           class="form-control form-control-color w-100 @error('favorite_color') is-invalid @enderror">
                    <div class="form-text">Màu quá sáng sẽ được làm đậm lên một chút để chữ trên nút vẫn dễ đọc.</div>
                    @error('favorite_color')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="set-interests" class="form-label">Sở thích</label>
                    <input type="text" id="set-interests" name="interests" maxlength="255"
                           value="{{ old('interests', implode(', ', $profile->interests ?? [])) }}"
                           placeholder="bóng đá, vẽ, game"
                           class="form-control @error('interests') is-invalid @enderror">
                    <div class="form-text">Cách nhau bằng dấu phẩy.</div>
                    @error('interests')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="set-target-score" class="form-label">Mục tiêu điểm Toán</label>
                    <input type="number" id="set-target-score" name="target_score" min="0" max="10" step="0.5"
                           value="{{ old('target_score', $profile->target_score !== null ? (float) $profile->target_score : '') }}"
                           placeholder="vd 8" class="form-control @error('target_score') is-invalid @enderror" style="max-width:8rem">
                    <div class="form-text">Hiện ở trang Lộ trình để em thấy mình còn cách mục tiêu bao xa.</div>
                    @error('target_score')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <button class="btn btn-primary btn-touch">Lưu cá nhân hoá</button>
            </form>
        </div>
    </div>
@endif
