<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmPasswordRequest;
use App\Services\Auth\DataExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportController extends Controller
{
    public function __construct(private readonly DataExportService $export) {}

    /**
     * POST chứ không GET, và hỏi lại mật khẩu: file chứa toàn bộ dữ liệu cá nhân, một phiên đăng nhập
     * bỏ quên trên máy dùng chung không được đủ để tải nó về.
     */
    public function store(ConfirmPasswordRequest $request): StreamedResponse
    {
        $data = $this->export->build($request->user());
        $filename = 'toan-ai-du-lieu-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(
            fn () => print json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE),
            $filename,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }
}
