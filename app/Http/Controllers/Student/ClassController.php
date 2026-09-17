<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Teaching\ClassService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassController extends Controller
{
    public function __construct(private readonly ClassService $classes) {}

    public function index(Request $request): View
    {
        return view('student.classes.index', [
            'classes' => $request->user()->joinedClasses()->with('grade', 'owner')->get(),
        ]);
    }

    public function join(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isStudent(), 403);

        $data = $request->validate(
            ['code' => ['required', 'string', 'max:12']],
            [],
            ['code' => 'mã lớp'],
        );

        $class = $this->classes->joinByCode($request->user(), $data['code']);

        return redirect()->route('student.classes.index')
            ->with('status', "Đã tham gia lớp {$class->name}.");
    }
}
