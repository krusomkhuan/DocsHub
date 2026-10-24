<?php
/** แถบเมนูย่อยของส่วนบัญชีแผนก ใช้ใน ledger.php / ledger-entry.php / ledger-categories.php (ต้องมี $dept และ $perms) */
$ledgerTab = $ledgerTab ?? 'overview';
$ledgerTabs = ['overview' => ['ledger.php', 'bi-speedometer', 'ภาพรวมและรายการ']];
if ($perms['add']) {
    $ledgerTabs['income']  = ['ledger-entry.php?kind=income', 'bi-plus-circle', 'บันทึกรายรับ'];
    $ledgerTabs['expense'] = ['ledger-entry.php?kind=expense', 'bi-dash-circle', 'บันทึกรายจ่าย'];
}
$ledgerTabs['settings'] = ['ledger-categories.php', 'bi-gear', $perms['manage'] ? 'หมวดและการตั้งค่า' : 'หมวด / ผู้รับผิดชอบ'];
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3 d-print-none">
  <span class="badge text-bg-primary fs-6 fw-normal"><i class="bi bi-cash-coin me-1"></i>บัญชีแผนก · <?= e($dept['name']) ?></span>
  <ul class="nav nav-pills small flex-nowrap overflow-auto">
    <?php foreach ($ledgerTabs as $k => [$href, $ic, $label]): ?>
      <li class="nav-item"><a class="nav-link py-1 text-nowrap<?= $ledgerTab === $k ? ' active' : '' ?>" href="<?= url($href) ?>"><i class="bi <?= $ic ?> me-1"></i><?= e($label) ?></a></li>
    <?php endforeach; ?>
  </ul>
</div>
