<?php
require_once __DIR__.'/config.php';require_once __DIR__.'/inc/db.php';
$error='';$done=false;
try{
 $srv=pdo_server();$srv->exec("CREATE DATABASE IF NOT EXISTS `".str_replace('`','',DB_NAME)."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
 $pdo=db();$sql=file_get_contents(__DIR__.'/schema.sql');foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql))) as $q)$pdo->exec($q);
 $count=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
 if($count>0){$done=true;}
 elseif($_SERVER['REQUEST_METHOD']==='POST'){
  $name=trim($_POST['full_name']??'');$username=trim($_POST['username']??'');$pass=$_POST['password']??'';$confirm=$_POST['confirm']??'';
  if(strlen($name)<2||!preg_match('/^[A-Za-z0-9._-]{3,40}$/',$username))throw new RuntimeException('Valid name and username required. Username: letters, numbers, dot, underscore or hyphen.');
  if(strlen($pass)<8)throw new RuntimeException('Password must be at least 8 characters.');if($pass!==$confirm)throw new RuntimeException('Passwords do not match.');
  $s=$pdo->prepare("INSERT INTO users(username,password_hash,full_name,role,active,data_scope) VALUES(?,?,?,'admin',1,'all')");$s->execute([$username,password_hash($pass,PASSWORD_DEFAULT),$name]);$uid=(int)$pdo->lastInsertId();
  // Seed the current receivables data from the protected dashboard template.
  $text=file_get_contents(__DIR__.'/private/dashboard_template.php');if(preg_match('~<script id="embeddedData" type="application/json">(.*?)</script>~s',$text,$m)){$d=json_decode($m[1],true);if($d){$j=json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$q=$pdo->prepare("INSERT INTO app_data(data_key,json_value,updated_by) VALUES('receivables_source',?,?)");$q->execute([$j,$uid]);}}
  $done=true;
 }
}catch(Throwable $e){$error=$e->getMessage();}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Setup - <?=htmlspecialchars(APP_NAME)?></title><link rel="stylesheet" href="assets/app.css"></head><body><div class="auth-shell"><div class="card"><div class="brand">BASIK</div><div class="sub">Receivables Portal · First-time setup</div>
<?php if($error):?><div class="notice error"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($done):?><div class="notice">Setup complete. Database and Admin account are ready.</div><a class="btn primary" href="login.php">Open Login</a><p class="small" style="margin-top:18px">For security, do not share setup.php. It will not create a second admin automatically.</p>
<?php else:?><form method="post"><div class="field"><label>Admin full name</label><input name="full_name" required></div><div class="field"><label>Admin username</label><input name="username" required autocomplete="username"></div><div class="field"><label>Password</label><input type="password" name="password" required autocomplete="new-password"></div><div class="field"><label>Confirm password</label><input type="password" name="confirm" required autocomplete="new-password"></div><button class="btn primary">Create database & Admin</button></form><p class="small" style="margin-top:16px">Default config is XAMPP MySQL: root user with blank password. If yours is different, edit config.php first.</p><?php endif;?>
</div></div></body></html>
