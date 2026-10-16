<?php
// api_orders.php - واجهة تطبيق الطلبات (تسجيل دخول بنفس كلمة مرور admin.php)
require __DIR__.'/lib.php';
ini_set('session.gc_maxlifetime', '2592000');
session_name('sidrat_app');
session_set_cookie_params(['lifetime'=>2592000, 'path'=>'/', 'secure'=>!empty($_SERVER['HTTPS']), 'httponly'=>true, 'samesite'=>'Strict']);
session_start();

function auth_hash(){
  $p = data_dir().'/auth.php'; if(!is_file($p)) return null;
  $j = json_decode((string)substr((string)file_get_contents($p), strlen(GUARD)), true);
  return (is_array($j) && !empty($j['hash'])) ? $j['hash'] : null;
}
function pw_ok($pw, $hash){
  if(strpos($hash, '$2') === 0) return password_verify($pw, $hash);
  return hash_equals($hash, hash('sha256', $pw));
}
function read_orders(){
  $f = data_dir().'/orders.php'; if(!is_file($f)) return [];
  $fp = fopen($f, 'r'); if(!$fp) return [];
  flock($fp, LOCK_SH); $s = (string)stream_get_contents($fp); flock($fp, LOCK_UN); fclose($fp);
  $d = json_decode((string)substr($s, strlen(GUARD)), true);
  return is_array($d) ? $d : [];
}

$in = json_decode((string)file_get_contents('php://input'), true);
if(!is_array($in)) $in = [];
$act = isset($_GET['action']) ? $_GET['action'] : '';

if($act === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST'){
  $h = auth_hash();
  if($h && pw_ok((string)(isset($in['pw']) ? $in['pw'] : ''), $h)){
    session_regenerate_id(true);
    $_SESSION['ok'] = 1; $_SESSION['csrf'] = bin2hex(random_bytes(16));
    jout(['ok'=>true, 'csrf'=>$_SESSION['csrf']]);
  }
  sleep(1); jout(['ok'=>false, 'msg'=>'كلمة المرور غير صحيحة'], 401);
}
if(empty($_SESSION['ok'])) jout(['ok'=>false, 'auth'=>false], 401);

if($act === 'logout'){ $_SESSION = []; session_destroy(); jout(['ok'=>true]); }

if($act === 'list'){
  $out = [];
  foreach(read_orders() as $o){ unset($o['ip']); $out[] = $o; }
  jout(['ok'=>true, 'csrf'=>$_SESSION['csrf'], 'status'=>STATUS, 'orders'=>$out]);
}

if($act === 'status' && $_SERVER['REQUEST_METHOD'] === 'POST'){
  if(!hash_equals($_SESSION['csrf'], (string)(isset($in['csrf']) ? $in['csrf'] : ''))) jout(['ok'=>false], 403);
  $num = (string)(isset($in['num']) ? $in['num'] : ''); $st = (string)(isset($in['status']) ? $in['status'] : '');
  if(!isset(STATUS[$st])) jout(['ok'=>false], 422);
  tx(function(&$d) use ($num, $st){ foreach($d as $k => $o){ if($o['num'] === $num) $d[$k]['status'] = $st; } });
  jout(['ok'=>true]);
}
jout(['ok'=>false], 400);
