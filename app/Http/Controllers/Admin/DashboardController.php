<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'users' => User::count(),
                'students' => $this->countByRole(Role::STUDENT),
                'teachers' => $this->countByRole(Role::TEACHER),
                'parents' => $this->countByRole(Role::PARENT),
                'pending_teachers' => User::where('status', User::STATUS_PENDING)->count(),
            ],
        ]);
    }

    private function countByRole(string $role): int
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', $role))->count();
    }
}
