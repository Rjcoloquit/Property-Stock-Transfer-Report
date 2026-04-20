<?php
session_start();
require_once __DIR__ . '/config/rbac.php';

header('Content-Type: application/json; charset=utf-8');
ptr_require_login(true);
ptr_require_page_access('item_search_suggest', true);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/dashboard_inventory_helper.php';

$q = isset($_GET['q']) ? (string) $_GET['q'] : '';

try {
    $pdo = getConnection();
    $suggestions = ptr_dashboard_search_suggestions($pdo, $q, 15);
    echo json_encode($suggestions, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('item_search_suggest.php: ' . $e->getMessage());
    echo json_encode([]);
}
