@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Hình học không gian — Math AI')
@section('page_title', 'Hình học không gian')

@section('content')
    <a href="{{ route('student.learn.index', request()->filled('grade') ? ['grade' => (string) request('grade')] : []) }}"
       class="small text-decoration-none d-inline-block mb-2"><i class="bi bi-arrow-left"></i> Chương trình học</a>
    <p class="text-secondary small mb-3">
        Chọn hình, nhập số liệu rồi kéo để xoay, cuộn (hoặc chụm hai ngón trên điện thoại) để zoom.
        Công cụ tự học — không phải bài kiểm tra chấm điểm.
    </p>

    @include('partials.solid-geometry-widget')
@endsection

@push('scripts')
    @vite('resources/js/solid-geometry.js')
@endpush
