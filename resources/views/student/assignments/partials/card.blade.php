@php
    use App\Support\Score;

    $a = $record->assignment;
    $icon = ['question_set' => 'bi-pencil-square', 'exam' => 'bi-clipboard-check', 'lesson' => 'bi-journal-text'][$a->type] ?? 'bi-list-check';

    // Hạn: đỏ khi quá hạn, vàng khi còn dưới 24 giờ.
    $dueTone = match (true) {
        $a->due_at === null => null,
        $record->isDone() => 'secondary',
        $a->due_at->isPast() => 'danger',
        $a->due_at->diffInHours(now(), true) < 24 => 'warning',
        default => 'light',
    };
@endphp

<a href="{{ route('student.assignments.show', $a) }}" class="card border text-decoration-none text-body">
    <div class="card-body d-flex align-items-start gap-3">
        <div class="feature-card__icon mb-0 flex-shrink-0"><i class="bi {{ $icon }}"></i></div>

        <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold">{{ $a->title }}</div>
            <div class="text-secondary small">{{ $a->schoolClass?->name }} · {{ $a->typeLabel() }}</div>

            <div class="d-flex flex-wrap gap-2 mt-2">
                @if ($a->due_at)
                    <span class="badge text-bg-{{ $dueTone }} {{ $dueTone === 'light' ? 'border' : '' }}">
                        <i class="bi bi-calendar-event me-1"></i>Hạn {{ $a->due_at->format('H:i d/m') }}
                    </span>
                @endif

                @if ($record->status === 'completed')
                    <span class="badge text-bg-success">
                        Đã xong{{ $record->percent !== null ? ' · ' . $record->percent . '%' : '' }}
                    </span>
                @elseif ($record->status === 'submitted')
                    <span class="badge text-bg-info">Chờ chấm</span>
                @elseif ($a->isClosed())
                    <span class="badge text-bg-secondary">Đã đóng</span>
                @endif

                @if ($record->is_late)
                    <span class="badge text-bg-warning text-dark">Nộp trễ</span>
                @endif
            </div>
        </div>

        <i class="bi bi-chevron-right text-secondary"></i>
    </div>
</a>
