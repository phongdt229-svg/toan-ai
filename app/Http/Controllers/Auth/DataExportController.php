<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\DataExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportController extends Controller
{
    public function __construct(private readonly DataExportService $export) {}

    /**
     * POST chứ không GET, và hỏi lại mật khẩu: file chứa toàn bộ dữ liệu cá nhân, một phiên đăng nhập
     * bỏ quên trên máy dùng chung không được đủ để tải nó về.
     */
    public function store(Request $request): StreamedResponse
    {
        $request->validate(['current_password' => ['required', 'string']]);

        if (! Hash::check($request->input('current_password'), $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }

        $data = $this->export->build($request->user());
        $filename = 'toan-ai-du-lieu-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(
            fn () => print json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE),
            $filename,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }
}
