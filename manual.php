<?php
/** คู่มือการใช้งานระบบ Docs Hub (ครอบคลุมทุกฟังก์ชันถึงเวอร์ชันปัจจุบัน) */
require_once __DIR__ . '/includes/bootstrap.php';

$toc = [
    'start'    => ['bi-flag', 'เริ่มต้นใช้งาน'],
    'account'  => ['bi-person-plus', 'สมาชิก เข้าสู่ระบบ และข้อมูลส่วนตัว'],
    'search'   => ['bi-search', 'ค้นหาและดาวน์โหลดเอกสาร'],
    'upload'   => ['bi-cloud-upload', 'อัปโหลดเอกสาร'],
    'booknum'  => ['bi-123', 'ออกเลขหนังสือราชการ'],
    'trip'     => ['bi-geo-alt', 'รายงานการไปราชการ'],
    'albums'   => ['bi-images', 'คลังรูปภาพ'],
    'ledger'   => ['bi-cash-coin', 'บัญชีรายรับ-รายจ่ายแผนก'],
    'pdpa'     => ['bi-shield-lock', 'ข้อมูลส่วนบุคคล (PDPA)'],
    'staff'    => ['bi-person-workspace', 'สำหรับเจ้าหน้าที่'],
    'admin'    => ['bi-gear', 'สำหรับผู้ดูแลระบบ'],
    'google'   => ['bi-google', 'ตั้งค่า Google (เข้าสู่ระบบ / Drive)'],
    'storage'  => ['bi-folder2-open', 'ตำแหน่งจัดเก็บไฟล์'],
    'maintain' => ['bi-tools', 'การดูแลรักษาระบบ'],
    'faq'      => ['bi-question-circle', 'ปัญหาที่พบบ่อย'],
];
$role = fn(string ...$r) => implode('', array_map(fn($x) => '<span class="badge rounded-pill text-bg-' . ['ทุกคน' => 'light', 'สมาชิก' => 'primary', 'ผู้รับผิดชอบบัญชี' => 'info', 'เจ้าหน้าที่' => 'success', 'ผู้ดูแลระบบ' => 'danger'][$x] . ' me-1">' . $x . '</span>', $r));
$link = fn(string $path, string $text) => '<a href="' . url($path) . '">' . e($text) . '</a>';
$root = setting('gdrive_folder_name') ?: 'DocsHub';
$appPath = str_replace('/', '\\', APP_ROOT);

$pageTitle = 'คู่มือการใช้งาน';
$activeNav = 'manual';
require __DIR__ . '/includes/header.php';
?>
<div class="row g-4">
  <aside class="col-lg-3 d-print-none">
    <div class="card shadow-sm sticky-lg-top" style="top:80px">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-book me-1"></i>สารบัญ</div>
      <nav class="list-group list-group-flush small" id="manualToc">
        <?php $no = 0; foreach ($toc as $id => [$ic, $t]): ?>
          <a class="list-group-item list-group-item-action" href="#<?= $id ?>"><i class="bi <?= $ic ?> me-2"></i><?= ++$no ?>. <?= e($t) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="card-body border-top">
        <button class="btn btn-outline-primary btn-sm w-100" onclick="window.print()"><i class="bi bi-printer me-1"></i>พิมพ์ / บันทึกเป็น PDF</button>
      </div>
    </div>
  </aside>

  <article class="col-lg-9 manual">
    <div class="card shadow-sm mb-4"><div class="card-body p-4">
      <h2 class="mb-1"><i class="bi bi-book me-2 text-primary"></i>คู่มือการใช้งาน <?= e(setting('site_name')) ?></h2>
      <div class="text-muted"><?= e(setting('site_slogan')) ?> · <?= e(setting('college_name')) ?></div>
      <div class="small text-muted mt-1">สำหรับระบบเวอร์ชัน <?= e(APP_VERSION) ?> · พัฒนาระบบโดย <?= e(APP_DEVELOPER . ' ' . APP_DEVELOPER_DEPT) ?></div>
      <div class="small mt-2">สัญลักษณ์ผู้ใช้: <?= $role('ทุกคน', 'สมาชิก', 'ผู้รับผิดชอบบัญชี', 'เจ้าหน้าที่', 'ผู้ดูแลระบบ') ?></div>
      <div class="alert alert-info small mt-3 mb-0">
        <strong><i class="bi bi-stars me-1"></i>มีอะไรใหม่</strong>
        <ul class="mb-0 mt-1 ps-3">
          <li><strong>ค้นหาเอกสารทุกประเภทพร้อมกัน</strong> ค้นจากชื่อประเภท หมวด แผนก ปีการศึกษา ชื่อไฟล์ได้ และพิมพ์หลายคำเพื่อค้นให้แคบลง (หัวข้อ 3)</li>
          <li>ไฟล์ที่อัปโหลดเก็บใน Google Drive แยกโฟลเดอร์ตามประเภทภายใน <code><?= e(dirname(storage_display_path(''))) ?></code> ระบบสร้างโฟลเดอร์ที่ยังไม่มีให้เองและไม่สร้างซ้ำ (หัวข้อ 12–13)</li>
          <li>ไฟล์สำรองข้อมูลส่งสำเนาไป <code><?= e(storage_display_path('backups')) ?></code> อัตโนมัติ (หัวข้อ 14)</li>
        </ul>
      </div>
    </div></div>

    <!-- 1 -->
    <section id="start" class="card shadow-sm mb-4"><div class="card-body p-4">
      <h4><i class="bi bi-flag me-2"></i>1. เริ่มต้นใช้งาน</h4>
      <p>Docs Hub คือศูนย์รวมการจัดเก็บเอกสารของวิทยาลัย ได้แก่ คำสั่งราชการ เกียรติบัตร แผนการสอน โครงการสอน หนังสือเรียน และประเภทอื่นที่ผู้ดูแลเพิ่มได้ พร้อมระบบออกเลขหนังสือราชการ รายงานการไปราชการ คลังรูปภาพกิจกรรม และบัญชีรายรับ-รายจ่ายของแผนกวิชา</p>
      <h6>บทบาทผู้ใช้</h6>
      <div class="table-responsive"><table class="table table-sm table-bordered small">
        <thead class="table-light"><tr><th style="width:190px">บทบาท</th><th>ทำอะไรได้</th></tr></thead>
        <tbody>
          <tr><td><?= $role('ทุกคน') ?> บุคคลทั่วไป</td><td>ค้นหา ดู และดาวน์โหลดเอกสารที่เผยแพร่สาธารณะ ดูรายงานการไปราชการที่เผยแพร่ และสืบค้นคลังรูปภาพ</td></tr>
          <tr><td><?= $role('สมาชิก') ?> ครู/บุคลากร</td><td>ดูเอกสารภายใน อัปโหลดเอกสาร (รออนุมัติ) ออกเลขหนังสือของแผนกตน เขียนรายงานการไปราชการ ส่งลิงก์อัลบั้มภาพ และใช้บัญชีรายรับ-รายจ่ายของแผนกที่ตนสังกัด</td></tr>
          <tr><td><?= $role('ผู้รับผิดชอบบัญชี') ?></td><td>ครูที่ผู้ดูแลระบบแต่งตั้ง จัดการบัญชีของแผนกได้ทุกรายการ (รวมลบรายการของผู้อื่น) และจัดการหมวด</td></tr>
          <tr><td><?= $role('เจ้าหน้าที่') ?></td><td>เข้าหลังบ้าน อนุมัติ/แก้ไข/ลบเอกสาร ออกและยกเลิกเลขหนังสือทุกแผนก กำหนดเลขเริ่มต้น จัดการหมวดหมู่เอกสาร รายงานไปราชการ และอนุมัติคลังรูปภาพ</td></tr>
          <tr><td><?= $role('ผู้ดูแลระบบ') ?></td><td>ทุกอย่างของเจ้าหน้าที่ และจัดการสมาชิก แผนก ประเภทเอกสาร ปีการศึกษา ผู้รับผิดชอบบัญชี ตั้งค่าระบบ (URL, Google, Google Drive) PDPA ตรวจสอบระบบ สำรอง/กู้คืนข้อมูล และนำเข้าข้อมูลคลังรูปภาพ<br><span class="text-muted">หมายเหตุ: ผู้ดูแลระบบไม่เห็นบัญชีรายรับ-รายจ่ายของแผนกที่ตนไม่ได้สังกัด</span></td></tr>
        </tbody>
      </table></div>
      <h6>เมนูหลัก</h6>
      <ul class="mb-0">
        <li><strong>แถบเมนูด้านบน:</strong> หน้าแรก · หมวดเอกสาร · รายงานไปราชการ · คลังรูปภาพ · ออกเลขหนังสือ · เอกสารของฉัน · <i class="bi bi-cash-coin"></i> บัญชีแผนก (เมนูแยก มีเมนูย่อย) · <i class="bi bi-question-circle"></i> คู่มือ</li>
        <li><strong>เมนูชื่อของคุณ (มุมขวาบน):</strong> อัปโหลดเอกสาร · ข้อมูลส่วนตัว · ข้อมูลส่วนบุคคลของฉัน (PDPA) · ระบบหลังบ้าน (เจ้าหน้าที่/ผู้ดูแล) · ออกจากระบบ</li>
      </ul>
    </div></section>

    <!-- 2 -->
    <section id="account" class="card shadow-sm mb-4"><div class="card-body p-4">
      <h4><i class="bi bi-person-plus me-2"></i>2. สมาชิก เข้าสู่ระบบ และข้อมูลส่วนตัว <?= $role('สมาชิก') ?></h4>
      <h6>สมัครสมาชิกด้วยชื่อผู้ใช้</h6>
      <ol>
        <li>กด <strong>สมัครสมาชิก</strong> มุมขวาบน กรอกชื่อผู้ใช้ (a-z, 0-9) ชื่อ-สกุล เลือก<strong>แผนกวิชา/ฝ่าย</strong> และตั้งรหัสผ่านอย่างน้อย 8 ตัวอักษร</li>
        <li>ติ๊กยอมรับ<strong>นโยบายคุ้มครองข้อมูลส่วนบุคคล</strong> แล้วกดสมัคร</li>
        <li>รอผู้ดูแลระบบอนุมัติบัญชี (ยกเว้นตั้งให้อนุมัติอัตโนมัติ) แล้วจึงเข้าสู่ระบบ</li>
      </ol>
      <h6>เข้าสู่ระบบ / สมัครด้วย Google <span class="text-muted small">(เมื่อผู้ดูแลเปิดใช้)</span></h6>
      <ul>
        <li>กดปุ่ม <strong>เข้าสู่ระบบด้วย Google</strong> ในหน้าเข้าสู่ระบบ<?= google_allowed_domain() !== '' ? ' (รับเฉพาะบัญชี @' . e(google_allowed_domain()) . ')' : '' ?></li>
        <li>ถ้ายังไม่มีบัญชี ระบบจะให้กรอกชื่อผู้ใช้ เลือกแผนก และยอมรับนโยบาย แล้วรออนุมัติ</li>
        <li>ผู้ที่มีบัญชีอยู่แล้ว ให้เข้าสู่ระบบตามปกติ แล้วกด <strong>เชื่อมบัญชี Google</strong> ที่หน้า <?= $link('profile.php', 'ข้อมูลส่วนตัว') ?> (ระบบไม่เชื่อมจากอีเมลให้อัตโนมัติเพื่อความปลอดภัย)</li>
        <li>ผู้ที่สมัครด้วย Google ตั้งรหัสผ่านของระบบเพิ่มได้ที่หน้าข้อมูลส่วนตัว เพื่อเข้าด้วยชื่อผู้ใช้ได้ด้วย</li>
      </ul>
      <h6>ข้อมูลส่วนตัวและภาพโปรไฟล์</h6>
      <ul class="mb-2">
        <li>เมนูชื่อของคุณ → <?= $link('profile.php', 'ข้อมูลส่วนตัว') ?> แก้ชื่อ อีเมล เบอร์โทร เปลี่ยนรหัสผ่าน และอัปโหลด<strong>ภาพโปรไฟล์</strong> (JPG/PNG/WEBP ระบบครอปเป็นสี่เหลี่ยมจัตุรัสให้)</li>
        <li>แผนกวิชาของบัญชีกำหนดสิทธิ์การออกเลขหนังสือและบัญชีแผนก หากย้ายแผนกให้แจ้งผู้ดูแลระบบ</li>
      </ul>
      <div class="alert alert-light border small mb-0"><i class="bi bi-info-circle me-1"></i>เข้าสู่ระบบผิด 5 ครั้งจะถูกล็อก 5 นาที · ลืมรหัสผ่านให้ติดต่อผู้ดูแลระบบ</div>
    </div></section>

    <!-- 3 -->
    <section id="search" class="card shadow-sm mb-4"><div class="card-body p-4">
      <h4><i class="bi bi-search me-2"></i>3. ค้นหาและดาวน์โหลดเอกสาร <?= $role('ทุกคน') ?></h4>
      <ul class="mb-0">
        <li>พิมพ์คำค้นที่ช่องค้นหาหน้าแรก ระบบค้น<strong>เอกสารทุกประเภท</strong>พร้อมกัน (หรือเลือกประเภทในช่องข้างคำค้น) ค้นได้จากชื่อเอกสาร เลขที่ ชื่อบุคคล รหัส/ชื่อวิชา รายละเอียด ชื่อประเภท หมวดหมู่ แผนกวิชา (ชื่อหรือรหัสย่อ) ปีการศึกษา และชื่อไฟล์</li>
        <li>พิมพ์หลายคำคั่นด้วยช่องว่างเพื่อค้นให้แคบลง เช่น <code>เกียรติบัตร 2568</code> · ในหน้าผลการค้นหา คลิกประเภทด้านซ้ายเพื่อกรองโดยคำค้นยังอยู่</li>
        <li>เลือกประเภทจากการ์ดหน้าแรกหรือเมนู <strong>หมวดเอกสาร</strong> แล้วกรองด้วยหมวดหมู่ แผนกวิชา และปีการศึกษา</li>
        <li>เปิดเอกสารเพื่อดูรายละเอียด ไฟล์ <strong>PDF รูปภาพ และ Word (.docx)</strong> ดูออนไลน์ได้ทันทีในหน้าเอกสาร (Word กดปุ่ม <i class="bi bi-arrows-fullscreen"></i> เต็มจอได้ · ไฟล์ .doc แบบเก่าต้องดาวน์โหลด) กด <strong>ดาวน์โหลด</strong> เพื่อบันทึกไฟล์</li>
        <li>กด <strong>คัดลอกลิงก์เอกสาร</strong> เพื่อส่งต่อทาง LINE/อีเมล</li>
        <li>เอกสารที่มีไอคอน <i class="bi bi-lock-fill"></i> ดูได้เฉพาะสมาชิกที่เข้าสู่ระบบ · ระบบตรวจสิทธิ์ทุกครั้งที่ดาวน์โหลด ไม่ว่าไฟล์จะเก็บที่เซิร์ฟเวอร์หรือ Google Drive</li>
      </ul>
    </div></section>

    <!-- 4 -->
    <section id="upload" class="card shadow-sm mb-4"><div class="card-body p-4">
      <h4><i class="bi bi-cloud-upload me-2"></i>4. อัปโหลดเอกสาร <?= $role('สมาชิก') ?></h4>
      <ol>
        <li>เมนูชื่อของคุณ → <?= $link('upload.php', 'อัปโหลดเอกสาร') ?></li>
        <li>เลือก<strong>ประเภทเอกสาร</strong> ฟอร์มจะแสดงช่องที่เกี่ยวข้องให้อัตโนมัติ เช่น รหัสวิชา/ภาคเรียนสำหรับแผนการสอน</li>
        <li>กรอกชื่อเอกสาร เลขที่ ลงวันที่ แผนก ปีการศึกษา (ระบบเลือกปีปัจจุบันไว้ให้) และเลือกไฟล์ (PDF, Word, Excel, PowerPoint, รูปภาพ, ZIP ไม่เกิน <?= e(setting('max_upload_mb', '20')) ?> MB)</li>
        <li>ตั้งค่า <strong>เผยแพร่สาธารณะ</strong> หรือเฉพาะสมาชิก แล้วกดบันทึก</li>
      </ol>
      <ul class="mb-0">
        <li>เอกสารของสมาชิกมีสถานะ <span class="badge text-bg-warning">รออนุมัติ</span> จนกว่าเจ้าหน้าที่ตรวจสอบ ติดตามได้ที่ <?= $link('my-documents.php', 'เอกสารของฉัน') ?> · แก้ไข/ลบได้เองระหว่างรออนุมัติ</li>
        <li><strong>คำสั่งราชการ:</strong> ถ้าไม่กรอกเลขที่ ติ๊ก "ออกเลขคำสั่งอัตโนมัติ" ระบบจะออกเลขจากทะเบียนของแผนกให้</li>
        <li>ถ้าระบบเก็บไฟล์ใน Google Drive และขึ้นข้อความ <em>"บันทึกไฟล์ลง Google Drive ไม่สำเร็จ"</em> ให้ลองใหม่อีกครั้ง หากยังไม่ได้ให้แจ้งผู้ดูแลระบบ (เอกสารยังไม่ถูกบันทึก)</li>
        <li><strong>ข้อควรระวัง (PDPA):</strong> ก่อนเผยแพร่สาธารณะ ตรวจว่าเอกสารไม่มีข้อมูลส่วนบุคคลเกินจำเป็น เช่น เลขบัตรประชาชน ที่อยู่ เบอร์โทร</li>
      </ul>
    </div></section>

    <!-- 5 -->
    <section id="booknum" class="card shadow-sm mb-4"><div class="card-body p-4">
      <h4><i class="bi bi-123 me-2"></i>5. ออกเลขหนังสือราชการ <?= $role('สมาชิก', 'เจ้าหน้าที่') ?></h4>
      <ol>
        <li>เมนู <?= $link('book-numbers.php', 'ออกเลขหนังสือ') ?></li>
        <li>เลือกประเภท (หนังสือส่ง / หนังสือรับ / บันทึกข้อความ / คำสั่ง) ลงวันที่ กรอกเรื่องและผู้รับ</li>
        <li>กด <strong>ออกเลข</strong> ระบบแสดงเลขที่ได้ (เช่น <code>ชฟ 015/<?= current_year_be() ?></code>) กด <strong>คัดลอกเลข</strong> ไปใส่ในหนังสือ</li>
      </ol>
      <ul class="mb-0">
        <li>เลขรันใหม่ทุกปี พ.ศ. แยกตามแผนกและประเภท และไม่ซ้ำแม้หลายคนขอพร้อมกัน · สมาชิกออกเลขได้เฉพาะแผนกของตน</li>
        <li>เลขที่ออกผิดให้แจ้งเจ้าหน้าที่ <strong>ยกเลิก</strong> (แสดงขีดฆ่าและไม่ถูกนำกลับมาใช้)</li>
        <li><strong>กำหนดเลขเริ่มต้น</strong> <?= $role('เจ้าหน้าที่') ?>: การ์ด "กำหนดเลขเริ่มต้น" ในหน้าออกเลข เลือกแผนก ประเภท ปี พ.ศ. และเลขที่ต้องการเริ่ม เช่น ทะเบียนกระดาษใช้ถึงเลข 57 ให้ตั้งเริ่มที่ 58 · ถ้าออกเลขเกินเลขเริ่มต้นไปแล้ว ระบบนับต่อจากเลขล่าสุด</li>
        <li>เจ้าหน้าที่ดูทะเบียนทุกแผนกและส่งออก CSV ได้</li>
      </ul>
    </div></section>

    <!-- 6 -->
    <section id="trip" class="card shadow-sm mb-4"><div class="card-body p-4">
      <h4><i class="bi bi-geo-alt me-2"></i>6. รายงานการไปราชการ <?= $role('สมาชิก') ?></h4>
      <ol>
        <li>อัปโหลดคำสั่งไปราชการก่อน (ประเภทคำสั่งราชการ หมวด "คำสั่งไปราชการ")</li>
        <li>เปิดหน้าคำสั่งนั้น กด <strong>เขียนรายงาน</strong> หรือเมนู <?= $link('trip-reports.php', 'รายงานไปราชการ') ?> → เขียนรายงาน แล้วเลือกคำสั่ง</li>
        <li>กรอกเรื่อง สถานที่ วันที่ไป-กลับ ผู้ไปราชการ วัตถุประสงค์ ผลการไปราชการ ประโยชน์ที่ได้รับ ข้อเสนอแนะ และค่าใช้จ่าย</li>
        <li><strong>แนบภาพประกอบ</strong> ได้หลายภาพพร้อมกัน (สูงสุด 30 ภาพ) พิมพ์คำบรรยายใต้ภาพได้ ภาพจากมือถือถูกย่อขนาดและหมุนให้อัตโนมัติ</li>
        <li>กดบันทึก แล้วกด <strong>พิมพ์ / บันทึก PDF</strong> เพื่อได้รายงานขนาด A4 พร้อมช่องลงชื่อ</li>
      </ol>
      <p class="mb-0 small text-muted">แก้ไขภายหลังเพื่อเพิ่ม/ลบภาพ แก้คำบรรยาย หรือเรียงลำดับได้</p>
    </div></section>

    <!-- 7 -->
    <section id="albums" class="card shadow-sm mb-4"><div class="card-body p-4">
      <h4><i class="bi bi-images me-2"></i>7. คลังรูปภาพ <?= $role('ทุกคน', 'สมาชิก', 'เจ้าหน้าที่') ?></h4>
      <p>เมนู <?= $link('photo-albums.php', 'คลังรูปภาพ') ?> รวมลิงก์อัลบั้มภาพกิจกรรม (Google Photos, Google Drive, Facebook, YouTube ฯลฯ) ระบบเก็บเฉพาะลิงก์ ภาพจริงอยู่ที่บริการนั้น</p>
      <ul class="mb-0">
        <li><strong>ค้นหา:</strong> พิมพ์ชื่ออัลบั้มหรือกิจกรรม กรองตามปีการศึกษาและแผนก คลิกลิงก์เพื่อเปิดอัลบั้ม</li>
        <li><strong>ส่งลิงก์ (สมาชิก):</strong> ตั้งค่าแชร์อัลบั้มเป็น "ทุกคนที่มีลิงก์" แล้วกด <strong>ส่งลิงก์อัลบั้ม</strong> · ลิงก์จะแสดงหลังเจ้าหน้าที่อนุมัติ ติดตามสถานะได้ท้ายหน้าคลังรูปภาพ</li>
        <li><strong>เจ้าหน้าที่:</strong> หลังบ้าน → คลังรูปภาพ อนุมัติ/ไม่อนุมัติ (ระบุเหตุผล) แก้ไข ลบ</li>
        <li><strong>นำเข้าข้อมูลเดิม</strong> <?= $role('ผู้ดูแลระบบ') ?>: อัปโหลดไฟล์ Excel (.xlsx) / CSV หรือวางรายการ ระบบหาคอลัมน์ชื่ออัลบั้มและลิงก์ให้ แสดงตัวอย่างให้ตรวจก่อน แล้วจึงกดยืนยัน</li>
      </ul>
    </div></section>

    <!-- 8 -->
    <section id="ledger" class="card shadow-sm mb-4"><div class="card-body p-4">
      <h4><i class="bi bi-cash-coin me-2"></i>8. บัญชีรายรับ-รายจ่ายแผนก <?= $role('สมาชิก', 'ผู้รับผิดชอบบัญชี') ?></h4>
      <p>เมนู <?= $link('ledger.php', 'บัญชีแผนก') ?> เห็นได้<strong>เฉพาะครูที่สังกัดแผนกนั้น</strong> ครูแผนกอื่น เจ้าหน้าที่ และผู้ดูแลระบบที่ไม่ได้สังกัดแผนกนั้นจะไม่เห็นบัญชี</p>
      <h6>เมนูบัญชีแผนก</h6>
      <p class="small">บัญชีแผนกเป็นส่วนแยกในแถบเมนูด้านบน (<i class="bi bi-cash-coin"></i> บัญชีแผนก ▾) มีเมนูย่อย: <strong>ภาพรวมและรายการ</strong> · <strong>บันทึกรายรับ</strong> · <strong>บันทึกรายจ่าย</strong> · รายการรายรับ · รายการรายจ่าย · ส่งออก CSV (ปีงบประมาณนี้) · <strong>หมวดและการตั้งค่า</strong> — ในหน้าบัญชีทุกหน้ามีแถบเมนูย่อยเดียวกันด้านบนให้สลับหน้าได้ทันที (เมนูบันทึกจะไม่แสดงถ้าไม่มีสิทธิ์บันทึก)</p>
      <ul>
        <li><strong>บันทึก:</strong> กด <span class="badge text-bg-success">+ รายรับ</span> หรือ <span class="badge text-bg-danger">− รายจ่าย</span> กรอกวันที่ จำนวนเงิน หมวด รายการ เลขที่ใบเสร็จ ผู้รับ/ผู้จ่ายเงิน และแนบหลักฐาน (ใบเสร็จ/ภาพถ่าย/PDF)</li>
        <li><strong>สรุป:</strong> ตาม<strong>ปีงบประมาณ</strong> (1 ต.ค. – 30 ก.ย.) มียอดยกมา รายรับ รายจ่าย คงเหลือ สรุปรายเดือนพร้อมยอดสะสม และแยกตามหมวด</li>
        <li><strong>ค้นหา/รายงาน:</strong> กรองตามเดือน ประเภท หมวด คำค้น · ส่งออก CSV · พิมพ์รายงาน</li>
        <li><strong>แชร์ไป LINE:</strong> กดปุ่ม <span class="badge text-white" style="background:#06C755"><i class="bi bi-line"></i> แชร์ LINE</span> ในหน้าภาพรวม เลือกกลุ่มไลน์ ระบบส่งสรุปยอดยกมา รายรับ รายจ่าย คงเหลือของปีงบประมาณที่เลือก พร้อมลิงก์ (ลิงก์เปิดดูรายละเอียดได้เฉพาะครูในแผนกที่เข้าสู่ระบบ) · หรือกด <i class="bi bi-clipboard"></i> คัดลอกข้อความไปวางในแชตเอง</li>
      </ul>
      <h6>สิทธิ์ในบัญชีแผนก</h6>
      <ul class="mb-0">
        <li><strong>ผู้รับผิดชอบบัญชี</strong> (ผู้ดูแลระบบแต่งตั้ง): บันทึก แก้ไข ลบได้ทุกรายการ และจัดการหมวดที่ปุ่ม <i class="bi bi-gear"></i></li>
        <li><strong>ครูทุกคนในแผนก:</strong> ดูได้ทั้งหมด <strong>บันทึกรายรับ-รายจ่ายได้เสมอ</strong> และ<strong>แก้ไขรายการเก่าได้ทุกรายการ</strong> กรณีบันทึกผิด (กดไอคอน <i class="bi bi-pencil"></i> ที่รายการ แก้แล้วกดบันทึก ระบบแสดง "แก้ไขล่าสุดโดย" ไว้ในรายการ) · ลบได้เฉพาะรายการที่ตนบันทึก</li>
        <li><strong>แผนกที่ยังไม่มีผู้รับผิดชอบบัญชี:</strong> ครูทุกคนจัดการหมวดได้ด้วย</li>
      </ul>
    </div></section>

    <!-- 9 -->
    <section id="pdpa" class="card shadow-sm mb-4"><div class="card-body p-4">
      <h4><i class="bi bi-shield-lock me-2"></i>9. ข้อมูลส่วนบุคคล (PDPA) <?= $role('ทุกคน', 'สมาชิก') ?></h4>
      <ul class="mb-0">
        <li>อ่าน<?= $link('privacy.php', 'นโยบายคุ้มครองข้อมูลส่วนบุคคล') ?>ได้จากลิงก์ท้ายทุกหน้า · เมื่อวิทยาลัยปรับปรุงนโยบาย ระบบจะขอให้ยืนยันอีกครั้ง</li>
        <li>เมนูชื่อของคุณ → <?= $link('my-data.php', 'ข้อมูลส่วนบุคคลของฉัน') ?>: ดูข้อมูลที่ระบบเก็บ ประวัติความยินยอม ประวัติการใช้งาน และ<strong>ดาวน์โหลดข้อมูลของตนเป็นไฟล์ JSON</strong></li>
        <li>ยื่นคำร้องใช้สิทธิ์ (ขอสำเนา แก้ไข ลบ คัดค้าน ระงับการใช้ ขอรับข้อมูล) และติดตามสถานะ วิทยาลัยดำเนินการภายใน 30 วัน</li>
        <li>ถอนความยินยอมได้ ซึ่งจะออกจากระบบทันทีและส่งคำร้องให้ผู้ดูแลพิจารณาลบข้อมูล</li>
      </ul>
    </div></section>

    <!-- 10 -->
    <section id="staff" class="card shadow-sm mb-4"><div class="card-body p-4">
      <h4><i class="bi bi-person-workspace me-2"></i>10. สำหรับเจ้าหน้าที่ <?= $role('เจ้าหน้าที่', 'ผู้ดูแลระบบ') ?></h4>
      <p>เมนูชื่อของคุณ → <strong>ระบบหลังบ้าน</strong></p>
      <ul class="mb-0">
        <li><strong>แดชบอร์ด:</strong> สถิติเอกสาร รายการรออนุมัติ และการออกเลขแยกตามแผนก</li>
        <li><strong>จัดการเอกสาร:</strong> กรองสถานะ <span class="badge text-bg-warning">รออนุมัติ</span> ติ๊กหลายรายการแล้ว อนุมัติ / ไม่อนุมัติ / ปรับการเผยแพร่ / ลบ ในครั้งเดียว</li>
        <li><strong>รายงานไปราชการ:</strong> ดูรายงานทั้งหมด และดูว่าคำสั่งใดยังไม่มีรายงาน</li>
        <li><strong>คลังรูปภาพ:</strong> อนุมัติลิงก์ที่สมาชิกส่ง (ตัวเลขสีเหลืองที่เมนู = จำนวนรออนุมัติ)</li>
        <li><strong>ทะเบียนเลขหนังสือ:</strong> ดูทุกแผนก ยกเลิก/คืนสถานะเลข กำหนดเลขเริ่มต้น และส่งออก CSV</li>
        <li><strong>หมวดหมู่เอกสาร:</strong> เพิ่ม/แก้/ลบหมวดย่อย และเปิดใช้รายงานการไปราชการกับหมวดที่ต้องการ</li>
      </ul>
    </div></section>

    <!-- 11 -->
    <section id="admin" class="card shadow-sm mb-4"><div class="card-body p-4">
      <h4><i class="bi bi-gear me-2"></i>11. สำหรับผู้ดูแลระบบ <?= $role('ผู้ดูแลระบบ') ?></h4>
      <div class="table-responsive"><table class="table table-sm table-bordered small mb-0">
        <thead class="table-light"><tr><th style="width:190px">เมนูหลังบ้าน</th><th>ใช้ทำอะไร</th></tr></thead>
        <tbody>
          <tr><td>สมาชิก</td><td>อนุมัติสมาชิกใหม่ กำหนดสิทธิ์ แผนก ระงับบัญชี รีเซ็ตรหัสผ่าน เปลี่ยน/ลบภาพโปรไฟล์ และยกเลิกการเชื่อม Google</td></tr>
          <tr><td>ประเภทเอกสาร</td><td>เพิ่มประเภทใหม่ กำหนดไอคอน สี และช่องในฟอร์ม (วิชา ภาคเรียน ออกเลขคำสั่ง ชื่อช่องบุคคล) · ลบได้เมื่อไม่มีเอกสาร</td></tr>
          <tr><td>แผนกวิชา / ฝ่าย</td><td>รหัสย่อใช้ในเลขหนังสือ เช่น ชฟ · ลบไม่ได้เมื่อมีเลขหนังสือหรือข้อมูลบัญชี</td></tr>
          <tr><td>ผู้รับผิดชอบบัญชีแผนก</td><td>แต่งตั้ง/ยกเลิกผู้รับผิดชอบบัญชีของแต่ละแผนก (ต้องสังกัดแผนกนั้น)</td></tr>
          <tr><td>ปีการศึกษา</td><td>เพิ่มปี กำหนด<strong>ปีปัจจุบัน</strong> (ค่าเริ่มต้นในฟอร์ม) · เปลี่ยนเลขปีแล้วเอกสารจะเปลี่ยนตาม</td></tr>
          <tr><td>ตั้งค่าระบบ</td><td>ชื่อระบบ สโลแกน ชื่อวิทยาลัย <strong>URL ของระบบ</strong> URL เว็บวิทยาลัย รูปแบบเลขหนังสือ ขนาดไฟล์ การรับสมัคร · <strong>เข้าสู่ระบบด้วย Google</strong> · <strong>จัดเก็บไฟล์ใน Google Drive</strong> (ดูหัวข้อ 12)</td></tr>
          <tr><td>PDPA ข้อมูลส่วนบุคคล</td><td>จัดการคำร้อง (ครบกำหนด 30 วัน) ลบข้อมูลบัญชีแบบ anonymize แก้ไข/เผยแพร่นโยบายฉบับใหม่ ตั้งข้อมูล DPO และระยะเก็บประวัติ</td></tr>
          <tr><td>ตรวจสอบระบบ</td><td>ตรวจ PHP ฐานข้อมูล โฟลเดอร์ การสำรองข้อมูล ความปลอดภัย ไฟล์หาย Google Drive และไฟล์โปรแกรมที่ถูกแก้ไข (ดูหัวข้อ 14)</td></tr>
          <tr><td>สำรอง / กู้คืนข้อมูล</td><td>สำรองแบบเต็มหรือเฉพาะฐานข้อมูล ส่งสำเนาไป Google Drive อัตโนมัติ ดาวน์โหลด ตั้งสำรองอัตโนมัติ และกู้คืน</td></tr>
          <tr><td>บันทึกการใช้งาน</td><td>ประวัติการทำรายการทั้งหมด (ลบอัตโนมัติตามระยะเวลาที่ตั้งใน PDPA) · ค้นหา <code>gdrive_error</code> เพื่อดูปัญหา Google Drive</td></tr>
        </tbody>
      </table></div>
    </div></section>

    <!-- 12 -->
    <section id="google" class="card shadow-sm mb-4"><div class="card-body p-4">
      <h4><i class="bi bi-google me-2"></i>12. ตั้งค่า Google (เข้าสู่ระบบ / Drive) <?= $role('ผู้ดูแลระบบ') ?></h4>
      <h6>ขั้นที่ 1 — Google Cloud Console (ทำครั้งเดียว)</h6>
      <ol>
        <li>เปิด <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console.cloud.google.com</a> สร้างหรือเลือกโปรเจกต์</li>
        <li><strong>APIs &amp; Services → Library</strong> ค้นหา <strong>Google Drive API</strong> กด Enable</li>
        <li><strong>OAuth consent screen</strong> เลือก <strong>Internal</strong> (บัญชี @วิทยาลัย — สิทธิ์ไม่หมดอายุ) กรอกชื่อแอปและอีเมล</li>
        <li><strong>Credentials → Create credentials → OAuth client ID</strong> ประเภท <strong>Web application</strong></li>
        <li><strong>Authorized redirect URIs</strong> ใส่: <code><?= e(google_redirect_uri()) ?></code> แล้วกด Create คัดลอก <strong>Client ID</strong> และ <strong>Client secret</strong></li>
      </ol>
      <h6>ขั้นที่ 2 — เข้าสู่ระบบด้วย Google</h6>
      <ol>
        <li>หลังบ้าน → ตั้งค่าระบบ → การ์ด <strong>เข้าสู่ระบบด้วย Google</strong> ใส่ Client ID / Secret</li>
        <li>(แนะนำ) จำกัดโดเมนอีเมลของวิทยาลัย และเลือกว่าจะให้สมัครด้วย Google ได้หรือไม่</li>
        <li>กด <strong>ทดสอบ Client ID / Secret</strong> ต้องขึ้นว่าถูกต้อง แล้วเปิดสวิตช์และกดบันทึก</li>
      </ol>
      <h6>ขั้นที่ 3 — จัดเก็บไฟล์ใน Google Drive</h6>
      <ol>
        <li>สร้างโฟลเดอร์ <strong><?= e($root) ?></strong> ใน Google Drive ของบัญชีกลางของวิทยาลัย (โฟลเดอร์ย่อย uploads และอื่น ๆ ระบบสร้างให้เอง) คัดลอกรหัสจากลิงก์ <code>drive.google.com/drive/folders/<strong>รหัส</strong></code></li>
        <li>การ์ด <strong>จัดเก็บไฟล์ใน Google Drive</strong> → กด <strong>เชื่อมต่อ Google Drive</strong> เลือกบัญชีและอนุญาต</li>
        <li>วาง <strong>Folder ID</strong> กด <strong>ทดสอบการเชื่อมต่อ</strong> ระบบตรวจทีละขั้น (Client ID/Secret → สิทธิ์เข้าถึง Drive → โฟลเดอร์ → ทดสอบอัปโหลด → โฟลเดอร์ย่อย) และบอกข้อที่ไม่ผ่านพร้อมวิธีแก้ (สร้างโฟลเดอร์ย่อยที่ยังไม่มีให้เอง)</li>
        <li>ไฟล์ที่อยู่ใน Drive แล้วแต่อยู่ผิดโฟลเดอร์ กด <strong>จัดไฟล์ใน Drive เข้าโฟลเดอร์ตามประเภท</strong> (รวมสำเนาไฟล์สำรองข้อมูล)</li>
        <li>ถ้ายังมีไฟล์เดิมเก็บในเซิร์ฟเวอร์ ย้ายขึ้น Drive ด้วยคำสั่ง <code>C:
mpp\php\php.exe <?= e($appPath) ?>	ools\move-to-drive.php</code> บนเครื่องเซิร์ฟเวอร์ (ไม่มีปุ่มในหน้าตั้งค่า)</li>
        <li>เปิดสวิตช์ <strong>เก็บไฟล์ที่อัปโหลดใน Google Drive เท่านั้น</strong> แล้วกดบันทึก</li>
      </ol>
      <div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>เมื่อเปิด "Drive เท่านั้น" ถ้าการเชื่อมต่อมีปัญหา (สิทธิ์หมดอายุ/ถูกยกเลิก) จะอัปโหลดไฟล์ไม่ได้จนกว่าจะกด <strong>เชื่อมต่อใหม่</strong> · อย่าลบ ย้าย หรือเปลี่ยนชื่อไฟล์ในโฟลเดอร์ <?= e($root) ?> ด้วยตนเอง</div>
    </div></section>

    <!-- 13 -->
    <section id="storage" class="card shadow-sm mb-4"><div class="card-body p-4">
      <h4><i class="bi bi-folder2-open me-2"></i>13. ตำแหน่งจัดเก็บไฟล์</h4>
      <p class="small">เมื่อเปิด "เก็บไฟล์ใน Google Drive เท่านั้น" ไฟล์จะถูกจัดเก็บดังนี้ (ระบบสร้างโฟลเดอร์ที่ยังไม่มีให้เอง และใช้โฟลเดอร์เดิมเสมอ ไม่สร้างซ้ำ)</p>
      <div class="table-responsive"><table class="table table-sm table-bordered small">
        <thead class="table-light"><tr><th>ประเภทไฟล์</th><th>ที่จัดเก็บ</th></tr></thead>
        <tbody>
          <tr><td>คำสั่งราชการ</td><td><i class="bi bi-google text-success"></i> <code><?= e($root . '/' . storage_category_path('doc:orders')) ?></code></td></tr>
          <tr><td>แผนการสอน, โครงการสอน</td><td><i class="bi bi-google text-success"></i> <code><?= e($root . '/' . storage_category_path('doc:lesson-plans')) ?></code></td></tr>
          <tr><td>หนังสือเรียน / ตำรา</td><td><i class="bi bi-google text-success"></i> <code><?= e($root . '/' . storage_category_path('doc:textbooks')) ?></code></td></tr>
          <tr><td>เกียรติบัตร</td><td><i class="bi bi-google text-success"></i> <code><?= e($root . '/' . storage_category_path('doc:certificates')) ?></code></td></tr>
          <tr><td>ใบเสร็จ (หลักฐานบัญชีแผนก)</td><td><i class="bi bi-google text-success"></i> <code><?= e($root) ?>/<?= e(storage_category_path('receipt')) ?></code></td></tr>
          <tr><td>ภาพประกอบและไฟล์แนบรายงานไปราชการ</td><td><i class="bi bi-google text-success"></i> <code><?= e($root . '/' . storage_category_path('trip-reports')) ?></code></td></tr>
          <tr><td>เอกสารประเภทอื่น ๆ ทั้งหมด</td><td><i class="bi bi-google text-success"></i> <code><?= e($root . '/' . storage_category_path('')) ?></code></td></tr>
          <tr><td>ไฟล์สำรองข้อมูล (.zip)</td><td><i class="bi bi-google text-success"></i> <code><?= e(storage_display_path('backups')) ?></code> + <i class="bi bi-hdd"></i> เซิร์ฟเวอร์ <code>backups/</code> (ใช้กู้คืน)</td></tr>
          <tr><td>ภาพโปรไฟล์</td><td><i class="bi bi-hdd"></i> เซิร์ฟเวอร์ <code>uploads/docs/avatars</code></td></tr>
          <tr><td>คลังรูปภาพ</td><td>เก็บเฉพาะลิงก์ในฐานข้อมูล (ภาพอยู่ที่ Google Photos/Drive ของผู้ส่ง)</td></tr>
          <tr><td>ข้อมูลทั้งหมดในระบบ</td><td><i class="bi bi-database"></i> ฐานข้อมูล MySQL <code><?= e(DB_NAME) ?></code></td></tr>
        </tbody>
      </table></div>
      <p class="small text-muted mb-0">ไฟล์ทุกที่เป็นส่วนตัว ผู้ใช้ดาวน์โหลดผ่านระบบซึ่งตรวจสิทธิ์ทุกครั้ง · เปิดโฟลเดอร์ <code>uploads/</code> <code>backups/</code> ตรงจากเว็บไม่ได้ (403)</p>
    </div></section>

    <!-- 14 -->
    <section id="maintain" class="card shadow-sm mb-4"><div class="card-body p-4">
      <h4><i class="bi bi-tools me-2"></i>14. การดูแลรักษาระบบ <?= $role('ผู้ดูแลระบบ') ?></h4>
      <h6>ตรวจสอบระบบ (แนะนำสัปดาห์ละครั้ง)</h6>
      <ul>
        <li>หลังบ้าน → <strong>ตรวจสอบระบบ</strong> ผล <i class="bi bi-check-circle-fill text-success"></i> ผ่าน <i class="bi bi-exclamation-triangle-fill text-warning"></i> เตือน <i class="bi bi-x-octagon-fill text-danger"></i> ไม่ผ่าน</li>
        <li>หลังอัปเดตไฟล์ระบบทุกครั้ง กด <strong>บันทึกค่าอ้างอิง</strong> เพื่อให้ตรวจจับไฟล์ที่ถูกแก้ไข/ลบ/แอบเพิ่มได้</li>
        <li>"ไฟล์สคริปต์แปลกปลอมในโฟลเดอร์อัปโหลด" ไม่ผ่าน = อาจถูกเจาะระบบ ให้สำรองข้อมูล ลบไฟล์นั้น และเปลี่ยนรหัสผ่านผู้ดูแลทันที</li>
      </ul>
      <h6>สำรองและกู้คืนข้อมูล</h6>
      <ul>
        <li>หลังบ้าน → <strong>สำรอง / กู้คืนข้อมูล</strong> → สำรองแบบเต็มหรือเฉพาะฐานข้อมูล · ระบบส่งสำเนาไป <code><?= e(storage_display_path('backups')) ?></code> อัตโนมัติ (ป้าย <span class="badge text-bg-success"><i class="bi bi-google"></i> Drive</span>)</li>
        <li>ตั้ง "สำรองอัตโนมัติทุก 1 วัน" หรือ Windows Task Scheduler เรียก <code>tools\backup-task.bat</code> · จำนวนไฟล์ที่เก็บ (ทั้งเซิร์ฟเวอร์และ Drive) ตามค่า "เก็บไฟล์สำรองล่าสุด"</li>
        <li>เข้าหลังบ้านได้: เลือกไฟล์สำรองแล้วกด <i class="bi bi-arrow-counterclockwise"></i> กู้คืน (ระบบสำรองสถานะปัจจุบันไว้ให้ก่อน)</li>
        <li>เข้าเว็บไม่ได้: รัน <code>C:\xampp\php\php.exe <?= e($appPath) ?>\tools\restore.php ชื่อไฟล์.zip</code></li>
      </ul>
      <h6>เครื่องมือบรรทัดคำสั่ง (บนเครื่องเซิร์ฟเวอร์)</h6>
      <div class="table-responsive"><table class="table table-sm table-bordered small">
        <tbody>
          <tr><td style="width:220px"><code>tools\check.php</code></td><td>ตรวจสอบระบบ</td></tr>
          <tr><td><code>tools\backup.php --full</code></td><td>สำรองข้อมูล (ส่งสำเนาไป Drive ด้วย)</td></tr>
          <tr><td><code>tools\restore.php</code></td><td>แสดงรายการ / กู้คืนไฟล์สำรอง</td></tr>
          <tr><td><code>tools\move-to-drive.php</code></td><td>ย้ายไฟล์เดิมขึ้น Drive และจัดเข้าโฟลเดอร์ตามประเภท</td></tr>
          <tr><td><code>tools\reset-admin.php</code></td><td>ตั้งรหัสผ่านผู้ดูแลระบบใหม่ / สร้างผู้ดูแลเพิ่ม (กรณีลืมรหัสผ่าน)</td></tr>
        </tbody>
      </table></div>
      <h6>อัปเดตไฟล์ระบบขึ้นเซิร์ฟเวอร์</h6>
      <ul class="mb-0">
        <li>สำรองข้อมูลก่อนทุกครั้ง แล้วอัปโหลดเฉพาะไฟล์ที่แก้ไข</li>
        <li><strong>ห้ามเขียนทับ</strong> โฟลเดอร์ <code>uploads/</code> <code>backups/</code> และไฟล์ <code>includes/installed.lock</code> · <code>includes/config.php</code> ให้แก้เฉพาะบรรทัด <code>APP_VERSION</code> ถ้าค่าฐานข้อมูลต่างกัน</li>
        <li>เปิดเว็บหนึ่งครั้ง ระบบปรับโครงสร้างฐานข้อมูลให้อัตโนมัติ แล้วกด "บันทึกค่าอ้างอิง" ในหน้าตรวจสอบระบบ</li>
      </ul>
    </div></section>

    <!-- 15 -->
    <section id="faq" class="card shadow-sm mb-4"><div class="card-body p-4">
      <h4><i class="bi bi-question-circle me-2"></i>15. ปัญหาที่พบบ่อย</h4>
      <div class="accordion" id="faqAcc">
        <?php $faq = [
            'ลืมรหัสผ่าน' => 'สมาชิก: ติดต่อผู้ดูแลระบบให้ตั้งรหัสผ่านใหม่ที่ หลังบ้าน → สมาชิก → แก้ไข · ผู้ดูแลระบบลืมรหัสผ่าน: รัน tools\\reset-admin.php บนเครื่องเซิร์ฟเวอร์',
            'สมัครแล้วเข้าสู่ระบบไม่ได้' => 'บัญชีอาจรอการอนุมัติหรือถูกระงับ ติดต่อผู้ดูแลระบบ',
            'เข้าสู่ระบบด้วย Google ไม่ได้' => 'ตรวจว่าใช้บัญชีโดเมนที่วิทยาลัยกำหนด · ถ้าขึ้น redirect_uri_mismatch ให้ผู้ดูแลตรวจ Authorized redirect URI ใน Google Cloud ต้องตรงกับที่หน้าตั้งค่าแสดงทุกตัวอักษร · มีบัญชีเดิมอยู่แล้วให้เข้าสู่ระบบด้วยชื่อผู้ใช้แล้วกด "เชื่อมบัญชี Google"',
            'อัปโหลดไม่ได้: บันทึกไฟล์ลง Google Drive ไม่สำเร็จ' => 'ลองใหม่อีกครั้ง หากยังไม่ได้ให้ผู้ดูแลระบบไปที่ ตั้งค่าระบบ → การ์ด Google Drive กด "ทดสอบการเชื่อมต่อ" แล้วแก้ตามข้อที่ขึ้น ✗ · ถ้าขึ้น unauthorized_client (เปลี่ยน Client ID แล้ว) หรือ invalid_grant (สิทธิ์หมดอายุ) ให้กด "เชื่อมต่อ Google Drive ใหม่" ด้วยบัญชีเจ้าของโฟลเดอร์ DocsHub · ถ้าขึ้น invalid_client ให้วาง Client ID และ Secret ใหม่จาก client ตัวเดียวกัน แล้วกด "ทดสอบ Client ID / Secret"',
            'ไฟล์ใหญ่เกินอัปโหลดไม่ได้' => 'ตรวจขนาดไฟล์ไม่เกินที่ระบบกำหนด (แสดงใต้ช่องเลือกไฟล์) หากต้องเพิ่ม ผู้ดูแลแก้ที่ ตั้งค่าระบบ และ upload_max_filesize/post_max_size ใน php.ini',
            'ดาวน์โหลดเอกสารแล้วขึ้น "ไม่พบไฟล์"' => 'ไฟล์อาจถูกลบหรือย้ายใน Google Drive ด้วยตนเอง หรือการเชื่อมต่อ Drive มีปัญหา ให้ผู้ดูแลตรวจหน้าตรวจสอบระบบ และดูถังขยะของ Drive (กู้คืนได้ใน 30 วัน)',
            'ค้นหาแล้วไม่พบเอกสาร' => 'เอกสารอาจยังรอเจ้าหน้าที่อนุมัติ (ดูได้ที่ เอกสารของฉัน) หรือเป็นเอกสารเฉพาะสมาชิกซึ่งต้องเข้าสู่ระบบก่อน · ลองใช้คำสั้นลง หรือค้นด้วยเลขที่ รหัสวิชา ชื่อแผนก หรือปีการศึกษา · ตรวจว่าช่องประเภทข้างคำค้นเลือก "ทุกประเภท"',
            'ไฟล์ใน Google Drive อยู่ผิดโฟลเดอร์ หรือมีโฟลเดอร์ซ้ำ' => 'ผู้ดูแลระบบกด "จัดไฟล์ใน Drive เข้าโฟลเดอร์ตามประเภท" ในหน้าตั้งค่า ระบบย้ายไฟล์ไปโฟลเดอร์ที่ถูกต้อง (ใช้โฟลเดอร์ที่สร้างก่อนเสมอเมื่อชื่อซ้ำ) แล้วจึงลบโฟลเดอร์ที่ว่างใน Drive',
            'หาเมนูบัญชีแผนกไม่เจอ / ใช้บัญชีแผนกไม่ได้' => 'เมนู บัญชีแผนก อยู่แถบเมนูด้านบน (หลังเข้าสู่ระบบ) บนมือถือให้กดปุ่ม ☰ ก่อน · ถ้ากดแล้วขึ้นว่ายังไม่ได้ระบุแผนกวิชา ให้ผู้ดูแลระบบกำหนดแผนกให้ที่ หลังบ้าน → สมาชิก → แก้ไข (บัญชีแผนกเห็นได้เฉพาะครูที่สังกัดแผนกนั้น ผู้ดูแลระบบก็ต้องกำหนดแผนกของตนเองก่อน)',
            'บันทึกรายการบัญชีผิด จะแก้อย่างไร' => 'เมนู บัญชีแผนก → ภาพรวมและรายการ ค้นหารายการนั้น กดไอคอนดินสอ แก้ไขแล้วกดบันทึก (ครูทุกคนในแผนกแก้ได้ทุกรายการ) · ถ้าต้องการลบ ให้ผู้บันทึกรายการหรือผู้รับผิดชอบบัญชีเป็นผู้ลบ',
            'ระบบให้ยืนยันนโยบายคุ้มครองข้อมูลอีกครั้ง' => 'วิทยาลัยปรับปรุงนโยบายเป็นฉบับใหม่ อ่านแล้วกดยอมรับเพื่อใช้งานต่อ',
            'ภาพจากมือถือกลับหัว/ตะแคง' => 'ระบบหมุนภาพตามข้อมูลกล้องให้อัตโนมัติ หากยังผิด ให้หมุนภาพในมือถือแล้วบันทึกก่อนอัปโหลด',
            'เลขหนังสือออกผิด' => 'แจ้งเจ้าหน้าที่ให้ยกเลิกเลขนั้นในทะเบียน แล้วออกเลขใหม่',
            'ลิงก์อัลบั้มไม่แสดงในคลังรูปภาพ' => 'ลิงก์รอเจ้าหน้าที่อนุมัติ ดูสถานะได้ท้ายหน้าคลังรูปภาพ หากไม่อนุมัติจะแสดงเหตุผล',
        ]; $i = 0; foreach ($faq as $q => $a): $i++; ?>
          <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>"><?= e($q) ?></button></h2>
            <div id="faq<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#faqAcc"><div class="accordion-body small"><?= e($a) ?></div></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div></section>

    <div class="text-center small text-muted mb-4">คู่มือการใช้งาน <?= e(setting('site_name')) ?> เวอร์ชัน <?= e(APP_VERSION) ?> · พัฒนาระบบโดย <?= e(APP_DEVELOPER . ' ' . APP_DEVELOPER_DEPT) ?></div>
  </article>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
