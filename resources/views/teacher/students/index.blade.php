@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Học sinh — TOÁN AI')
@section('page_title', 'Học sinh')

@section('content')
    <h2 class="h5 fw-bold mb-3">Học sinh các lớp tôi dạy</h2>

    @include('teacher.partials.student-filters')
    @include('teacher.partials.student-table', ['students' => $students])
@endsection
