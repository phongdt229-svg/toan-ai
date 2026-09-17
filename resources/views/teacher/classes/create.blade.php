@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Tạo lớp — TOÁN AI')
@section('page_title', 'Tạo lớp')

@section('content')
    <a href="{{ route('teacher.classes.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Lớp học
    </a>

    <h2 class="h5 fw-bold mt-2 mb-3">Tạo lớp mới</h2>

    <div class="card border" style="max-width:40rem">
        <div class="card-body">
            <form method="POST" action="{{ route('teacher.classes.store') }}" novalidate>
                @csrf
                @include('teacher.classes.partials.form')
                <button type="submit" class="btn btn-primary btn-touch mt-4">Tạo lớp</button>
            </form>
        </div>
    </div>
@endsection
