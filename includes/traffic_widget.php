<?php
require_once __DIR__ . '/traffic.php';
try {
    traffic_record();
    $traffic = traffic_stats();
} catch (Throwable $ex) {
    error_log('Docs Hub traffic: ' . $ex->getMessage());
    $traffic = null;
}
?>
<?php if ($traffic !== null): ?>
<div class="traffic-widget my-3 p-3 rounded-3 border small" data-traffic-url="<?= e(url('traffic-status.php')) ?>" data-traffic-csrf="<?= e(csrf_token()) ?>">
  <div class="d-flex flex-wrap gap-3 justify-content-center">
    <span><i class="bi bi-circle-fill text-success me-1" aria-hidden="true"></i>ออนไลน์ <strong data-traffic="online"><?= number_format($traffic['online']) ?></strong> คน</span>
    <span>สมาชิก <strong data-traffic="members"><?= number_format($traffic['members']) ?></strong> · ผู้เยี่ยมชม <strong data-traffic="guests"><?= number_format($traffic['guests']) ?></strong></span>
    <span><i class="bi bi-calendar-day me-1" aria-hidden="true"></i>เข้าชมวันนี้ <strong data-traffic="today"><?= number_format($traffic['today']) ?></strong> ครั้ง</span>
    <span><i class="bi bi-bar-chart me-1" aria-hidden="true"></i>เข้าชมสะสม <strong data-traffic="visits"><?= number_format($traffic['visits']) ?></strong> ครั้ง</span>
    <span>เปิดหน้าสะสม <strong data-traffic="pageviews"><?= number_format($traffic['pageviews']) ?></strong> ครั้ง</span>
  </div>
  <div class="text-center mt-2 opacity-75">ออนไลน์ = มีการใช้งานใน 5 นาทีล่าสุด · เข้าชมนับต่อเซสชันต่อวัน · เริ่มนับตั้งแต่เปิดใช้ตัวนับ</div>
</div>
<?php endif; ?>
