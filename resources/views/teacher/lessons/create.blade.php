@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Tạo bài học — TOÁN AI')
@section('page_title', 'Tạo bài học')

@section('content')
    <a href="{{ route('teacher.lessons.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Danh sách bài học
    </a>

    <h2 class="h5 fw-bold mt-2 mb-3">Tạo bài học mới</h2>

    <div class="card border">
        <div class="card-body">
            <form method="POST" action="{{ route('teacher.lessons.store') }}" novalidate>
                @csrf
                @include('teacher.lessons.partials.form')

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-touch">Tạo và soạn nội dung</button>
                    <a href="{{ route('teacher.lessons.index') }}" class="btn btn-outline-secondary btn-touch">Huỷ</a>
                </div>
            </form>
        </div>
    </div>
@endsection
