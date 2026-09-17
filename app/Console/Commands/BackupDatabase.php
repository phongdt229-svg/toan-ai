<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Sao lưu DB bằng mysqldump → .sql.gz, xoá bản quá hạn.
 * Mật khẩu truyền qua biến môi trường MYSQL_PWD, không nằm trên dòng lệnh (không lộ trong danh sách tiến trình).
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep= : Số ngày giữ bản sao lưu (mặc định theo config/backup.php)}';

    protected $description = 'Sao lưu cơ sở dữ liệu ra file .sql.gz và dọn bản cũ';

    public function handle(): int
    {
        $db = config('database.connections.'.config('database.default'));

        if (! in_array($db['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error('Chỉ hỗ trợ MySQL/MariaDB.');

            return self::FAILURE;
        }

        $dir = rtrim(config('backup.path'), '/\\');
        File::ensureDirectoryExists($dir);

        $name = $db['database'].'-'.now()->format('Ymd-His');
        $sqlPath = "{$dir}/{$name}.sql";

        $result = Process::timeout(config('backup.timeout'))
            ->env(['MYSQL_PWD' => (string) ($db['password'] ?? '')])
            ->run([
                config('backup.mysqldump'),
                '--host='.$db['host'],
                '--port='.$db['port'],
                '--user='.$db['username'],
                '--single-transaction', // InnoDB: bản chụp nhất quán, không khoá bảng khi đang có người học
                '--quick',
                '--routines',
                '--default-character-set=utf8mb4',
                '--result-file='.$sqlPath,
                $db['database'],
            ]);

        if ($result->failed() || ! File::exists($sqlPath)) {
            File::delete($sqlPath);
            Log::error('Sao lưu DB thất bại', ['error' => $result->errorOutput()]);
            $this->error('Sao lưu thất bại: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $gzPath = $this->gzip($sqlPath);
        $size = number_format(File::size($gzPath) / 1024, 1);
        $this->info("Đã sao lưu: {$gzPath} ({$size} KB)");

        $removed = $this->prune($dir, (int) ($this->option('keep') ?? config('backup.keep_days')));
        if ($removed) {
            $this->line("Đã xoá {$removed} bản sao lưu cũ.");
        }

        return self::SUCCESS;
    }

    /** Nén theo luồng — file dump lớn không bị đọc hết vào RAM. */
    private function gzip(string $sqlPath): string
    {
        $gzPath = $sqlPath.'.gz';
        $in = fopen($sqlPath, 'rb');
        $out = gzopen($gzPath, 'wb6');

        while (! feof($in)) {
            gzwrite($out, fread($in, 1024 * 512));
        }

        fclose($in);
        gzclose($out);
        File::delete($sqlPath);

        return $gzPath;
    }

    private function prune(string $dir, int $keepDays): int
    {
        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $removed = 0;

        foreach (File::glob($dir.'/*.sql.gz') as $file) {
            if (File::lastModified($file) < $cutoff) {
                File::delete($file);
                $removed++;
            }
        }

        return $removed;
    }
}
