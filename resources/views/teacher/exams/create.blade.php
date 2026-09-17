@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Tạo đề kiểm tra — TOÁN AI')
@section('page_title', 'Tạo đề kiểm tra')

@section('content')
    <a href="{{ route('teacher.exams.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Danh sách đề
    </a>

    <h2 class="h5 fw-bold mt-2 mb-3">Tạo đề kiểm tra</h2>

    <div class="card border">
        <div class="card-body">
            <form method="POST" action="{{ route('teacher.exams.store') }}" novalidate>
                @csrf
                @include('teacher.exams.partials.form')

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-touch">Tạo và thêm câu hỏi</button>
                    <a href="{{ route('teacher.exams.index') }}" class="btn btn-outline-secondary btn-touch">Huỷ</a>
                </div>
            </form>
        </div>
    </div>
@endsection
