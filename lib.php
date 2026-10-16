<?php
// lib.php - مشترك بين order.php و admin.php
date_default_timezone_set('Africa/Tripoli');
const OWNER_EMAIL = 'san.1429x@gmail.com';
const GUARD = "<?php http_response_code(403); exit; ?>\n";
const STATUS = ['new'=>'جديد','confirmed'=>'تم التأكيد','shipped'=>'تم الشحن','delivered'=>'تم التسليم','cancelled'=>'ملغي'];
// جدول الأسعار (رقم المنتج => [الاسم, السعر]) - عدّله عند تغيير الأسعار في index.html
function products(){ return [0=>['البداية و النهاية',390],1=>['كتاب الروح',60],2=>['الرحيق المختوم',60],3=>['روضة المحبين',55],4=>['رياض الصالحين',60],5=>['الطب النبوي',55],6=>['كتاب الكبائر',50],7=>['جوامع الأدعية النبوية',50],8=>['شرح الاربعين النوويه',50],9=>['الأذكار',55],10=>['الداء والدواء',65],11=>['روضة العقلاء',55],12=>['خلاصة تاريخ الأندلس',0]]; }
function delivery_fee($city, $area){
  $f = __DIR__.'/delivery.json'; if(!is_file($f)) return null;
  $d = json_decode((string)file_get_contents($f), true); if(!is_array($d)) return null;
  foreach($d as $c){ if($c[0] === $city){ foreach($c[1] as $a){ if($a[0] === $area) return $a[1]; } } }
  return null;
}
function data_dir(){ $d = __DIR__.'/data'; if(!is_dir($d)) @mkdir($d, 0755, true); return $d; }
function tx($fn){
  $fp = @fopen(data_dir().'/orders.php', 'c+');
  if(!$fp) return null;
  flock($fp, LOCK_EX);
  $s = (string)stream_get_contents($fp);
  $d = json_decode((string)substr($s, strlen(GUARD)), true);
  if(!is_array($d)){
    if(trim($s) !== ''){ flock($fp, LOCK_UN); fclose($fp); return null; }
    $d = [];
  }
  $r = $fn($d);
  ftruncate($fp, 0); rewind($fp);
  fwrite($fp, GUARD.json_encode($d, JSON_UNESCAPED_UNICODE));
  fflush($fp); flock($fp, LOCK_UN); fclose($fp);
  return $r;
}
function norm_phone($p){
  $p = preg_replace('/\D/', '', (string)$p);
  if(strpos($p, '00') === 0) $p = substr($p, 2);
  if(strpos($p, '0') === 0) return '218'.substr($p, 1);
  if(strlen($p) == 9) return '218'.$p;
  return $p;
}
function jout($a, $code = 200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($a, JSON_UNESCAPED_UNICODE);
  exit;
}
