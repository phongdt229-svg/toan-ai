@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Lịch học — TOÁN AI')
@section('page_title', 'Lịch học')

@section('content')
    <h2 class="h5 fw-bold mb-1">Lịch học của em</h2>
    <p class="text-secondary small mb-3" style="max-width:40rem">
        Chọn những ngày em ngồi vào học và giờ bắt đầu. Học đúng giờ đều đặn quan trọng hơn học dồn một hôm thật lâu.
        Phụ huynh đã liên kết cũng xem và sửa được lịch này — ai sửa thì bên kia được báo.
    </p>

    @include('partials.study-schedule-form', ['action' => route('student.schedule.update')])
@endsection
