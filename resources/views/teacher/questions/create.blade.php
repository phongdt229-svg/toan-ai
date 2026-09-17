@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Tạo câu hỏi — TOÁN AI')
@section('page_title', 'Tạo câu hỏi')

@section('content')
    <a href="{{ route('teacher.questions.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Ngân hàng câu hỏi
    </a>

    <h2 class="h5 fw-bold mt-2 mb-3">Tạo câu hỏi mới</h2>

    <div class="card border">
        <div class="card-body">
            <form method="POST" action="{{ route('teacher.questions.store') }}" novalidate>
                @csrf
                @include('teacher.questions.partials.form')

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-touch">Lưu câu hỏi</button>
                    <a href="{{ route('teacher.questions.index') }}" class="btn btn-outline-secondary btn-touch">Huỷ</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @include('teacher.questions.partials.script')
@endpush
