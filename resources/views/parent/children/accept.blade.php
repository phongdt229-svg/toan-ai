@extends('layouts.app', ['portal' => 'parent'])

@section('title', 'Xác nhận liên kết — TOÁN AI')
@section('page_title', 'Xác nhận liên kết')

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card border-primary text-center">
                <div class="card-body p-4">
                    <i class="bi bi-person-heart text-primary" style="font-size:3rem"></i>

                    <h2 class="h5 fw-bold mt-3 mb-1">Liên kết với {{ $student->name }}?</h2>
                    <p class="text-secondary mb-4">
                        {{ $student->studentProfile?->grade?->name }}
                        — sau khi liên kết, bạn xem được tiến độ, điểm, thời gian học và nhận xét của giáo viên.
                    </p>

                    <form method="POST" action="{{ route('parent.children.link.store') }}">
                        @csrf
                        <input type="hidden" name="link_code" value="{{ $code }}">
                        <div class="d-grid d-sm-flex justify-content-sm-center gap-2">
                            <button class="btn btn-primary btn-lg btn-touch">Đúng, liên kết</button>
                            <a href="{{ route('parent.dashboard') }}" class="btn btn-outline-secondary btn-lg btn-touch">Không phải con tôi</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
