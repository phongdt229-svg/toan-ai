<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand p-0" href="{{ route('home') }}">
            <x-brand size="md" />
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Mở menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#tinh-nang">Tính năng</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#ai-tutor">AI Tutor</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#chuong-trinh">Chương trình</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('packages.index') }}">Gói học</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('blog.index') }}">Tin tức</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('guides.index') }}">Hướng dẫn</a></li>
            </ul>

            <div class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center gap-2 ms-lg-3 mt-3 mt-lg-0">
                @auth
                    <a href="{{ auth()->user()->homeRoute() }}" class="btn btn-primary">Vào học</a>

                    <div class="dropdown">
                        <button class="btn btn-sm btn-light d-flex align-items-center gap-2 w-100 justify-content-center justify-content-lg-start"
                                type="button" data-bs-toggle="dropdown" aria-label="Tài khoản">
                            <i class="bi bi-person-circle"></i>
                            <span>{{ auth()->user()->name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text small text-secondary">{{ auth()->user()->email }}</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-primary">Đăng nhập</a>
                    <a href="{{ route('register') }}" class="btn btn-accent">Đăng ký</a>
                @endauth
            </div>
        </div>
    </div>
</nav>
