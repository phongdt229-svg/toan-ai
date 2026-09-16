<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="{{ route('home') }}">
            <i class="bi bi-calculator me-1"></i>TOÁN AI
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Mở menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link" href="#tinh-nang">Tính năng</a></li>
                <li class="nav-item"><a class="nav-link" href="#ai-tutor">AI Tutor</a></li>
                <li class="nav-item"><a class="nav-link" href="#chuong-trinh">Chương trình</a></li>
                <li class="nav-item"><a class="nav-link" href="#goi-hoc">Gói học</a></li>
            </ul>

            <div class="d-flex flex-column flex-lg-row gap-2 ms-lg-3 mt-3 mt-lg-0">
                @auth
                    <a href="{{ auth()->user()->homeRoute() }}" class="btn btn-primary">Vào học</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-primary">Đăng nhập</a>
                    <a href="{{ route('register') }}" class="btn btn-accent">Đăng ký</a>
                @endauth
            </div>
        </div>
    </div>
</nav>
