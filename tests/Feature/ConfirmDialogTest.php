<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Hộp thoại xác nhận dùng chung (đợt 23/09).
 *
 * Test quét mã nguồn chứ không mở trình duyệt: mục đích là chặn `confirm()`/`alert()` mặc định
 * bò ngược trở lại khi có người thêm màn hình mới.
 */
class ConfirmDialogTest extends TestCase
{
    /** @return list<string> */
    private function bladeFiles(): array
    {
        $files = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function test_no_view_uses_the_browser_confirm_or_alert(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $path) {
            $source = file_get_contents($path);

            // Bỏ qua chính lời gọi hộp thoại dùng chung.
            $stripped = str_replace(['window.confirmDialog', 'window.alertDialog', 'confirmDialog(', 'alertDialog('], '', $source);

            if (preg_match('/(?<![\w.])(confirm|alert)\s*\(/', $stripped)) {
                $offenders[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $path);
            }
        }

        $this->assertSame([], $offenders,
            "Dùng confirm()/alert() mặc định của trình duyệt. Thay bằng data-confirm=\"…\" ".
            'hoặc window.confirmDialog(). Các file: '.implode(', ', $offenders));
    }

    public function test_every_confirm_form_keeps_its_message(): void
    {
        $withConfirm = 0;

        foreach ($this->bladeFiles() as $path) {
            $source = file_get_contents($path);
            preg_match_all('/data-confirm="([^"]*)"/', $source, $matches);

            foreach ($matches[1] as $message) {
                $withConfirm++;
                // Hộp thoại trống thì người dùng không biết mình đang đồng ý điều gì.
                $this->assertNotSame('', trim($message), "data-confirm rỗng trong {$path}");
            }
        }

        // Có thật nhiều màn hình dùng, không phải test rỗng.
        $this->assertGreaterThan(15, $withConfirm);
    }

    public function test_the_dialog_script_is_bundled(): void
    {
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $entry = $manifest['resources/js/app.js'] ?? null;

        $this->assertNotNull($entry, 'app.js không có trong manifest — chạy npm run build.');
        $this->assertStringContainsString(
            'confirm-dialog',
            file_get_contents(resource_path('js/app.js')),
            'app.js phải nạp confirm-dialog, nếu không data-confirm sẽ im lặng bỏ qua.',
        );
    }
}
