@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Duyệt giáo viên — TOÁN AI')
@section('page_title', 'Duyệt giáo viên')

@section('content')
    <h2 class="h5 fw-bold mb-3">Giáo viên chờ duyệt</h2>

    @if ($teachers->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4 text-secondary">
                <i class="bi bi-check2-circle fs-2 d-block mb-2 text-success"></i>
                Không có hồ sơ nào đang chờ.
            </div>
        </div>
    @else
        <div class="d-grid gap-3">
            @foreach ($teachers as $teacher)
                <div class="card border">
                    <div class="card-body">
                        <div class="row g-3 align-items-center">
                            <div class="col-12 col-md">
                                <div class="fw-semibold">{{ $teacher->name }}</div>
                                <div class="text-secondary small">
                                    {{ $teacher->email }} · {{ $teacher->phone }}
                                </div>
                                <div class="text-secondary small">
                                    <i class="bi bi-building me-1"></i>{{ $teacher->teacherProfile?->school ?? '—' }}
                                </div>
                                <div class="text-secondary small">
                                    Đăng ký {{ $teacher->created_at->diffForHumans() }}
                                </div>
                            </div>

                            <div class="col-12 col-md-auto d-flex gap-2">
                                <form method="POST" action="{{ route('admin.teachers.approve', $teacher) }}">
                                    @csrf
                                    <button class="btn btn-success">
                                        <i class="bi bi-check-lg me-1"></i>Duyệt
                                    </button>
                                </form>

                                <button class="btn btn-outline-danger" data-bs-toggle="collapse"
                                        data-bs-target="#reject-{{ $teacher->id }}">
                                    Từ chối
                                </button>
                            </div>
                        </div>

                        <div class="collapse mt-3" id="reject-{{ $teacher->id }}">
                            <form method="POST" action="{{ route('admin.teachers.reject', $teacher) }}"
                                  class="d-flex flex-column flex-sm-row gap-2">
                                @csrf
                                <input type="text" name="reason" class="form-control" maxlength="191"
                                       placeholder="Lý do từ chối" required>
                                <button class="btn btn-danger flex-shrink-0">Xác nhận từ chối</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3">{{ $teachers->links() }}</div>
    @endif
@endsection
