<?php
require __DIR__.'/lib.php';
$P = products();

// تتبع الطلب: order.php?action=track&n=SM-..&p=رقم الهاتف
if($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['action']) ? $_GET['action'] : '') === 'track'){
  $n = strtoupper(trim((string)(isset($_GET['n']) ? $_GET['n'] : '')));
  $ph = substr(preg_replace('/\D/', '', (string)(isset($_GET['p']) ? $_GET['p'] : '')), -7);
  if($n === '' || strlen($ph) < 7) jout(['ok'=>false, 'msg'=>'أدخل رقم الطلب ورقم الهاتف']);
  $orders = tx(function(&$d){ return $d; });
  foreach((array)$orders as $o){
    if($o['num'] === $n && substr($o['phone'], -7) === $ph){
      jout(['ok'=>true, 'num'=>$o['num'], 'status'=>$o['status'], 'total'=>$o['total'], 'date'=>$o['date']]);
    }
  }
  jout(['ok'=>false, 'msg'=>'لم نجد طلباً بهذه البيانات']);
}

if($_SERVER['REQUEST_METHOD'] !== 'POST') jout(['ok'=>false, 'msg'=>'طلب غير صالح'], 405);
$in = json_decode(file_get_contents('php://input'), true);
if(!is_array($in)) jout(['ok'=>false, 'msg'=>'بيانات غير صالحة'], 400);
if(!empty($in['website'])) jout(['ok'=>true, 'num'=>'SM-000000-000']); // فخ للرسائل الآلية

$name  = trim((string)(isset($in['name']) ? $in['name'] : ''));
$phone = preg_replace('/\D/', '', (string)(isset($in['phone']) ? $in['phone'] : ''));
$addr  = trim((string)(isset($in['addr']) ? $in['addr'] : ''));
$note  = mb_substr(trim((string)(isset($in['note']) ? $in['note'] : '')), 0, 300);
$pay   = (isset($in['pay']) && $in['pay'] === 'bank') ? 'bank' : 'cod';
if(mb_strlen($name) < 2 || mb_strlen($name) > 60) jout(['ok'=>false, 'msg'=>'يرجى إدخال اسم صحيح'], 422);
if(strlen($phone) < 8 || strlen($phone) > 15) jout(['ok'=>false, 'msg'=>'يرجى إدخال رقم هاتف صحيح'], 422);
if(mb_strlen($addr) < 3 || mb_strlen($addr) > 200) jout(['ok'=>false, 'msg'=>'يرجى إدخال تفاصيل العنوان'], 422);
$city = trim((string)(isset($in['city']) ? $in['city'] : '')); $area = trim((string)(isset($in['area']) ? $in['area'] : ''));
$fee = delivery_fee($city, $area);
if($fee === null) jout(['ok'=>false, 'msg'=>'يرجى اختيار المدينة والمنطقة'], 422);

$items = [];
foreach((array)(isset($in['items']) ? $in['items'] : []) as $it){
  $id = (int)(isset($it['id']) ? $it['id'] : -1);
  $q  = (int)(isset($it['qty']) ? $it['qty'] : 0);
  if(!isset($P[$id]) || $P[$id][1] <= 0 || $q < 1 || $q > 20) jout(['ok'=>false, 'msg'=>'يوجد منتج غير صالح في السلة'], 422);
  $items[$id] = (isset($items[$id]) ? $items[$id] : 0) + $q;
}
if(!$items || count($items) > 30) jout(['ok'=>false, 'msg'=>'السلة فارغة'], 422);
$lines = []; $total = 0;
foreach($items as $id => $q){
  $total += $P[$id][1] * $q;
  $lines[] = ['id'=>$id, 'name'=>$P[$id][0], 'qty'=>$q, 'price'=>$P[$id][1]];
}
$subtotal = $total; $total += $fee;
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

$res = tx(function(&$d) use ($name, $phone, $addr, $note, $pay, $lines, $total, $ip, $city, $area, $fee, $subtotal){
  $now = time(); $recent = 0;
  foreach($d as $o){ if(isset($o['ip']) && $o['ip'] === $ip && $now - $o['ts'] < 3600) $recent++; }
  if($recent >= 5) return ['limit'=>true];
  $day = date('ymd'); $c = 1;
  foreach($d as $o){ if(strpos($o['num'], 'SM-'.$day.'-') === 0) $c++; }
  $num = 'SM-'.$day.'-'.str_pad((string)$c, 3, '0', STR_PAD_LEFT);
  array_unshift($d, ['num'=>$num, 'ts'=>$now, 'date'=>date('Y-m-d H:i'), 'name'=>$name, 'phone'=>$phone, 'addr'=>$addr, 'city'=>$city, 'area'=>$area, 'fee'=>$fee, 'subtotal'=>$subtotal, 'note'=>$note, 'pay'=>$pay, 'items'=>$lines, 'total'=>$total, 'status'=>'new', 'ip'=>$ip]);
  return ['num'=>$num];
});
if(!$res) jout(['ok'=>false], 500);
if(!empty($res['limit'])) jout(['ok'=>false, 'msg'=>'عدد كبير من الطلبات خلال وقت قصير، تواصل معنا عبر واتساب'], 429);

// إشعار بالبريد (قد يصل أحياناً إلى البريد المزعج)
$body = "طلب جديد: ".$res['num']."\nالاسم: $name\nالهاتف: $phone\nالتوصيل: $city - $area ($fee د.ل)\nالعنوان: $addr\nالدفع: ".($pay === 'bank' ? 'تحويل مصرفي' : 'عند الاستلام')."\n";
foreach($lines as $l){ $body .= "- ".$l['name']." × ".$l['qty']."\n"; }
$body .= "الإجمالي: ".number_format($total, 2)." د.ل\n".($note ? "ملاحظات: $note\n" : '');
$host = preg_replace('/^www\./', '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
@mail(OWNER_EMAIL, '=?UTF-8?B?'.base64_encode('طلب جديد '.$res['num']).'?=', $body, "Content-Type: text/plain; charset=UTF-8\r\nFrom: no-reply@".$host);

jout(['ok'=>true, 'num'=>$res['num'], 'total'=>$total, 'fee'=>$fee, 'pay'=>$pay]);
