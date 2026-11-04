<?php
require_once __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
if (empty($_SESSION['csrf']) || empty($_POST['csrf'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}
csrf_check();
require_once __DIR__ . '/includes/traffic.php';
try {
    traffic_record(false);
    echo json_encode(traffic_stats());
} catch (Throwable $ex) {
    error_log('Docs Hub traffic: ' . $ex->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Statistics unavailable']);
}
