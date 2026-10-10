<?php
require_once __DIR__ . '/../inc/data.php';
header('Content-Type: application/json; charset=utf-8');

$u = current_user();
if (!$u) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Login required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST required']);
    exit;
}

verify_csrf();

try {
    $body = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $party = trim((string)($body['party'] ?? ''));
    $city = trim((string)($body['city'] ?? ''));
    $contact = trim((string)($body['contact'] ?? ''));
    $email = trim((string)($body['email'] ?? ''));
    $saved = save_party_contact($party, $city, $contact, $email, (int)$u['id']);
    echo json_encode(['ok' => true, 'contact' => $saved], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
