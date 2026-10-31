{{-- Hỏi đáp theo chủ đề + hỏi AI dùng chung một mục menu; thanh này chuyển qua lại giữa hai trang. Chỉ cho học sinh. --}}
@if (auth()->user()->isStudent())
    <ul class="nav nav-pills mb-3 gap-1" aria-label="Chọn cách hỏi">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('student.qa.*') ? 'active' : '' }}" href="{{ route('student.qa.index') }}">
                <i class="bi bi-chat-dots me-1"></i>Hỏi đáp theo chủ đề
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('student.ai.*') ? 'active' : '' }}" href="{{ route('student.ai.index') }}">
                <i class="bi bi-robot me-1"></i>Hỏi AI
            </a>
        </li>
    </ul>
@endif
