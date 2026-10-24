<?php
/**
 * ย้ายไฟล์เดิมทั้งหมดใน uploads/docs ขึ้น Google Drive (โฟลเดอร์ตาม Folder ID ในหน้าตั้งค่า)
 *   C:\xampp\php\php.exe C:\xampp\htdocs\docshub\tools\move-to-drive.php
 * ต้องเชื่อมต่อ Google Drive ที่ หลังบ้าน → ตั้งค่าระบบ ก่อน
 * ไฟล์ที่อัปโหลดไม่สำเร็จจะยังอยู่ในเครื่อง รันซ้ำได้ (ย้ายเฉพาะไฟล์ที่ยังเหลือ)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('ใช้งานผ่านบรรทัดคำสั่งเท่านั้น');
}
require_once __DIR__ . '/../includes/bootstrap.php';

echo "=== ย้ายไฟล์เดิมขึ้น Google Drive ===\n";
echo "บัญชี: " . (setting('gdrive_account_email') ?: '(ยังไม่เชื่อมต่อ)') . " · Folder ID: " . (setting('gdrive_folder_id') ?: '(ยังไม่กำหนด)') . "\n";
try {
    // ภาพโปรไฟล์เก็บในเครื่องเสมอ: ดึงภาพที่เคยอยู่ใน Drive กลับมาที่ uploads/docs/avatars
    $av = storage_pull_avatars_from_drive();
    if ($av['moved'] || $av['failed']) echo "ภาพโปรไฟล์: ดึงกลับมาเก็บในเครื่อง {$av['moved']} ภาพ" . ($av['failed'] ? ' · ไม่สำเร็จ ' . count($av['failed']) : '') . "\n";
    $rc = storage_organize_drive(); // ไฟล์ที่อยู่ใน Drive แล้ว ย้ายเข้าโฟลเดอร์ตามประเภท
    if ($rc['moved'] || $rc['failed']) echo "จัดไฟล์ใน Drive เข้าโฟลเดอร์ตามประเภท {$rc['moved']} ไฟล์" . ($rc['failed'] ? ' · ไม่สำเร็จ ' . count($rc['failed']) : '') . "\n";
    $res = storage_migrate_to_drive(0, function (string $st, string $msg) {
        echo ($st === 'ok' ? '  [ OK ] ' : '  [FAIL] ') . $msg . "\n";
    });
} catch (Throwable $ex) {
    fwrite(STDERR, 'ERROR ' . $ex->getMessage() . "\n");
    exit(1);
}
foreach ($res['missing'] as $m) echo "  [SKIP] ไม่พบไฟล์ในเครื่อง: $m\n";
echo "\nสรุป: ย้ายแล้ว {$res['moved']} · ล้มเหลว " . count($res['failed']) . " · เหลือในเครื่อง {$res['remaining']}\n";
if (setting('storage_driver') !== 'gdrive') {
    echo "หมายเหตุ: ไฟล์ที่อัปโหลดใหม่ยังเก็บในเครื่อง — เปิดสวิตช์ \"เก็บไฟล์ที่อัปโหลดใหม่ใน Google Drive\" ที่หน้าตั้งค่าระบบ\n";
}
exit($res['failed'] ? 1 : 0);
