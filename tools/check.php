<?php
/**
 * ตรวจสอบระบบจากบรรทัดคำสั่ง
 *   C:\xampp\php\php.exe C:\xampp\htdocs\docshub\tools\check.php
 * คืนค่า exit code 0 = ผ่าน, 1 = มีรายการไม่ผ่าน (ใช้กับ Task Scheduler/สคริปต์ได้)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('ใช้งานผ่านบรรทัดคำสั่งเท่านั้น');
}
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/system_check.php';

$mark = ['ok' => '[ OK ]', 'warn' => '[WARN]', 'fail' => '[FAIL]', 'info' => '[INFO]'];
$sum = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
// บรรทัดคำสั่งไม่มี URL ของเว็บ จึงข้ามการทดสอบผ่าน HTTP
foreach (run_system_checks(false) as $group => $items) {
    echo "\n== $group ==\n";
    foreach ($items as $it) {
        $sum[$it['status']]++;
        echo $mark[$it['status']] . ' ' . $it['label'] . ($it['detail'] !== '' ? ' — ' . $it['detail'] : '') . "\n";
    }
}
echo "\nสรุป: ผ่าน {$sum['ok']} · เตือน {$sum['warn']} · ไม่ผ่าน {$sum['fail']}\n";
exit($sum['fail'] ? 1 : 0);
