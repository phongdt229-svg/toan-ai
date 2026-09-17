@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Sửa câu hỏi — TOÁN AI')
@section('page_title', 'Sửa câu hỏi')

@section('content')
    <a href="{{ route('teacher.questions.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Ngân hàng câu hỏi
    </a>

    <h2 class="h5 fw-bold mt-2 mb-3">Sửa câu hỏi</h2>

    <div class="card border">
        <div class="card-body">
            <form method="POST" action="{{ route('teacher.questions.update', $question) }}" novalidate>
                @csrf
                @method('PUT')
                @include('teacher.questions.partials.form')

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-touch">Lưu thay đổi</button>
                    <a href="{{ route('teacher.questions.index') }}" class="btn btn-outline-secondary btn-touch">Huỷ</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @include('teacher.questions.partials.script')
@endpush
