<?php
/** สถิติเริ่มนับหลังติดตั้ง: เซสชัน/วัน ไม่ใช่จำนวนบุคคลจริง ไม่เก็บ IP หรือ URL */
function traffic_identity(): string
{
    if (empty($_SESSION['traffic_id'])) {
        $_SESSION['traffic_id'] = bin2hex(random_bytes(32));
    }
    return hash('sha256', $_SESSION['traffic_id']);
}

function traffic_record(bool $pageview = true): void
{
    global $db;
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $hash = traffic_identity();
    $day = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    $uid = current_user()['id'] ?? null;
    if (!$pageview) {
        db_exec('UPDATE traffic_sessions SET last_seen = ?, user_id = ? WHERE visitor_hash = ? AND visit_date = ?',
            'siss', [$now, $uid, $hash, $day]);
        return;
    }
    $db->begin_transaction();
    try {
        db_exec('INSERT IGNORE INTO traffic_sessions (visitor_hash, visit_date, user_id, last_seen) VALUES (?, ?, ?, ?)',
            'ssis', [$hash, $day, $uid, $now]);
        $newVisit = $db->affected_rows === 1 ? 1 : 0;
        db_exec('UPDATE traffic_sessions SET last_seen = ?, user_id = ? WHERE visitor_hash = ? AND visit_date = ?',
            'siss', [$now, $uid, $hash, $day]);
        db_exec('INSERT INTO traffic_daily (visit_date, visits, pageviews) VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE visits = visits + ?, pageviews = pageviews + 1', 'sii', [$day, $newVisit, $newVisit]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    // เก็บสถานะชั่วคราวแค่วันนี้/เมื่อวาน สถิติรวมรายวันคงอยู่
    db_exec('DELETE FROM traffic_sessions WHERE visit_date < ?', 's', [date('Y-m-d', strtotime('-1 day'))]);
}

function traffic_stats(): array
{
    $online = db_row('SELECT COUNT(DISTINCT user_id) AS members,
        COUNT(DISTINCT CASE WHEN user_id IS NULL THEN visitor_hash END) AS guests
        FROM traffic_sessions WHERE last_seen >= ?', 's', [date('Y-m-d H:i:s', time() - 300)]);
    $total = db_row('SELECT COALESCE(SUM(visits), 0) AS visits, COALESCE(SUM(pageviews), 0) AS pageviews FROM traffic_daily');
    return [
        'online' => (int)$online['members'] + (int)$online['guests'],
        'members' => (int)$online['members'], 'guests' => (int)$online['guests'],
        'today' => (int)db_val('SELECT visits FROM traffic_daily WHERE visit_date = ?', 's', [date('Y-m-d')]),
        'visits' => (int)$total['visits'], 'pageviews' => (int)$total['pageviews'],
    ];
}
