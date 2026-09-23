@php
    $flagBadges = [
        'needs_support' => ['danger', 'Cần hỗ trợ'],
        'not_done' => ['warning', 'Chưa làm bài'],
        'low_score' => ['danger', 'Điểm thấp'],
        'improving' => ['success', 'Tiến bộ'],
    ];
@endphp

@if ($students->isEmpty())
    <div class="card border">
        <div class="card-body text-center p-4 text-secondary">Không có học sinh nào khớp bộ lọc.</div>
    </div>
@else
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Học sinh</th>
                    <th class="text-center">Đã làm</th>
                    <th class="text-center">Quá hạn</th>
                    <th class="text-end">Điểm TB</th>
                    <th class="text-end d-none d-md-table-cell">Xu hướng</th>
                    <th>Ghi chú</th>
                    @if ($removeFrom ?? null)<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $row)
                    <tr>
                        <td>
                            <a href="{{ route('teacher.students.show', $row['student']) }}" class="fw-semibold text-decoration-none">
                                {{ $row['student']->name }}
                            </a>
                            @unless ($removeFrom ?? null)
                                <div class="text-secondary small">{{ implode(', ', $row['classes']) }}</div>
                            @endunless
                        </td>
                        <td class="text-center">{{ $row['done'] }}/{{ $row['assigned'] }}</td>
                        <td class="text-center {{ $row['overdue'] > 0 ? 'text-danger fw-semibold' : 'text-secondary' }}">
                            {{ $row['overdue'] }}
                        </td>
                        <td class="text-end">{{ $row['avg_percent'] !== null ? $row['avg_percent'] . '%' : '—' }}</td>
                        <td class="text-end d-none d-md-table-cell">
                            @if ($row['trend'] === null)
                                <span class="text-secondary">—</span>
                            @elseif ($row['trend'] > 0)
                                <span class="text-success"><i class="bi bi-arrow-up-short"></i>{{ $row['trend'] }}</span>
                            @elseif ($row['trend'] < 0)
                                <span class="text-danger"><i class="bi bi-arrow-down-short"></i>{{ abs($row['trend']) }}</span>
                            @else
                                <span class="text-secondary">0</span>
                            @endif
                        </td>
                        <td>
                            @foreach ($row['flags'] as $flag)
                                <span class="badge text-bg-{{ $flagBadges[$flag][0] }} {{ $flagBadges[$flag][0] === 'warning' ? 'text-dark' : '' }}">
                                    {{ $flagBadges[$flag][1] }}
                                </span>
                            @endforeach
                        </td>
                        @if ($removeFrom ?? null)
                            <td class="text-end">
                                <form method="POST" action="{{ route('teacher.classes.students.remove', [$removeFrom, $row['student']]) }}"
                                      data-confirm="Xoá {{ $row['student']->name }} khỏi lớp?" data-confirm-ok="Xoá">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger p-0" title="Xoá khỏi lớp">
                                        <i class="bi bi-person-dash"></i>
                                    </button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
