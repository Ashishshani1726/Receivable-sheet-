<?php
require_once __DIR__ . '/auth.php';

function template_seed_source(): array {
    $text = file_get_contents(__DIR__ . '/../private/dashboard_template.php');
    if (!preg_match('~<script id="embeddedData" type="application/json">(.*?)</script>~s', $text, $m)) {
        throw new RuntimeException('Seed dashboard data not found.');
    }
    return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
}

function dashboard_db_source(): array {
    $s = db()->prepare("SELECT json_value FROM app_data WHERE data_key='receivables_source' LIMIT 1");
    $s->execute();
    $raw = $s->fetchColumn();
    if (!$raw) return template_seed_source();
    return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
}

function save_source_data(array $data, int $userId): void {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $s = db()->prepare("INSERT INTO app_data(data_key,json_value,updated_by) VALUES('receivables_source',?,?) ON DUPLICATE KEY UPDATE json_value=VALUES(json_value),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP");
    $s->execute([$json, $userId]);
}


function ensure_party_contacts_table(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS party_contacts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        party_name VARCHAR(191) NOT NULL UNIQUE,
        city VARCHAR(191) NULL,
        contact VARCHAR(100) NULL,
        email VARCHAR(191) NULL,
        updated_by INT UNSIGNED NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_party_contact_party (party_name),
        INDEX idx_party_contact_user (updated_by),
        CONSTRAINT fk_party_contact_user FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function load_party_contacts(array $source = []): array {
    ensure_party_contacts_table();
    $rows = db()->query("SELECT party_name, city, contact, email, updated_at FROM party_contacts ORDER BY party_name")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $party = trim((string)($r['party_name'] ?? ''));
        if ($party === '') continue;
        $out[live_key($party)] = [
            'party' => $party,
            'city' => (string)($r['city'] ?? ''),
            'contact' => (string)($r['contact'] ?? ''),
            'email' => (string)($r['email'] ?? ''),
            'updatedAt' => (string)($r['updated_at'] ?? ''),
        ];
    }
    return $out;
}

function save_party_contact(string $party, string $city, string $contact, string $email, int $userId): array {
    ensure_party_contacts_table();
    $party = trim($party);
    $city = trim($city);
    $contact = trim($contact);
    $email = trim($email);
    if ($party === '') throw new RuntimeException('Party name missing.');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Valid email address enter karo.');
    }
    $stmt = db()->prepare("INSERT INTO party_contacts(party_name, city, contact, email, updated_by)
        VALUES(?,?,?,?,?)
        ON DUPLICATE KEY UPDATE city=VALUES(city), contact=VALUES(contact), email=VALUES(email), updated_by=VALUES(updated_by), updated_at=CURRENT_TIMESTAMP");
    $stmt->execute([$party, $city, $contact, $email, $userId]);
    db()->prepare("INSERT INTO audit_log(user_id, action, detail) VALUES(?, 'party_contact_save', ?)")
        ->execute([$userId, 'Party: ' . $party . ' | Contact: ' . $contact . ' | Email: ' . $email . ' | City: ' . $city]);
    return ['party' => $party, 'city' => $city, 'contact' => $contact, 'email' => $email];
}

function data_source_mode(): string {
    if (!defined('GOOGLE_SHEET_LIVE') || !GOOGLE_SHEET_LIVE) return 'manual';
    try {
        $s = db()->prepare("SELECT json_value FROM app_data WHERE data_key='receivables_source_mode' LIMIT 1");
        $s->execute();
        $raw = $s->fetchColumn();
        if (!$raw) return 'google';
        $mode = json_decode($raw, true);
        return in_array($mode, ['google','manual'], true) ? $mode : 'google';
    } catch (Throwable $e) {
        return 'google';
    }
}

function set_data_source_mode(string $mode, ?int $userId = null): void {
    if (!in_array($mode, ['google','manual'], true)) throw new InvalidArgumentException('Invalid data source mode.');
    $json = json_encode($mode, JSON_THROW_ON_ERROR);
    $s = db()->prepare("INSERT INTO app_data(data_key,json_value,updated_by) VALUES('receivables_source_mode',?,?) ON DUPLICATE KEY UPDATE json_value=VALUES(json_value),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP");
    $s->execute([$json, $userId]);
}

function live_key(string $value): string {
    $value = preg_replace('/\s+/u', ' ', trim($value));
    return strtolower($value ?? '');
}

function live_date_value($value): ?string {
    if ($value instanceof DateTimeInterface) return $value->format('Y-m-d');
    $s = trim((string)($value ?? ''));
    if ($s === '' || preg_match('/^#/', $s)) return null;

    // Google Apps Script getDisplayValues() can return date-time strings like
    // 03/04/2026 15:21:07 or 03-Apr-2026 15:21:07. Parse the date part
    // robustly so approval/date based PR-PO cards do not show wrong counts.
    $s = str_replace(["\xc2\xa0", ','], [' ', ''], $s);
    $s = preg_replace('/\s+/', ' ', $s) ?? $s;

    if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:[T\s].*)?$/', $s, $m)) return $m[1];

    $candidates = [$s];
    if (preg_match('/^(.+?)\s+\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AP]M)?$/i', $s, $m)) {
        $candidates[] = trim($m[1]);
    }
    if (preg_match('/^(\d{1,2}[\/\-.][A-Za-z]{3,9}[\/\-.]\d{2,4}|\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}|\d{4}[\/\-.]\d{1,2}[\/\-.]\d{1,2})/', $s, $m)) {
        $candidates[] = trim($m[1]);
    }

    $formats = [
        'd-M-y','d-M-Y','j-M-y','j-M-Y','d-F-y','d-F-Y','j-F-y','j-F-Y',
        'd M y','d M Y','j M y','j M Y','d F y','d F Y','j F y','j F Y',
        'd/m/Y','d-m-Y','d.m.Y','d/m/y','d-m-y','j/n/Y','j-n-Y','j.n.Y','j/n/y','j-n-y',
        'Y/m/d','Y-m-d','Y.m.d','Y/n/j','Y-n-j','Y.n.j',
        'd-M-y H:i','d-M-Y H:i','j-M-y H:i','j-M-Y H:i','d-M-y H:i:s','d-M-Y H:i:s','j-M-y H:i:s','j-M-Y H:i:s',
        'd-F-y H:i','d-F-Y H:i','j-F-y H:i','j-F-Y H:i','d-F-y H:i:s','d-F-Y H:i:s','j-F-y H:i:s','j-F-Y H:i:s',
        'd/m/Y H:i','d-m-Y H:i','d/m/y H:i','d-m-y H:i','j/n/Y H:i','j-n-Y H:i','j/n/y H:i','j-n-y H:i',
        'd/m/Y H:i:s','d-m-Y H:i:s','d/m/y H:i:s','d-m-y H:i:s','j/n/Y H:i:s','j-n-Y H:i:s','j/n/y H:i:s','j-n-y H:i:s',
        'd/m/Y h:i A','d-m-Y h:i A','j/n/Y h:i A','j-n-Y h:i A','d/m/Y h:i:s A','d-m-Y h:i:s A','j/n/Y h:i:s A','j-n-Y h:i:s A',
        'Y/m/d H:i','Y-m-d H:i','Y/m/d H:i:s','Y-m-d H:i:s','Y/n/j H:i','Y-n-j H:i','Y/n/j H:i:s','Y-n-j H:i:s'
    ];

    foreach (array_unique($candidates) as $candidate) {
        foreach ($formats as $fmt) {
            $d = DateTimeImmutable::createFromFormat('!' . $fmt, $candidate);
            $err = DateTimeImmutable::getLastErrors();
            if ($d && ($err === false || ($err['warning_count'] === 0 && $err['error_count'] === 0))) {
                return $d->format('Y-m-d');
            }
        }
    }

    return null;
}

function http_get_json(string $url): array {
    $body = false;
    $error = '';
    $status = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_USERAGENT => 'BasikReceivablesPortal/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false) $error = curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 25, 'follow_location' => 1, 'user_agent' => 'BasikReceivablesPortal/1.0']]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) $error = 'Unable to open Google Sheet URL from PHP.';
        if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $status = (int)$m[1];
    }
    if ($body === false || ($status && $status >= 400)) {
        throw new RuntimeException('Google Sheet request failed' . ($status ? ' (HTTP ' . $status . ')' : '') . ($error ? ': ' . $error : '.'));
    }
    $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || empty($payload['success'])) {
        throw new RuntimeException((string)($payload['message'] ?? $payload['error'] ?? 'Google Sheet returned an invalid response.'));
    }
    if (!isset($payload['headers'], $payload['rows']) || !is_array($payload['headers']) || !is_array($payload['rows'])) {
        throw new RuntimeException('Google Sheet response is missing headers or rows.');
    }
    return $payload;
}

function google_payload_to_source(array $payload): array {
    $seed = template_seed_source();
    $canonical = $seed['headers'] ?? [];

    $payloadHeaders = array_map(fn($h) => live_key((string)$h), $payload['headers'] ?? []);
    $hasTds = (bool)array_intersect($payloadHeaders, ['tds', 'tds amount', 'tds deduction']);
    if ($hasTds && !in_array('TDS', $canonical, true)) {
        $pendingPos = array_search('Pending Amount', $canonical, true);
        if ($pendingPos === false) $canonical[] = 'TDS';
        else array_splice($canonical, $pendingPos, 0, ['TDS']);
    }

    $weeklyOptional = [
        'BM' => ['bm', 'benchmark'],
        'CN Issued' => ['credit note amount', 'credit note', 'cn issued', 'cn issue'],
        'CN Date' => ['credit note date', 'cn date'],
        'RTV' => ['rtv', 'rtv amount'],
        'RTV Date' => ['rtv date'],
        'Write Off' => ['write off', 'right off'],
        'Write Off Date' => ['write off date', 'right off date'],
        'Other' => ['other', 'other amount'],
        'Other Date' => ['other date'],
    ];
    foreach ($weeklyOptional as $canonicalHeader => $keys) {
        if ((bool)array_intersect($payloadHeaders, $keys) && !in_array($canonicalHeader, $canonical, true)) {
            $canonical[] = $canonicalHeader;
        }
    }

    $aliases = [
        'Bill Date' => ['Bill Date'],
        'Ref. No.' => ['Ref. No.', 'Ref No.', 'Reference No.', 'Invoice No.', 'Bill No.'],
        'Party' => ['Party', 'Customer Name', 'Customer'],
        'Checking' => ['Checking'],
        'Customer Type' => ['Customer Type'],
        'Opening Amount' => ['Opening Amount'],
        'OLD Pending Amount' => ['OLD Pending Amount', 'Old Pending Amount'],
        'Receipt date' => ['Receipt date', 'Receipt Date'],
        'Debit Note' => ['Debit Note'],
        'Receipt Amount' => ['Receipt Amount'],
        'TDS' => ['TDS', 'TDS Amount', 'TDS Deduction'],
        'BM' => ['BM', 'Benchmark'],
        'CN Issued' => ['Credit Note Amount', 'Credit Note', 'CN Issued', 'CN Issue'],
        'CN Date' => ['Credit Note Date', 'CN Date'],
        'RTV' => ['RTV', 'RTV Amount'],
        'RTV Date' => ['RTV Date'],
        'Write Off' => ['Write Off', 'Write off', 'Right Off', 'Right off'],
        'Write Off Date' => ['Write Off Date', 'Write off Date', 'Right Off Date', 'Right off Date'],
        'Other' => ['Other', 'Other Amount'],
        'Other Date' => ['Other Date'],
        'Pending Amount' => ['Pending Amount'],
        'Terms' => ['Terms'],
        'Due Date' => ['Due Date'],
        'Year' => ['Year'],
        'MONTH' => ['MONTH', 'Month'],
        'Focus Followups' => ['Focus Followups', 'Focus Follow-ups'],
        'Target Date' => ['Target Date'],
        'Ageing' => ['Ageing', 'Aging'],
        'Debtor Age' => ['Debtor Age'],
        'Collection Target' => ['Collection Target'],
        'BDM' => ['BDM', 'Sales Rep'],
        'Sales Coordinator' => ['Sales Coordinator'],
        'CRM' => ['CRM'],
        'Invoice Age' => ['Invoice Age'],
        'Collection next 7 Days' => ['Collection next 7 Days'],
        'Collection next 15 Days' => ['Collection next 15 Days'],
        'Collection next 30 Days' => ['Collection next 30 Days'],
        'Delivery Status' => ['Delivery Status'],
        'Credit Note' => ['Credit Note'],
        'OS Type' => ['OS Type'],
        'Action Type' => ['Action Type'],
        'Action Point' => ['Action Point'],
        'Target Week' => ['Target Week'],
        ' Week 1 Target' => ['Week 1 Target', ' Week 1 Target'],
        'Week 1 Recd' => ['Week 1 Recd'],
        'Remarks' => ['Remarks'],
        'JAYA REMARK ' => ['JAYA REMARK', 'JAYA REMARK '],
        'Type' => ['Type'],
    ];
    foreach ($canonical as $h) {
        if (preg_match('/^OS upto \d+ Days$/', trim((string)$h))) $aliases[$h] = [trim((string)$h)];
    }

    $rows = [];
    $headerRow = max(1, (int)($payload['headerRow'] ?? 3));
    foreach (($payload['rows'] ?? []) as $i => $obj) {
        if (!is_array($obj)) continue;
        $norm = [];
        foreach ($obj as $k => $v) $norm[live_key((string)$k)] = $v;
        $values = [];
        foreach ($canonical as $header) {
            $candidates = $aliases[$header] ?? [$header];
            $value = '';
            foreach ($candidates as $candidate) {
                $k = live_key($candidate);
                if (array_key_exists($k, $norm)) { $value = $norm[$k]; break; }
            }
            if (in_array(trim((string)$header), ['Bill Date', 'Receipt date', 'Due Date', 'Target Date', 'CN Date', 'RTV Date', 'Write Off Date', 'Other Date'], true)) {
                $parsed = live_date_value($value);
                $value = $parsed ?? '';
            }
            $values[] = $value;
        }
        $partyIndex = array_search('Party', $canonical, true);
        $pendingIndex = array_search('Pending Amount', $canonical, true);
        $party = $partyIndex === false ? '' : trim((string)($values[$partyIndex] ?? ''));
        $pending = $pendingIndex === false ? '' : trim((string)($values[$pendingIndex] ?? ''));
        if ($party === '' && $pending === '') continue;
        $rows[] = ['row' => $headerRow + 1 + $i, 'v' => $values];
    }

    return [
        'schemaVersion' => 1,
        'fileName' => 'Google Sheet Live - Data',
        'company' => $seed['company'] ?? 'Basik Innovation LLP',
        'snapshotDate' => date('Y-m-d'),
        'date1904' => false,
        'headers' => $canonical,
        'rows' => $rows,
        'master' => $seed['master'] ?? [],
        'sourceHeaderTotal' => null,
        'legacyTotal' => null,
        'legacyRangeEnd' => null,
        'liveSync' => [
            'enabled' => true,
            'status' => 'live',
            'sheet' => (string)($payload['sheet'] ?? 'Data'),
            'rowCount' => count($rows),
            'fetchedAt' => date('c'),
        ],
    ];
}

function cached_google_row(): ?array {
    try {
        $s = db()->prepare("SELECT json_value, updated_at FROM app_data WHERE data_key='google_live_source' LIMIT 1");
        $s->execute();
        $row = $s->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function save_google_cache(array $source): void {
    $json = json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $s = db()->prepare("INSERT INTO app_data(data_key,json_value,updated_by) VALUES('google_live_source',?,NULL) ON DUPLICATE KEY UPDATE json_value=VALUES(json_value),updated_by=NULL,updated_at=CURRENT_TIMESTAMP");
    $s->execute([$json]);
}

function load_google_source(bool $force = false): array {
    if (!defined('GOOGLE_SHEET_API_URL') || trim((string)GOOGLE_SHEET_API_URL) === '') {
        throw new RuntimeException('Google Sheet API URL is not configured.');
    }
    $cached = cached_google_row();
    $maxAge = defined('GOOGLE_SHEET_CACHE_SECONDS') ? max(0, (int)GOOGLE_SHEET_CACHE_SECONDS) : 60;
    if (!$force && $cached && strtotime((string)$cached['updated_at']) >= time() - $maxAge) {
        $src = json_decode($cached['json_value'], true, 512, JSON_THROW_ON_ERROR);
        if (isset($src['liveSync'])) {
            $src['liveSync']['status'] = 'cached';
            $src['liveSync']['cacheUpdatedAt'] = $cached['updated_at'];
        }
        return $src;
    }
    try {
        $payload = http_get_json(GOOGLE_SHEET_API_URL);
        $source = google_payload_to_source($payload);
        save_google_cache($source);
        return $source;
    } catch (Throwable $e) {
        if ($cached) {
            $src = json_decode($cached['json_value'], true, 512, JSON_THROW_ON_ERROR);
            $src['liveSync'] = array_merge($src['liveSync'] ?? [], [
                'enabled' => true,
                'status' => 'stale',
                'error' => $e->getMessage(),
                'cacheUpdatedAt' => $cached['updated_at'],
            ]);
            return $src;
        }
        throw $e;
    }
}

function load_source_data(): array {
    if (defined('GOOGLE_SHEET_LIVE') && GOOGLE_SHEET_LIVE) {
        if (data_source_mode() === 'manual') {
            $manual = dashboard_db_source();
            $manual['liveSync'] = [
                'enabled' => false,
                'status' => 'manual',
                'rowCount' => count($manual['rows'] ?? []),
            ];
            return $manual;
        }
        try {
            return load_google_source(false);
        } catch (Throwable $e) {
            $fallback = dashboard_db_source();
            $fallback['liveSync'] = [
                'enabled' => true,
                'status' => 'fallback',
                'error' => $e->getMessage(),
                'rowCount' => count($fallback['rows'] ?? []),
            ];
            return $fallback;
        }
    }
    return dashboard_db_source();
}

function force_google_sync(): array {
    if (!defined('GOOGLE_SHEET_LIVE') || !GOOGLE_SHEET_LIVE) throw new RuntimeException('Google Sheet live mode is disabled.');
    return load_google_source(true);
}

function filtered_source_for_user(array $source, array $user): array {
    if ($user['role'] === 'admin' || $user['data_scope'] === 'all') return $source;
    $allowed = user_coordinators((int)$user['id']);
    $headers = $source['headers'] ?? [];
    $idx = null;
    foreach ($headers as $i => $h) if (live_key((string)$h) === 'sales coordinator') { $idx = $i; break; }
    if ($idx === null) { $source['rows'] = []; $source['master'] = []; return $source; }
    $partyIdx = null;
    foreach ($headers as $i => $h) if (live_key((string)$h) === 'party') { $partyIdx = $i; break; }
    $set = array_fill_keys($allowed, true); $rows = []; $parties = [];
    foreach (($source['rows'] ?? []) as $r) {
        $v = $r['v'] ?? [];
        $owner = trim((string)($v[$idx] ?? ''));
        if (isset($set[$owner])) {
            $rows[] = $r;
            if ($partyIdx !== null) {
                $party = trim((string)($v[$partyIdx] ?? ''));
                if ($party !== '') $parties[$party] = true;
            }
        }
    }
    $source['rows'] = $rows;
    if (isset($source['master']) && is_array($source['master'])) {
        $source['master'] = array_values(array_filter($source['master'], fn($r) => isset($parties[trim((string)(($r['v'] ?? [])[1] ?? ''))])));
    }
    $source['sourceHeaderTotal'] = null; $source['legacyTotal'] = null; $source['legacyRangeEnd'] = null;
    $source['fileName'] = ($source['fileName'] ?? 'Receivables') . ' - permitted view';
    if (isset($source['liveSync'])) $source['liveSync']['rowCount'] = count($rows);
    return $source;
}

function load_party_remarks(array $source): array {
    $headers = $source['headers'] ?? [];
    $partyIdx = null;
    foreach ($headers as $i => $h) if (live_key((string)$h) === 'party') { $partyIdx = $i; break; }
    if ($partyIdx === null) return [];
    $parties = [];
    foreach (($source['rows'] ?? []) as $r) {
        $p = trim((string)(($r['v'] ?? [])[$partyIdx] ?? ''));
        if ($p !== '') $parties[$p] = true;
    }
    if (!$parties) return [];
    $all = db()->query('SELECT party_name, remark FROM party_remarks')->fetchAll();
    $out = [];
    foreach ($all as $r) if (isset($parties[$r['party_name']])) $out[$r['party_name']] = $r['remark'];
    return $out;
}

function ensure_party_remark_history_table(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS party_remark_history (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        party_name VARCHAR(191) NOT NULL,
        old_remark TEXT NULL,
        new_remark TEXT NULL,
        action VARCHAR(20) NOT NULL DEFAULT 'update',
        changed_by INT UNSIGNED NULL,
        changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_party_changed (party_name, changed_at),
        INDEX idx_changed_by (changed_by),
        CONSTRAINT fk_remark_history_user FOREIGN KEY(changed_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function source_parties(array $source): array {
    $headers = $source['headers'] ?? [];
    $partyIdx = null;
    foreach ($headers as $i => $h) if (live_key((string)$h) === 'party') { $partyIdx = $i; break; }
    if ($partyIdx === null) return [];
    $parties = [];
    foreach (($source['rows'] ?? []) as $r) {
        $p = trim((string)(($r['v'] ?? [])[$partyIdx] ?? ''));
        if ($p !== '') $parties[$p] = true;
    }
    return array_keys($parties);
}

function load_party_remark_history(array $source, int $limitPerParty = 20): array {
    ensure_party_remark_history_table();
    $parties = source_parties($source);
    if (!$parties) return [];
    $history = [];
    foreach (array_chunk($parties, 250) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $sql = "SELECT h.id, h.party_name, h.old_remark, h.new_remark, h.action, h.changed_at, COALESCE(u.full_name, u.username, 'System') AS changed_by_name
                FROM party_remark_history h
                LEFT JOIN users u ON u.id = h.changed_by
                WHERE h.party_name IN ($ph)
                ORDER BY h.changed_at DESC, h.id DESC";
        $stmt = db()->prepare($sql);
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $row) {
            $party = (string)$row['party_name'];
            if (!isset($history[$party])) $history[$party] = [];
            if (count($history[$party]) >= $limitPerParty) continue;
            $history[$party][] = [
                'id' => (int)$row['id'],
                'old' => (string)($row['old_remark'] ?? ''),
                'new' => (string)($row['new_remark'] ?? ''),
                'action' => (string)$row['action'],
                'by' => (string)($row['changed_by_name'] ?? 'System'),
                'at' => (string)$row['changed_at'],
            ];
        }
    }
    return $history;
}

function source_coordinators(): array {
    $src = load_source_data(); $headers = $src['headers'] ?? []; $idx = null;
    foreach ($headers as $i => $h) if (live_key((string)$h) === 'sales coordinator') { $idx = $i; break; }
    if ($idx === null) return [];
    $vals = [];
    foreach (($src['rows'] ?? []) as $r) {
        $v = trim((string)(($r['v'] ?? [])[$idx] ?? ''));
        if ($v !== '') $vals[$v] = true;
    }
    $vals = array_keys($vals); natcasesort($vals); return array_values($vals);
}


function procurement_live_enabled(): bool {
    if (!defined('PR_PO_SHEET_LIVE') || !PR_PO_SHEET_LIVE) return false;
    if (!defined('PR_PO_SHEET_API_URL')) return false;
    $url = trim((string)PR_PO_SHEET_API_URL);
    if ($url === '') return false;
    if (stripos($url, 'PASTE') !== false || stripos($url, 'WEB_APP') !== false) return false;
    return true;
}

function procurement_file_source(): array {
    $path = __DIR__ . '/../data/procurement_pr_po.json';
    if (!is_file($path)) {
        return [
            'schemaVersion' => 1,
            'sourceFile' => '',
            'sheetName' => '',
            'asOfDate' => date('Y-m-d'),
            'note' => 'PR/PO source file is not connected.',
            'headers' => [],
            'rows' => [],
            'summary' => [],
            'liveSync' => ['enabled' => false, 'status' => 'fallback_file', 'rowCount' => 0]
        ];
    }
    try {
        $payload = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) $payload = [];
        // Older bundled JSON files are re-normalized through the current parser so
        // date/time parsing, delay days, PR/PO approval counts and GRN counts stay consistent.
        if ((int)($payload['schemaVersion'] ?? 0) < 4) {
            $payload = procurement_source_from_payload($payload);
        }
        $payload['liveSync'] = array_merge($payload['liveSync'] ?? [], [
            'enabled' => false,
            'status' => procurement_live_enabled() ? 'fallback_file' : 'file',
            'rowCount' => count($payload['rows'] ?? []),
        ]);
        return $payload;
    } catch (Throwable $e) {
        return [
            'schemaVersion' => 1,
            'sourceFile' => basename($path),
            'sheetName' => '',
            'asOfDate' => date('Y-m-d'),
            'note' => 'PR/PO source could not be read: ' . $e->getMessage(),
            'headers' => [],
            'rows' => [],
            'summary' => [],
            'liveSync' => ['enabled' => false, 'status' => 'error', 'error' => $e->getMessage(), 'rowCount' => 0]
        ];
    }
}

function procurement_cache_row(): ?array {
    try {
        $s = db()->prepare("SELECT json_value, updated_at FROM app_data WHERE data_key='pr_po_live_source' LIMIT 1");
        $s->execute();
        $r = $s->fetch();
        return $r ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function save_procurement_cache(array $source): void {
    $json = json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $s = db()->prepare("INSERT INTO app_data(data_key,json_value,updated_by) VALUES('pr_po_live_source',?,NULL) ON DUPLICATE KEY UPDATE json_value=VALUES(json_value),updated_by=NULL,updated_at=CURRENT_TIMESTAMP");
    $s->execute([$json]);
}

function proc_header_key($value): string {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string)$value)) ?? '');
}

function proc_num($value): float {
    if (is_int($value) || is_float($value)) return is_finite((float)$value) ? (float)$value : 0.0;
    $s = trim((string)($value ?? ''));
    if ($s === '' || preg_match('/^#/', $s)) return 0.0;
    $s = str_replace([',', '₹', 'INR', ' '], '', $s);
    if (preg_match('/^\((.*)\)$/', $s, $m)) $s = '-' . $m[1];
    return is_numeric($s) ? (float)$s : 0.0;
}

function proc_date($value): ?string {
    if ($value instanceof DateTimeInterface) return $value->format('Y-m-d');
    if (is_int($value) || is_float($value)) {
        $n = (float)$value;
        if ($n > 1 && $n < 100000) return gmdate('Y-m-d', (int)(($n - 25569) * 86400));
    }
    $s = trim((string)($value ?? ''));
    if ($s === '' || preg_match('/^#/', $s)) return null;
    if (preg_match('/^(\d{4}-\d{2}-\d{2})T/', $s, $m)) return $m[1];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
    if (preg_match('/^\/Date\((\d+)\)\/?$/', $s, $m)) return gmdate('Y-m-d', (int)floor(((int)$m[1]) / 1000));
    if (preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $s)) {
        $n = (float)$s;
        if ($n > 1 && $n < 100000) return gmdate('Y-m-d', (int)(($n - 25569) * 86400));
    }
    return live_date_value($s);
}

function proc_days(?string $from, ?string $to): ?int {
    if (!$from || !$to) return null;
    $a = strtotime($from . ' 00:00:00 UTC');
    $b = strtotime($to . ' 00:00:00 UTC');
    if ($a === false || $b === false) return null;
    return (int)round(($b - $a) / 86400);
}

function proc_delay_days(?string $from, ?string $to): ?int {
    $days = proc_days($from, $to);
    return $days === null ? null : max(0, $days);
}

function proc_values_from_row($row): array {
    if (is_array($row)) {
        if (isset($row['values']) && is_array($row['values'])) return $row['values'];
        if (isset($row['v']) && is_array($row['v'])) return $row['v'];
        $keys = array_keys($row);
        $isList = $keys === range(0, count($row) - 1);
        if ($isList) return $row;
    }
    return [];
}

function proc_pick(array $headers, array $values, array $aliases) {
    static $cache = [];
    $aliasSet = [];
    foreach ($aliases as $a) $aliasSet[proc_header_key($a)] = true;
    foreach ($headers as $i => $h) {
        $hk = proc_header_key($h);
        if (isset($aliasSet[$hk])) return $values[$i] ?? '';
    }
    return '';
}

function procurement_source_from_payload(array $payload): array {
    $headers = array_values(array_map('strval', $payload['headers'] ?? []));
    $rawRows = $payload['rows'] ?? [];
    $rows = [];
    $today = date('Y-m-d');
    foreach ($rawRows as $i => $raw) {
        // If the API returns rows as arrays, {values:[...]}, {v:[...]}, or header-keyed objects, normalize to a values array.
        $values = proc_values_from_row($raw);
        if (!$values && is_array($raw) && $headers) {
            $headerKeyedValues = [];
            $hasHeaderKeyedValue = false;
            foreach ($headers as $h) {
                $cell = '';
                if (array_key_exists($h, $raw)) {
                    $cell = $raw[$h];
                    $hasHeaderKeyedValue = true;
                }
                $headerKeyedValues[] = $cell;
            }
            if ($hasHeaderKeyedValue) $values = $headerKeyedValues;
        }
        $line = is_array($raw) ? (int)($raw['row'] ?? $raw['rowNumber'] ?? $raw['_rowNumber'] ?? ($i + 2)) : ($i + 2);
        if ($values) {
            $prNo = trim((string)proc_pick($headers, $values, ['PR NO','PR No','PR Number','PRNo']));
            $prDate = proc_date(proc_pick($headers, $values, ['PR DATE','PR Date']));
            $department = trim((string)proc_pick($headers, $values, ['Department Name','Department','Dept']));
            $itemType = trim((string)proc_pick($headers, $values, ['Item Type','Type']));
            $itemCode = trim((string)proc_pick($headers, $values, ['Our Code','Item Code','Code']));
            $itemName = trim((string)proc_pick($headers, $values, ['Our Name','Item Name','Product Name','Material Name']));
            $prQty = proc_num(proc_pick($headers, $values, ['PR Qty','PR Quantity','Requisition Qty']));
            $poNo = trim((string)proc_pick($headers, $values, ['PO NO','PO No','PO Number','PONO']));
            $poDate = proc_date(proc_pick($headers, $values, ['PO DATE','PO Date']));
            $scheduleDate = proc_date(proc_pick($headers, $values, ['Schedule Date','Delivery Date','Scheduled Date']));
            $party = trim((string)proc_pick($headers, $values, ['Party','Vendor','Vendor Name','Supplier']));
            $poQty = proc_num(proc_pick($headers, $values, ['PO Qty','PO Quantity','Order Qty']));
            $grpNo = trim((string)proc_pick($headers, $values, ['GRPNo','GRP No','GRP Number']));
            $grnNo = trim((string)proc_pick($headers, $values, ['GRNNo','GRN No','GRN Number']));
            $grnDate = proc_date(proc_pick($headers, $values, ['GRN Date','GRNDate']));
            $invoiceNo = trim((string)proc_pick($headers, $values, ['Invoice No','Invoice Number','Bill No']));
            $invoiceDate = proc_date(proc_pick($headers, $values, ['Invoice Date','Bill Date']));
            $acceptedQty = proc_num(proc_pick($headers, $values, ['Accepted Qty','Accept Qty','Accepted Quantity']));
            $receivedQty = proc_num(proc_pick($headers, $values, ['Received Qty','Receive Qty','GRN Qty','Receipt Qty']));
            if ($receivedQty == 0.0 && $acceptedQty > 0.0) $receivedQty = $acceptedQty;
            $rejectedQty = proc_num(proc_pick($headers, $values, ['Rejected Qty','Reject Qty','Rejected Quantity']));
            $purchaseVoucherNo = trim((string)proc_pick($headers, $values, ['Purchase Voucher No','PV No','Voucher No']));
            $purchaseVoucherDate = proc_date(proc_pick($headers, $values, ['Purchase Voucher Date','PV Date','Voucher Date']));
            $prApprovedBy = trim((string)proc_pick($headers, $values, ['PR Approved by','PR Approved By','PR Approver']));
            $prApprovedDate = proc_date(proc_pick($headers, $values, ['PR Approved Date','PR Approval Date']));
            $poApprovedBy = trim((string)proc_pick($headers, $values, ['PO Approved by','PO Approved By','PO Approver']));
            // Correct the common header typo separately for date.
            $poApprovedDate = proc_date(proc_pick($headers, $values, ['PO Approved Date','Po APproved Date','PO Approval Date']));
            $qcBy = trim((string)proc_pick($headers, $values, ['QC By','QC Approved By']));
            $qcApprovalDate = proc_date(proc_pick($headers, $values, ['QC Approval Date','QC Date']));
            $debitNoteNumber = trim((string)proc_pick($headers, $values, ['Debit Note Number','Debit Note No','DN No']));
        } else {
            if (!is_array($raw)) continue;
            $prNo = trim((string)($raw['prNo'] ?? ''));
            $prDate = proc_date($raw['prDate'] ?? null);
            $department = trim((string)($raw['department'] ?? ''));
            $itemType = trim((string)($raw['itemType'] ?? ''));
            $itemCode = trim((string)($raw['itemCode'] ?? ''));
            $itemName = trim((string)($raw['itemName'] ?? ''));
            $prQty = proc_num($raw['prQty'] ?? 0);
            $poNo = trim((string)($raw['poNo'] ?? ''));
            $poDate = proc_date($raw['poDate'] ?? null);
            $scheduleDate = proc_date($raw['scheduleDate'] ?? null);
            $party = trim((string)($raw['party'] ?? ''));
            $poQty = proc_num($raw['poQty'] ?? 0);
            $grpNo = trim((string)($raw['grpNo'] ?? ''));
            $grnNo = trim((string)($raw['grnNo'] ?? ''));
            $grnDate = proc_date($raw['grnDate'] ?? null);
            $invoiceNo = trim((string)($raw['invoiceNo'] ?? ''));
            $invoiceDate = proc_date($raw['invoiceDate'] ?? null);
            $acceptedQty = proc_num($raw['acceptedQty'] ?? 0);
            $receivedQty = proc_num($raw['receivedQty'] ?? 0);
            $rejectedQty = proc_num($raw['rejectedQty'] ?? 0);
            $purchaseVoucherNo = trim((string)($raw['purchaseVoucherNo'] ?? ''));
            $purchaseVoucherDate = proc_date($raw['purchaseVoucherDate'] ?? null);
            $prApprovedBy = trim((string)($raw['prApprovedBy'] ?? ''));
            $prApprovedDate = proc_date($raw['prApprovedDate'] ?? null);
            $poApprovedBy = trim((string)($raw['poApprovedBy'] ?? ''));
            $poApprovedDate = proc_date($raw['poApprovedDate'] ?? null);
            $qcBy = trim((string)($raw['qcBy'] ?? ''));
            $qcApprovalDate = proc_date($raw['qcApprovalDate'] ?? null);
            $debitNoteNumber = trim((string)($raw['debitNoteNumber'] ?? ''));
        }
        if ($prNo === '' && $poNo === '' && $itemName === '' && $party === '') continue;
        $pendingQty = $poNo !== '' ? max($poQty - $receivedQty, 0) : 0.0;
        // Some live Sheet55 exports have blank or unparseable approval dates even after
        // later steps are completed. Treat downstream activity as approval evidence so
        // PR/PO dashboard counts do not show false pending numbers.
        $grnDone = ($grnNo !== '' || $grnDate !== null || $receivedQty > 0 || $acceptedQty > 0 || $qcApprovalDate !== null);
        $prApprovedEffective = ($prApprovedDate !== null || $prApprovedBy !== '' || $poNo !== '' || $poDate !== null || $poApprovedDate !== null || $grnDone);
        $poApprovedEffective = ($poApprovedDate !== null || $poApprovedBy !== '' || $grnDone || $invoiceNo !== '' || $purchaseVoucherNo !== '');
        if (!$prApprovedEffective) $status = 'PR Approval Pending';
        elseif ($poNo === '') $status = 'PO Pending';
        elseif (!$poApprovedEffective) $status = 'PO Approval Pending';
        elseif ($receivedQty <= 0 && $grnNo === '') $status = 'GRN Pending';
        elseif ($poQty > 0 && $receivedQty >= $poQty) $status = 'Completed';
        elseif ($receivedQty > 0) $status = 'Partial GRN';
        else $status = 'GRN Pending';
        $baseForGrn = $scheduleDate ?: $poDate;
        $openDelay = null;
        if ($poNo === '') $openDelay = proc_delay_days($prApprovedDate ?: $prDate, $today);
        elseif ($grnNo === '' && $receivedQty <= 0) $openDelay = proc_delay_days($baseForGrn ?: $poDate, $today);
        $rows[] = [
            'row' => $line,
            'prNo' => $prNo,
            'prDate' => $prDate,
            'department' => $department,
            'itemType' => $itemType,
            'itemCode' => $itemCode,
            'itemName' => $itemName,
            'prQty' => $prQty,
            'poNo' => $poNo,
            'poDate' => $poDate,
            'scheduleDate' => $scheduleDate,
            'party' => $party,
            'poQty' => $poQty,
            'grpNo' => $grpNo,
            'grnNo' => $grnNo,
            'grnDate' => $grnDate,
            'invoiceNo' => $invoiceNo,
            'invoiceDate' => $invoiceDate,
            'acceptedQty' => $acceptedQty,
            'receivedQty' => $receivedQty,
            'rejectedQty' => $rejectedQty,
            'purchaseVoucherNo' => $purchaseVoucherNo,
            'purchaseVoucherDate' => $purchaseVoucherDate,
            'prApprovedBy' => $prApprovedBy,
            'prApprovedDate' => $prApprovedDate,
            'poApprovedBy' => $poApprovedBy,
            'poApprovedDate' => $poApprovedDate,
            'qcBy' => $qcBy,
            'qcApprovalDate' => $qcApprovalDate,
            'debitNoteNumber' => $debitNoteNumber,
            'prApproved' => $prApprovedEffective,
            'poApproved' => $poApprovedEffective,
            'grnDone' => $grnDone,
            'pendingQty' => $pendingQty,
            'completionPct' => $poQty > 0 ? round(min(100, ($receivedQty / $poQty) * 100), 2) : 0,
            'status' => $status,
            'prApprovalDelayDays' => proc_delay_days($prDate, $prApprovedDate),
            'poCreateDelayDays' => proc_delay_days($prApprovedDate ?: $prDate, $poDate),
            'poApprovalDelayDays' => proc_delay_days($poDate, $poApprovedDate),
            'grnDelayDays' => proc_delay_days($baseForGrn, $grnDate),
            'openDelayDays' => $openDelay,
        ];
    }
    $uniquePr = [];
    $summary = ['rowCount' => count($rows), 'uniquePrCount' => 0, 'prQty' => 0, 'poRows' => 0, 'poPendingRows' => 0, 'poQty' => 0, 'grnRows' => 0, 'grnPendingRows' => 0, 'receivedQty' => 0, 'acceptedQty' => 0, 'rejectedQty' => 0, 'pendingQty' => 0];
    foreach ($rows as $r) {
        if ($r['prNo'] !== '') $uniquePr[$r['prNo']] = true;
        $summary['prQty'] += (float)$r['prQty'];
        if ($r['poNo'] !== '') {
            $summary['poRows']++;
            $summary['poQty'] += (float)$r['poQty'];
            if ($r['grnNo'] !== '' || (float)$r['receivedQty'] > 0) $summary['grnRows']++;
            else $summary['grnPendingRows']++;
        } else {
            $summary['poPendingRows']++;
        }
        $summary['receivedQty'] += (float)$r['receivedQty'];
        $summary['acceptedQty'] += (float)$r['acceptedQty'];
        $summary['rejectedQty'] += (float)$r['rejectedQty'];
        $summary['pendingQty'] += (float)$r['pendingQty'];
    }
    $summary['uniquePrCount'] = count($uniquePr);
    return [
        'schemaVersion' => 4,
        'sourceFile' => (string)($payload['sourceFile'] ?? $payload['fileName'] ?? 'PR/PO Google Sheet'),
        'spreadsheetId' => (string)($payload['spreadsheetId'] ?? ''),
        'sheetName' => (string)($payload['sheetName'] ?? $payload['sheet'] ?? (defined('PR_PO_DEFAULT_SHEET_NAME') ? PR_PO_DEFAULT_SHEET_NAME : 'Sheet55')),
        'asOfDate' => (string)($payload['asOfDate'] ?? $today),
        'note' => 'PR/PO data is stored separately from live receivables data. Counts are row-wise, not unique-counted.',
        'headers' => $headers,
        'rows' => $rows,
        'summary' => $summary,
        'liveSync' => ['enabled' => true, 'status' => 'live', 'rowCount' => count($rows), 'syncedAt' => date('Y-m-d H:i:s')],
    ];
}

function load_procurement_pr_po_source(bool $force = false): array {
    if (!procurement_live_enabled()) return procurement_file_source();
    $cached = procurement_cache_row();
    $maxAge = defined('PR_PO_SHEET_CACHE_SECONDS') ? max(0, (int)PR_PO_SHEET_CACHE_SECONDS) : 60;
    if (!$force && $cached && strtotime((string)$cached['updated_at']) >= time() - $maxAge) {
        $src = json_decode($cached['json_value'], true, 512, JSON_THROW_ON_ERROR);
        if ((int)($src['schemaVersion'] ?? 0) >= 4) {
            $src['liveSync'] = array_merge($src['liveSync'] ?? [], ['enabled' => true, 'status' => 'cached', 'cacheUpdatedAt' => $cached['updated_at'], 'rowCount' => count($src['rows'] ?? [])]);
            return $src;
        }
    }
    try {
        $payload = http_get_json(PR_PO_SHEET_API_URL);
        $src = procurement_source_from_payload($payload);
        save_procurement_cache($src);
        return $src;
    } catch (Throwable $e) {
        if ($cached) {
            $src = json_decode($cached['json_value'], true, 512, JSON_THROW_ON_ERROR);
            if ((int)($src['schemaVersion'] ?? 0) < 4) {
                $src = procurement_source_from_payload($src);
            }
            $src['liveSync'] = array_merge($src['liveSync'] ?? [], ['enabled' => true, 'status' => 'stale', 'error' => $e->getMessage(), 'cacheUpdatedAt' => $cached['updated_at'], 'rowCount' => count($src['rows'] ?? [])]);
            return $src;
        }
        $fallback = procurement_file_source();
        $fallback['liveSync'] = ['enabled' => true, 'status' => 'fallback_file', 'error' => $e->getMessage(), 'rowCount' => count($fallback['rows'] ?? [])];
        return $fallback;
    }
}

function force_procurement_pr_po_sync(): array {
    if (!procurement_live_enabled()) throw new RuntimeException('PR/PO live sheet URL is not configured. Paste Apps Script Web App URL in PR_PO_SHEET_API_URL.');
    return load_procurement_pr_po_source(true);
}

