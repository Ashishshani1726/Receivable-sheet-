<?php
require_once __DIR__ . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']);
    session_start();
}
const PERMISSION_CATALOG = [
 'overview'=>'Overview','ageing'=>'Ageing analysis','customers'=>'Customers','bills'=>'Bill register','checks'=>'Data checks','overdue'=>'Over due','weekly'=>'Week-wise due','remarks'=>'Party wise remark','master'=>'Master Data','crm'=>'CRM wise report','pr'=>'PR register','po'=>'PO tracker','pod'=>'POD tracker','grn'=>'GRN tracker','export'=>'Export CSV','import'=>'Import Excel','print'=>'Print','save_copy'=>'Save dashboard copy'
];
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function current_user(): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;
    if (empty($_SESSION['user_id'])) return $cache = null;
    $s = db()->prepare('SELECT id, username, full_name, role, active, data_scope FROM users WHERE id=? LIMIT 1');
    $s->execute([$_SESSION['user_id']]);
    $u = $s->fetch();
    if (!$u || !$u['active']) { logout_user(); return $cache = null; }
    return $cache = $u;
}
function login_user(array $user): void { session_regenerate_id(true); $_SESSION['user_id']=(int)$user['id']; $_SESSION['login_at']=time(); }
function logout_user(): void { $_SESSION=[]; if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']); } session_destroy(); }
function require_login(): array { $u=current_user(); if(!$u){ header('Location: login.php'); exit; } return $u; }
function require_admin(): array { $u=current_user(); if(!$u){ header('Location: ../login.php'); exit; } if($u['role']!=='admin'){ http_response_code(403); exit('Access denied'); } return $u; }
function permissions_for_user(int $userId, string $role): array {
    if ($role==='admin') return array_fill_keys(array_keys(PERMISSION_CATALOG), true);
    $out=array_fill_keys(array_keys(PERMISSION_CATALOG), false);
    $s=db()->prepare('SELECT permission_key, allowed FROM user_permissions WHERE user_id=?'); $s->execute([$userId]);
    foreach($s as $r) if(array_key_exists($r['permission_key'],$out)) $out[$r['permission_key']] = (bool)$r['allowed'];
    return $out;
}
function has_permission(string $key): bool { $u=current_user(); if(!$u)return false; $p=permissions_for_user((int)$u['id'],$u['role']); return !empty($p[$key]); }
function require_permission(string $key): void { if(!has_permission($key)){ http_response_code(403); exit('Permission denied'); } }
function user_coordinators(int $userId): array { $s=db()->prepare('SELECT coordinator_name FROM user_coordinators WHERE user_id=? ORDER BY coordinator_name');$s->execute([$userId]);return array_column($s->fetchAll(),'coordinator_name'); }
function csrf_token(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function verify_csrf(?string $token=null): void { $token=$token ?? ($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')); if(!$token || !hash_equals($_SESSION['csrf']??'', $token)){ http_response_code(419); exit('Invalid request token'); } }
