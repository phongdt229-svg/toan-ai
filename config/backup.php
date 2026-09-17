<?php

return [

    // Đường dẫn mysqldump. XAMPP Windows: C:/xampp/mysql/bin/mysqldump.exe
    'mysqldump' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),

    // Thư mục lưu bản sao lưu. Production nên đồng bộ thư mục này ra ngoài máy chủ (S3, NAS…).
    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    // Giữ bao nhiêu ngày — bản cũ hơn bị xoá sau mỗi lần sao lưu thành công.
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    'timeout' => (int) env('BACKUP_TIMEOUT', 900),

];
