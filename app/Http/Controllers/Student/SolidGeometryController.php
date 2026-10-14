<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Công cụ vẽ hình 3D tương tác (chương "Hình học không gian" lớp 11–12) — thuần tự học,
 * không phải dạng bài chấm điểm. Toán dựng hình/thể tích/diện tích tính hết ở client
 * (resources/js/solid-geometry.js), controller chỉ render khung trang.
 */
class SolidGeometryController extends Controller
{
    public function index(): View
    {
        return view('student.solid_geometry.index');
    }
}
