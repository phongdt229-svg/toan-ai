<?php

namespace Tests\Feature;

use App\Support\DiagnosticDebug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Debug riêng cho người gỡ lỗi ở production — khách thường không bao giờ thấy chi tiết lỗi. */
class DiagnosticDebugTest extends TestCase
{
    private const TOKEN = 'abcdefghijklmnopqrstuvwxyz0123456789';

    private function request(?string $cookie): Request
    {
        return Request::create('/', 'GET', [], $cookie === null ? [] : [DiagnosticDebug::COOKIE => $cookie]);
    }

    public function test_cookie_matching_the_token_turns_debug_on(): void
    {
        $this->assertTrue(DiagnosticDebug::allows($this->request(self::TOKEN), self::TOKEN));
    }

    public function test_missing_or_wrong_cookie_keeps_debug_off(): void
    {
        $this->assertFalse(DiagnosticDebug::allows($this->request(null), self::TOKEN));
        $this->assertFalse(DiagnosticDebug::allows($this->request(''), self::TOKEN));
        $this->assertFalse(DiagnosticDebug::allows($this->request(self::TOKEN.'x'), self::TOKEN));
    }

    public function test_empty_or_short_token_disables_the_feature(): void
    {
        // Token trống mà cookie cũng trống không được coi là khớp nhau.
        $this->assertFalse(DiagnosticDebug::allows($this->request(''), ''));
        $this->assertFalse(DiagnosticDebug::allows($this->request(null), null));
        $this->assertFalse(DiagnosticDebug::allows($this->request('short'), 'short'));
    }

    public function test_500_page_shows_the_cause_only_when_debug_is_on(): void
    {
        $exception = new HttpException(500, 'wrapped', new RuntimeException('Bảng legal_pages không tồn tại'));

        config(['app.debug' => false]);
        $this->assertStringNotContainsString('legal_pages', View::make('errors.500', ['exception' => $exception])->render());

        config(['app.debug' => true]);
        $html = View::make('errors.500', ['exception' => $exception])->render();
        $this->assertStringContainsString('RuntimeException: Bảng legal_pages không tồn tại', $html);
    }

    public function test_diag_script_answers_404_without_a_token(): void
    {
        // Chạy ngoài Laravel như trên server — không có ?token= thì không lộ gì.
        $output = shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(public_path('_diag.php')));

        $this->assertSame('Not Found', trim((string) $output));
    }
}
