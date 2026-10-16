<?php
require __DIR__.'/lib.php';
session_start();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function auth_path(){ return data_dir().'/auth.php'; }
function auth_load(){
  $p = auth_path(); if(!is_file($p)) return null;
  $j = json_decode((string)substr((string)file_get_contents($p), strlen(GUARD)), true);
  return is_array($j) ? $j : null;
}
function pw_ok($pw, $hash){
  if(strpos($hash, '$2') === 0) return password_verify($pw, $hash);
  return hash_equals($hash, hash('sha256', $pw));
}
function head($t){
  echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($t).'</title><style>
  body{font-family:Tahoma,Arial,sans-serif;background:#faf6f3;margin:0;padding:14px;color:#3a0a14;max-width:760px;margin:auto}
  h1{font-size:22px}.c{background:#fff;border:1px solid #f0d9d3;border-radius:14px;padding:14px;margin:12px 0}
  .t{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}.b{display:inline-block;padding:3px 12px;border-radius:12px;background:#f3dcd5;font-size:13px}
  .b.new{background:#ffe08a}.b.confirmed{background:#bfe5c8}.b.shipped{background:#bcd7f2}.b.delivered{background:#d8d8d8}.b.cancelled{background:#f5b8b8}
  input,select,button,a.btn{font:inherit;padding:9px 12px;border-radius:10px;border:1px solid #e3c9c2;background:#fff}
  button,a.btn{background:#6e1040;color:#fff;border:0;text-decoration:none;cursor:pointer;display:inline-block}a.btn.g{background:#1faa55}button.d{background:#999}
  table{width:100%;border-collapse:collapse;margin:8px 0}td{padding:5px 0;border-bottom:1px solid #f3e4df}.f a{margin-left:8px;color:#6e1040}
  </style></head><body>';
}
if(isset($_GET['out'])){ $_SESSION = []; session_destroy(); header('Location: admin.php'); exit; }
if(empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$auth = auth_load(); $err = '';
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])){
  if($auth && pw_ok((string)(isset($_POST['pw']) ? $_POST['pw'] : ''), $auth['hash'])){
    session_regenerate_id(true); $_SESSION['ok'] = 1; header('Location: admin.php'); exit;
  }
  sleep(1); $err = 'كلمة المرور غير صحيحة';
}
if(empty($_SESSION['ok'])){
  head('دخول الإدارة');
  echo '<h1>🔒 إدارة الطلبات</h1><div class="c"><form method="post"><input type="password" name="pw" placeholder="كلمة المرور" autofocus> <button name="login" value="1">دخول</button>';
  if($err) echo '<p style="color:#b00">'.h($err).'</p>';
  echo '</form></div></body></html>'; exit;
}
$msg = '';
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act'])){
  if(!hash_equals($_SESSION['csrf'], (string)(isset($_POST['csrf']) ? $_POST['csrf'] : ''))) die('csrf');
  $act = $_POST['act'];
  if($act === 'status'){
    $num = (string)$_POST['num']; $st = (string)$_POST['status'];
    if(isset(STATUS[$st])){
      tx(function(&$d) use ($num, $st){ foreach($d as $k => $o){ if($o['num'] === $num) $d[$k]['status'] = $st; } });
    }
  } elseif($act === 'del'){
    $num = (string)$_POST['num'];
    tx(function(&$d) use ($num){ $d = array_values(array_filter($d, function($o) use ($num){ return $o['num'] !== $num; })); });
  } elseif($act === 'pw'){
    $p = (string)(isset($_POST['np']) ? $_POST['np'] : '');
    if(strlen($p) >= 8){
      file_put_contents(auth_path(), GUARD.json_encode(['hash'=>password_hash($p, PASSWORD_DEFAULT)]), LOCK_EX);
      $msg = '&m=pw';
    }
  }
  header('Location: admin.php?f='.urlencode(isset($_GET['f']) ? $_GET['f'] : '').$msg); exit;
}
function wa_msg($o){
  $t = number_format($o['total'], 2); $n = $o['name']; $x = $o['num'];
  switch($o['status']){
    case 'confirmed': return "السلام عليكم $n، تم تأكيد طلبك رقم $x بإجمالي $t د.ل. شكراً لتسوقك من سدرة المنتهى للكتب.";
    case 'shipped':   return "السلام عليكم $n، تم شحن طلبك رقم $x وسيصلك قريباً بإذن الله.";
    case 'delivered': return "السلام عليكم $n، نأمل أن يكون طلبك رقم $x قد وصلك بخير. شكراً لتسوقك من سدرة المنتهى للكتب.";
    case 'cancelled': return "السلام عليكم $n، بخصوص طلبك رقم $x: تم إلغاؤه. للاستفسار نحن في الخدمة.";
    default:          return "السلام عليكم $n، بخصوص طلبك رقم $x من سدرة المنتهى للكتب بإجمالي $t د.ل.";
  }
}
$orders = tx(function(&$d){ return $d; }); if(!is_array($orders)) $orders = [];
$f = isset($_GET['f']) ? (string)$_GET['f'] : '';
$count = array_fill_keys(array_keys(STATUS), 0);
foreach($orders as $o){ if(isset($count[$o['status']])) $count[$o['status']]++; }
$list = (isset(STATUS[$f])) ? array_values(array_filter($orders, function($o) use ($f){ return $o['status'] === $f; })) : $orders;
head('إدارة الطلبات');
echo '<div class="t"><h1>📦 الطلبات ('.count($orders).')</h1><a class="btn" href="?out=1">خروج</a></div>';
if(isset($_GET['m']) && $_GET['m'] === 'pw') echo '<div class="c" style="background:#e7f6ea">تم تغيير كلمة المرور ✅</div>';
echo '<div class="f"><a href="admin.php">الكل</a>';
foreach(STATUS as $k => $v){ echo '<a href="?f='.$k.'">'.h($v).' ('.$count[$k].')</a>'; }
echo '</div>';
if(!$list) echo '<div class="c">لا توجد طلبات.</div>';
foreach($list as $o){
  echo '<div class="c"><div class="t"><b dir="ltr">'.h($o['num']).'</b><span class="b '.h($o['status']).'">'.h(STATUS[$o['status']]).'</span></div>';
  echo '<p>'.h($o['date']).' · '.($o['pay'] === 'bank' ? '🏦 تحويل مصرفي' : '💵 عند الاستلام').'</p>';
  echo '<p><b>'.h($o['name']).'</b> · <a href="tel:'.h($o['phone']).'" dir="ltr">'.h($o['phone']).'</a><br>'.h($o['addr']).(isset($o['city']) ? '<br>📍 '.h($o['city']).' - '.h($o['area']).' (التوصيل '.number_format($o['fee'], 2).' د.ل)' : '').'</p>';
  if(!empty($o['note'])) echo '<p>📝 '.h($o['note']).'</p>';
  echo '<table>';
  foreach($o['items'] as $l){ echo '<tr><td>'.h($l['name']).' × '.(int)$l['qty'].'</td><td dir="ltr" style="text-align:left">'.number_format($l['price'] * $l['qty'], 2).'</td></tr>'; }
  if(isset($o['fee'])) echo '<tr><td>رسوم التوصيل</td><td dir="ltr" style="text-align:left">'.number_format($o['fee'], 2).'</td></tr>'; echo '<tr><td><b>الإجمالي</b></td><td dir="ltr" style="text-align:left"><b>'.number_format($o['total'], 2).' د.ل</b></td></tr></table>';
  echo '<form method="post" class="t"><input type="hidden" name="act" value="status"><input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'"><input type="hidden" name="num" value="'.h($o['num']).'"><select name="status">';
  foreach(STATUS as $k => $v){ echo '<option value="'.$k.'"'.($o['status'] === $k ? ' selected' : '').'>'.h($v).'</option>'; }
  echo '</select><button>حفظ الحالة</button><a class="btn g" target="_blank" rel="noopener" href="https://wa.me/'.h(norm_phone($o['phone'])).'?text='.rawurlencode(wa_msg($o)).'">💬 مراسلة الزبون</a></form>';
  echo '<form method="post" onsubmit="return confirm(\'حذف هذا الطلب نهائياً؟\')" style="margin-top:8px"><input type="hidden" name="act" value="del"><input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'"><input type="hidden" name="num" value="'.h($o['num']).'"><button class="d">حذف</button></form></div>';
}
echo '<div class="c"><b>تغيير كلمة المرور</b><form method="post" style="margin-top:8px"><input type="hidden" name="act" value="pw"><input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'"><input type="password" name="np" placeholder="كلمة مرور جديدة (8 أحرف على الأقل)" minlength="8"> <button>تغيير</button></form></div><script>setInterval(function(){var a=document.activeElement;if(!a||a.tagName==='BODY')location.reload()},60000)</script></body></html>';
