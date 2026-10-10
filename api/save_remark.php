<?php
require_once __DIR__ . '/../inc/data.php';
header('Content-Type: application/json; charset=utf-8');

$u = current_user();
if (!$u) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Login required']);
    exit;
}
require_permission('remarks');
verify_csrf();

try {
    ensure_party_remark_history_table();

    $body = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $party = trim((string)($body['party'] ?? ''));
    $remark = trim((string)($body['remark'] ?? ''));
    if ($party === '' || mb_strlen($party) > 191) throw new RuntimeException('Invalid party');

    $visible = filtered_source_for_user(load_source_data(), $u);
    $allowed = [];
    foreach (($visible['rows'] ?? []) as $r) {
        $p = trim((string)(($r['v'] ?? [])[2] ?? ''));
        if ($p !== '') $allowed[$p] = true;
    }
    if ($u['role'] !== 'admin' && !isset($allowed[$party])) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Party not in your permitted data']);
        exit;
    }

    $stmt = db()->prepare('SELECT remark FROM party_remarks WHERE party_name=?');
    $stmt->execute([$party]);
    $oldRemark = (string)($stmt->fetchColumn() ?: '');

    $changed = ($oldRemark !== $remark);
    $history = null;

    if ($changed) {
        if ($remark === '') {
            db()->prepare('DELETE FROM party_remarks WHERE party_name=?')->execute([$party]);
            $action = 'clear';
        } else {
            $s = db()->prepare('INSERT INTO party_remarks(party_name,remark,updated_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE remark=VALUES(remark),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP');
            $s->execute([$party, $remark, $u['id']]);
            $action = $oldRemark === '' ? 'create' : 'update';
        }

        $h = db()->prepare('INSERT INTO party_remark_history(party_name, old_remark, new_remark, action, changed_by) VALUES(?,?,?,?,?)');
        $h->execute([$party, $oldRemark, $remark, $action, $u['id']]);
        $historyId = (int)db()->lastInsertId();
        $history = [
            'id' => $historyId,
            'old' => $oldRemark,
            'new' => $remark,
            'action' => $action,
            'by' => $u['full_name'] ?: $u['username'],
            'at' => date('Y-m-d H:i:s'),
        ];
    }

    echo json_encode(['ok' => true, 'changed' => $changed, 'history' => $history], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
