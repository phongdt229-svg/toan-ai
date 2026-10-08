@extends('layouts.app', ['portal' => 'parent'])

@section('title', 'Lịch học của ' . $student->name . ' — TOÁN AI')
@section('page_title', 'Lịch học')

@section('content')
    <a href="{{ route('parent.children.show', $student) }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> {{ $student->name }}
    </a>

    <h2 class="h5 fw-bold mt-2 mb-1">Lịch học của {{ $student->name }}</h2>
    <p class="text-secondary small mb-3" style="max-width:40rem">
        Hệ thống dựa vào lịch này để biết con có vào học đúng giờ hay không. Con cũng thấy và sửa được lịch —
        nên thống nhất với con trước; ai sửa thì bên kia được báo.
    </p>

    @include('partials.study-schedule-form', ['action' => route('parent.children.schedule.update', $student)])
@endsection
