<x-mail::message>
# Báo cáo học tập tuần

Chào {{ $parent->name }},

Đây là tóm tắt việc học của con từ **{{ $periodStart->format('d/m') }}** đến **{{ $periodEnd->format('d/m/Y') }}**.

@foreach ($children as $week)
---

## {{ $week['student']->name }}

<x-mail::table>
| | Tuần này |
|:--|--:|
| Bài học hoàn thành | {{ $week['lessons_completed'] }} |
| Câu hỏi đã làm | {{ $week['questions_answered'] }} |
| Tỉ lệ làm đúng | {{ $week['accuracy'] !== null ? $week['accuracy'] . '%' : '—' }} |
| Đề kiểm tra đã làm | {{ $week['exams_finished'] }} |
| Điểm trung bình | {{ $week['average_score'] !== null ? \App\Support\Score::format($week['average_score']) . '/10' : '—' }} |
</x-mail::table>

@if ($week['assignments']['overdue'] > 0)
<x-mail::panel>
⚠️ Con đang có **{{ $week['assignments']['overdue'] }} bài quá hạn chưa nộp**.
</x-mail::panel>
@endif

@if ($week['weak_topics']->isNotEmpty())
**Nên ôn lại:** {{ $week['weak_topics']->map(fn ($m) => $m->topic->name)->implode(', ') }}
@endif

@foreach ($week['comments'] as $comment)
> **{{ $comment->teacher->name }}:** {{ $comment->content }}
@endforeach

@endforeach

<x-mail::button :url="route('parent.dashboard')">
Xem báo cáo đầy đủ
</x-mail::button>

Không muốn nhận thư này nữa? Tắt trong [Cài đặt]({{ route('parent.settings') }}).

{{ config('app.name') }}
</x-mail::message>
