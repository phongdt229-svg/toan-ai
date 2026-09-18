@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Bài làm: ' . $exam->title . ' — TOÁN AI')
@section('page_title', 'Bài làm')

@php use App\Support\Score; @endphp

@section('content')
    <a href="{{ route('teacher.exams.edit', $exam) }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> {{ $exam->title }}
    </a>

    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-2 mb-3">
        <h2 class="h5 fw-bold mb-0">Bài làm của học sinh</h2>

        <div class="btn-group btn-group-sm" role="group">
            <a href="{{ route('teacher.exams.attempts', $exam) }}"
               class="btn {{ request()->boolean('pending') ? 'btn-outline-secondary' : 'btn-secondary' }}">Tất cả</a>
            <a href="{{ route('teacher.exams.attempts', [$exam, 'pending' => 1]) }}"
               class="btn {{ request()->boolean('pending') ? 'btn-secondary' : 'btn-outline-secondary' }}">Chờ chấm</a>
        </div>
    </div>

    @if ($attempts->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4 text-secondary">Chưa có bài làm nào.</div>
        </div>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Học sinh</th>
                        <th class="text-center">Lượt</th>
                        <th class="text-end">Điểm</th>
                        <th>Nộp lúc</th>
                        <th>Trạng thái</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attempts as $attempt)
                        <tr>
                            <td>{{ $attempt->user->name }}</td>
                            <td class="text-center">{{ $attempt->attempt_no }}</td>
                            <td class="text-end">{{ Score::format($attempt->score) }}/{{ Score::format($attempt->total_points) }}</td>
                            <td class="small">
                                {{ $attempt->submitted_at?->format('H:i d/m') }}
                                @if ($attempt->auto_submitted)
                                    <span class="badge text-bg-light border">tự nộp</span>
                                @endif
                            </td>
                            <td>
                                @if ($attempt->status === 'submitted')
                                    <span class="badge text-bg-warning text-dark">Chờ chấm</span>
                                @else
                                    <span class="badge text-bg-success">Đã chấm</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('teacher.exams.grade', $attempt) }}" class="btn btn-sm btn-outline-primary">
                                    {{ $attempt->status === 'submitted' ? 'Chấm bài' : 'Xem' }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$attempts" label="bài làm" class="mt-3" />
    @endif
@endsection
