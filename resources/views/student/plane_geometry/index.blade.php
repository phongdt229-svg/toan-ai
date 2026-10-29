@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Hình học phẳng — Math AI')
@section('page_title', 'Hình học phẳng')

@section('content')
    <p class="text-secondary small mb-3">
        Chọn công cụ rồi bấm thẳng lên lưới để vẽ — kéo điểm để chỉnh hình, cuộn để zoom. Công cụ
        tự học — không phải bài kiểm tra chấm điểm.
    </p>

    @include('partials.plane-geometry-widget')
@endsection

@push('scripts')
    @vite('resources/js/plane-geometry.js')
@endpush
