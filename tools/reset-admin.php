<?php
/**
 * ตั้งรหัสผ่านผู้ดูแลระบบใหม่ หรือสร้างบัญชีผู้ดูแลระบบเพิ่ม (กรณีลืมรหัสผ่าน / เข้าหลังบ้านไม่ได้)
 * ใช้ได้เฉพาะผู้ที่เข้าถึงเครื่องเซิร์ฟเวอร์ ผ่านบรรทัดคำสั่งเท่านั้น
 *
 *   C:\xampp\php\php.exe C:\xampp\htdocs\docshub\tools\reset-admin.php
 *
 * ระบบจะถามชื่อผู้ใช้และรหัสผ่าน (พิมพ์รหัสผ่านแล้วจะไม่แสดงบนหน้าจอ)
 * - ชื่อผู้ใช้ที่มีอยู่แล้ว: ตั้งรหัสผ่านใหม่ เปลี่ยนเป็นผู้ดูแลระบบ และเปิดใช้งานบัญชี
 * - ชื่อผู้ใช้ใหม่: สร้างบัญชีผู้ดูแลระบบ
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('ใช้งานผ่านบรรทัดคำสั่งเท่านั้น');
}
require_once __DIR__ . '/../includes/bootstrap.php';

// ตรวจว่ารันจากหน้าจอ (พิมพ์เอง) หรือรับข้อมูลทาง pipe — ต้องตรวจก่อนอ่าน STDIN
define('STDIN_IS_TTY', function_exists('stream_isatty') && @stream_isatty(STDIN));

function ask(string $prompt): string
{
    echo $prompt;
    return trim((string)fgets(STDIN));
}

/** รับรหัสผ่านแบบไม่แสดงบนหน้าจอ (Windows ใช้ PowerShell, ระบบอื่นใช้ stty) */
function ask_secret(string $prompt): string
{
    $tty = STDIN_IS_TTY;
    if ($tty && PHP_OS_FAMILY === 'Windows') {
        $cmd = 'powershell -NoProfile -Command "$s = Read-Host -AsSecureString \'' . $prompt . '\'; '
             . '[Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))"';
        $out = shell_exec($cmd);
        if ($out !== null && $out !== false) return rtrim($out, "\r\n");
    } elseif ($tty) {
        echo $prompt . ': ';
        shell_exec('stty -echo');
        $v = trim((string)fgets(STDIN));
        shell_exec('stty echo');
        echo "\n";
        return $v;
    }
    echo $prompt . ': ';
    return trim((string)fgets(STDIN));
}

echo "=== ตั้งรหัสผ่านผู้ดูแลระบบ " . setting('site_name') . " ===\n\n";
$admins = db_all("SELECT username, fullname, status FROM users WHERE role = 'admin' ORDER BY id");
echo "บัญชีผู้ดูแลระบบปัจจุบัน:\n";
foreach ($admins as $a) {
    echo "  - {$a['username']} ({$a['fullname']})" . ($a['status'] !== 'active' ? " [{$a['status']}]" : '') . "\n";
}
if (!$admins) echo "  (ไม่มี)\n";
echo "\n";

$username = ask('ชื่อผู้ใช้ (ใส่ชื่อเดิมเพื่อตั้งรหัสผ่านใหม่ หรือชื่อใหม่เพื่อสร้างบัญชี): ');
if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $username)) {
    fwrite(STDERR, "ชื่อผู้ใช้ต้องเป็น a-z, 0-9, _ หรือ . ยาว 4-30 ตัวอักษร\n");
    exit(1);
}
$user = db_row('SELECT * FROM users WHERE username = ?', 's', [$username]);
if ($user && $user['anonymized_at']) {
    fwrite(STDERR, "บัญชีนี้ถูกลบข้อมูลส่วนบุคคลตามคำขอ PDPA แล้ว ใช้ไม่ได้ กรุณาใช้ชื่อผู้ใช้อื่น\n");
    exit(1);
}
$fullname = $user ? $user['fullname'] : ask('ชื่อ-สกุล: ');
if ($fullname === '') {
    fwrite(STDERR, "กรุณากรอกชื่อ-สกุล\n");
    exit(1);
}

$pw1 = ask_secret('รหัสผ่านใหม่ (อย่างน้อย 8 ตัว)');
$pw2 = ask_secret('ยืนยันรหัสผ่านอีกครั้ง');
if (strlen($pw1) < 8) {
    fwrite(STDERR, "รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร\n");
    exit(1);
}
if ($pw1 !== $pw2) {
    fwrite(STDERR, "รหัสผ่านทั้งสองครั้งไม่ตรงกัน\n");
    exit(1);
}

$hash = password_hash($pw1, PASSWORD_DEFAULT);
if ($user) {
    db_exec("UPDATE users SET password_hash = ?, has_password = 1, role = 'admin', status = 'active' WHERE id = ?", 'si', [$hash, $user['id']]);
    log_activity('admin_reset_cli', 'ตั้งรหัสผ่านใหม่ให้ ' . $username . ' ผ่านบรรทัดคำสั่ง');
    echo "\nตั้งรหัสผ่านใหม่ให้ '$username' เรียบร้อยแล้ว (สิทธิ์: ผู้ดูแลระบบ, สถานะ: ใช้งาน)\n";
} else {
    db_exec("INSERT INTO users (username, password_hash, fullname, role, status) VALUES (?,?,?,'admin','active')", 'sss', [$username, $hash, mb_substr($fullname, 0, 150)]);
    log_activity('admin_create_cli', 'สร้างผู้ดูแลระบบ ' . $username . ' ผ่านบรรทัดคำสั่ง');
    echo "\nสร้างบัญชีผู้ดูแลระบบ '$username' เรียบร้อยแล้ว\n";
}
echo "เข้าสู่ระบบได้ที่ " . (setting('site_url') ?: 'http://localhost' . BASE_URL) . "/login.php\n";
