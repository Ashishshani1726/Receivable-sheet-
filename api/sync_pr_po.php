<?php
require_once __DIR__ . '/../inc/data.php';
header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Login required. Open the portal and login first.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'pull';
try {
    if ($action === 'status') {
        $src = load_procurement_pr_po_source(false);
        echo json_encode([
            'ok' => true,
            'rows' => count($src['rows'] ?? []),
            'sync' => $src['liveSync'] ?? null,
            'sourceFile' => $src['sourceFile'] ?? '',
            'sheetName' => $src['sheetName'] ?? '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Admin only']);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') verify_csrf();
    $src = force_procurement_pr_po_sync();
    db()->prepare("INSERT INTO audit_log(user_id, action, detail) VALUES(?, 'pr_po_google_sync', ?)")
        ->execute([(int)$user['id'], 'Rows: ' . count($src['rows'] ?? []) . ' | Sheet: ' . (string)($src['sheetName'] ?? '')]);
    echo json_encode([
        'ok' => true,
        'rows' => count($src['rows'] ?? []),
        'sync' => $src['liveSync'] ?? null,
        'sourceFile' => $src['sourceFile'] ?? '',
        'sheetName' => $src['sheetName'] ?? '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
