<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Công cụ vẽ hình học phẳng (2D) tương tác — thuần tự học, không phải dạng bài chấm điểm.
 * Toán dựng hình/đo đạc tính hết ở client (resources/js/plane-geometry-widget.js, JSXGraph),
 * controller chỉ render khung trang. Cùng tinh thần với SolidGeometryController (3D).
 */
class PlaneGeometryController extends Controller
{
    public function index(): View
    {
        return view('student.plane_geometry.index');
    }
}
