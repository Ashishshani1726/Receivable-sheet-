<?php
require_once __DIR__ . '/../inc/data.php';
header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Login required']);
    exit;
}
if ($user['role'] !== 'admin' && !has_permission('bills')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Bill register permission required']);
    exit;
}
verify_csrf();

function bill_norm_header(string $h): string {
    return strtolower(preg_replace('/\s+/u', ' ', trim($h)));
}

function bill_find_header_index(array $headers, array $names): ?int {
    $want = array_map('bill_norm_header', $names);
    foreach ($headers as $i => $h) {
        if (in_array(bill_norm_header((string)$h), $want, true)) return (int)$i;
    }
    return null;
}

function bill_ensure_header(array &$source, string $name): int {
    if (!isset($source['headers']) || !is_array($source['headers'])) $source['headers'] = [];
    $idx = bill_find_header_index($source['headers'], [$name, 'Action Point', 'Action / follow-up', 'Follow up', 'Follow-up', 'Followup']);
    if ($idx !== null) return $idx;
    $source['headers'][] = $name;
    $idx = count($source['headers']) - 1;
    foreach ($source['rows'] as &$r) {
        if (!isset($r['v']) || !is_array($r['v'])) $r['v'] = [];
        while (count($r['v']) <= $idx) $r['v'][] = '';
    }
    unset($r);
    return $idx;
}

function bill_update_source_followup(array &$source, int $rowNumber, string $value): array {
    if (!isset($source['rows']) || !is_array($source['rows'])) throw new RuntimeException('Source rows not found.');
    $col = bill_ensure_header($source, 'Action Point');
    foreach ($source['rows'] as &$r) {
        if ((int)($r['row'] ?? 0) !== $rowNumber) continue;
        if (!isset($r['v']) || !is_array($r['v'])) $r['v'] = [];
        while (count($r['v']) <= $col) $r['v'][] = '';
        $old = (string)($r['v'][$col] ?? '');
        $r['v'][$col] = $value;
        unset($r);
        return ['old' => $old, 'column_index' => $col, 'header' => (string)$source['headers'][$col]];
    }
    unset($r);
    throw new RuntimeException('Data row ' . $rowNumber . ' not found. Sync Google Sheet and try again.');
}

function google_sheet_update_cell(int $rowNumber, string $header, string $value): array {
    if (!defined('GOOGLE_SHEET_LIVE') || !GOOGLE_SHEET_LIVE || !defined('GOOGLE_SHEET_API_URL') || trim((string)GOOGLE_SHEET_API_URL) === '') {
        return ['ok' => false, 'message' => 'Google Sheet live mode is not configured.'];
    }
    $payload = [
        'action' => 'updateCell',
        'sheet' => 'Data',
        'row' => $rowNumber,
        'header' => $header,
        'value' => $value,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $body = false; $err = ''; $status = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init(GOOGLE_SHEET_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'BasikReceivablesPortal/1.0',
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false) $err = curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $json, 'timeout' => 25]]);
        $body = @file_get_contents(GOOGLE_SHEET_API_URL, false, $ctx);
        if ($body === false) $err = 'Unable to POST to Google Apps Script from PHP.';
        if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $status = (int)$m[1];
    }
    if ($body === false || ($status && $status >= 400)) {
        return ['ok' => false, 'message' => 'Google write failed' . ($status ? ' (HTTP ' . $status . ')' : '') . ($err ? ': ' . $err : '')];
    }
    try {
        $res = json_decode((string)$body, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Google write response was not JSON. Add the doPost updateCell code in Apps Script.'];
    }
    $success = !empty($res['success']) || !empty($res['ok']);
    return ['ok' => $success, 'message' => $success ? 'Google Sheet updated' : (string)($res['message'] ?? 'Google Sheet did not accept updateCell action.'), 'response' => $res];
}

try {
    $body = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $rowNumber = (int)($body['row'] ?? 0);
    $value = trim((string)($body['value'] ?? ''));
    if ($rowNumber <= 0) throw new RuntimeException('Invalid source row.');
    if (mb_strlen($value) > 5000) throw new RuntimeException('Follow-up text too long.');

    $modeBefore = function_exists('data_source_mode') ? data_source_mode() : 'manual';
    $source = load_source_data();
    $change = bill_update_source_followup($source, $rowNumber, $value);

    $google = ['ok' => false, 'message' => 'Manual source mode'];
    if (defined('GOOGLE_SHEET_LIVE') && GOOGLE_SHEET_LIVE) {
        $google = google_sheet_update_cell($rowNumber, 'Action Point', $value);
    }

    save_source_data($source, (int)$user['id']);
    if (!empty($google['ok'])) {
        if (function_exists('save_google_cache')) save_google_cache($source);
        if ($modeBefore === 'google' && function_exists('set_data_source_mode')) set_data_source_mode('google', (int)$user['id']);
    } else {
        if (function_exists('set_data_source_mode')) set_data_source_mode('manual', (int)$user['id']);
    }

    db()->prepare("INSERT INTO audit_log(user_id, action, detail) VALUES(?, 'bill_followup_update', ?)")
        ->execute([(int)$user['id'], 'Data row: ' . $rowNumber . ' | Old: ' . $change['old'] . ' | New: ' . $value . ' | Google: ' . (!empty($google['ok']) ? 'yes' : 'no')]);

    echo json_encode([
        'ok' => true,
        'row' => $rowNumber,
        'header' => 'Action Point',
        'value' => $value,
        'googleSynced' => !empty($google['ok']),
        'googleMessage' => (string)($google['message'] ?? ''),
        'sourceMode' => !empty($google['ok']) && $modeBefore === 'google' ? 'google' : 'manual',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
