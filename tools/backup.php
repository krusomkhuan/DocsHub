<?php
/**
 * สำรองข้อมูลผ่านบรรทัดคำสั่ง (ใช้กับ Windows Task Scheduler ได้)
 *
 *   C:\xampp\php\php.exe C:\xampp\htdocs\docshub\tools\backup.php          สำรองเฉพาะฐานข้อมูล
 *   C:\xampp\php\php.exe C:\xampp\htdocs\docshub\tools\backup.php --full   สำรองฐานข้อมูล + ไฟล์เอกสาร
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('ใช้งานผ่านบรรทัดคำสั่งเท่านั้น');
}
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/backup.php';

try {
    $name = backup_create(in_array('--full', $argv, true), 'cli');
    $pruned = backup_prune((int)setting('backup_keep', '10'));
    log_activity('backup_cli', $name);
    fwrite(STDOUT, "OK  " . BACKUP_DIR . DIRECTORY_SEPARATOR . $name . " (" . format_size((int)filesize(BACKUP_DIR . '/' . $name)) . ")\n");
    if ($dr = $GLOBALS['BACKUP_DRIVE_RESULT'] ?? null) fwrite(STDOUT, ($dr['ok'] ? 'DRIVE ' : 'WARN  ') . $dr['msg'] . "\n");
    if ($pruned) fwrite(STDOUT, "ลบไฟล์สำรองเก่า $pruned ไฟล์\n");
    exit(0);
} catch (Throwable $ex) {
    fwrite(STDERR, 'ERROR ' . $ex->getMessage() . "\n");
    exit(1);
}
