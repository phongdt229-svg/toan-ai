@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Bài được giao — TOÁN AI')
@section('page_title', 'Bài được giao')

@section('content')
    <h2 class="h5 fw-bold mb-3">Cần làm</h2>

    @if ($pending->isEmpty())
        <div class="card border mb-4">
            <div class="card-body text-center p-4 text-secondary">
                <i class="bi bi-emoji-smile fs-2 d-block mb-2"></i>
                Không còn bài nào phải làm.
            </div>
        </div>
    @else
        <div class="d-grid gap-2 mb-4">
            @foreach ($pending as $record)
                @include('student.assignments.partials.card', ['record' => $record])
            @endforeach
        </div>
    @endif

    @if ($done->isNotEmpty())
        <h2 class="h6 fw-bold mb-2">Đã làm</h2>
        <div class="d-grid gap-2">
            @foreach ($done as $record)
                @include('student.assignments.partials.card', ['record' => $record])
            @endforeach
        </div>
    @endif

    @if ($pending->isEmpty() && $done->isEmpty())
        <p class="text-secondary small">
            Chưa vào lớp nào? <a href="{{ route('student.classes.index') }}">Nhập mã lớp</a> giáo viên đưa cho bạn.
        </p>
    @endif
@endsection
