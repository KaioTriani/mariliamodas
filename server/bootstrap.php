<?php
declare(strict_types=1);
function configuration(): array {
    $file = dirname(__DIR__) . '/config/local.php';
    $config = is_file($file) ? require $file : [];
    foreach (['SITE_URL','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD','MERCADO_PAGO_ACCESS_TOKEN','MELHOR_ENVIO_TOKEN','SHIPPING_CONTACT_EMAIL','RESEND_API_KEY','NEWSLETTER_FROM','PAYMENTS_ENABLED','NEWSLETTER_ENABLED'] as $key) {
        $value = getenv($key);
        if ($value !== false) $config[$key] = $value;
    }
    return $config;
}
function database(array $config): PDO {
    if (empty($config['DB_NAME']) || empty($config['DB_USER'])) problem('O atendimento online está em preparação. Fale com a loja pelo WhatsApp.', 503);
    return new PDO('mysql:host='.($config['DB_HOST'] ?? 'localhost').';port='.($config['DB_PORT'] ?? 3306).';dbname='.$config['DB_NAME'].';charset=utf8mb4', $config['DB_USER'], $config['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
}
function query(PDO $db, string $sql, array $params=[]): PDOStatement {
    $statement = $db->prepare($sql); $statement->execute($params); return $statement;
}
function catalog(): array { return json_decode(file_get_contents(dirname(__DIR__).'/config/catalog.json'), true, 512, JSON_THROW_ON_ERROR); }
function settings(): array { return json_decode(file_get_contents(dirname(__DIR__).'/config/commerce.json'), true, 512, JSON_THROW_ON_ERROR); }
function problem(string $message, int $status=400): never { throw new RuntimeException($message, $status); }
function respond(array $body, int $status=200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); exit;
}
function enabled(mixed $value): bool { return filter_var($value, FILTER_VALIDATE_BOOLEAN); }
function readiness(array $env, ?array $store=null): array {
    $store ??= settings();
    $shipping = !empty($store['verified']) && preg_match('/^\d{8}$/', $store['originPostalCode'] ?? '') && !empty($env['MELHOR_ENVIO_TOKEN']) && !empty($env['SHIPPING_CONTACT_EMAIL']);
    $https = str_starts_with($env['SITE_URL'] ?? '', 'https://');
    return ['shippingReady'=>(bool)$shipping, 'paymentReady'=>$shipping && $https && !empty($env['MERCADO_PAGO_ACCESS_TOKEN']) && enabled($env['PAYMENTS_ENABLED'] ?? false), 'newsletterReady'=>$https && !empty($env['RESEND_API_KEY']) && !empty($env['NEWSLETTER_FROM']) && enabled($env['NEWSLETTER_ENABLED'] ?? false)];
}
function uuid(): string { $h=bin2hex(random_bytes(16)); return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-a'.substr($h,17,3).'-'.substr($h,20); }
function secret_token(): string { return bin2hex(random_bytes(36)); }
function version(): string { return hash('sha256', json_encode(catalog(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }
function email_valid(mixed $email): bool { return is_string($email) && strlen($email)<=254 && filter_var($email,FILTER_VALIDATE_EMAIL)!==false; }
function provider(string $url, string $key, ?array $body=null, array $headers=[]): array {
    $curl=curl_init($url);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>array_merge(['Authorization: Bearer '.$key,'Content-Type: application/json'],$headers)]);
    if ($body !== null) curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body,JSON_THROW_ON_ERROR)]);
    $result=curl_exec($curl); $status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
    if ($result===false || $status<200 || $status>=300) problem('O serviço está indisponível no momento. Tente novamente ou fale com a loja.',502);
    return json_decode($result,true,512,JSON_THROW_ON_ERROR);
}
function validate_items(mixed $rows, ?array $products=null): array {
    if (!is_array($rows) || !array_is_list($rows) || !count($rows) || count($rows)>30) problem('Revise os itens da sacola.');
    $products ??= catalog(); $result=[]; $seen=[]; $counts=[];
    foreach ($rows as $row) {
        if (!is_array($row)) problem('Item inválido.');
        $p=null; foreach ($products as $product) if ($product['id']===($row['id']??null)) $p=$product;
        if (!$p || !is_int($row['qty']??null) || $row['qty']<1 || $row['qty']>10 || !is_string($row['size']??null)) problem('Item ou quantidade inválida.');
        $key=$p['id'].':'.$row['size']; if (isset($seen[$key])) problem('Há itens duplicados na sacola.'); $seen[$key]=true;
        $counts[$p['id']]=($counts[$p['id']]??0)+$row['qty'];
        if (!is_int($p['priceCents']) || $p['priceCents']<=0 || !is_int($p['stock']) || $counts[$p['id']]>$p['stock']) problem('Confirme valor e disponibilidade pelo WhatsApp.',409);
        if ($p['category']==='Bolsas' ? $row['size']!=='Não se aplica' : !in_array($row['size'],$p['availableSizes']??[],true)) problem('Confirme o tamanho da peça com a loja.',409);
        foreach (['width','height','length','weight'] as $dimension) if (!is_numeric($p['package'][$dimension]??null) || $p['package'][$dimension]<=0) problem('A embalagem desta peça precisa ser confirmada.',409);
        $result[]=['id'=>$p['id'],'size'=>$row['size'],'qty'=>$row['qty'],'name'=>$p['name'],'priceCents'=>$p['priceCents'],'package'=>$p['package']];
    }
    return $result;
}
function shipping_options(array $rates): array {
    $result=[];
    foreach ($rates as $rate) {
        if (!is_array($rate) || !empty($rate['error'])) continue;
        $price=$rate['custom_price']??$rate['price']??null; $days=$rate['custom_delivery_time']??$rate['delivery_time']??null;
        if (!is_numeric($price) || !is_numeric($days) || $price<0 || $days<0) continue;
        $result[]=['id'=>(string)$rate['id'],'name'=>trim(($rate['company']['name']??'').' '.$rate['name']),'cents'=>(int)round($price*100),'days'=>(int)ceil((float)$days)+(int)settings()['dispatchDays']];
    }
    return $result;
}
function rate_limit(PDO $db, string $path): void {
    // REMOTE_ADDR is set by the server; never trust client-supplied forwarding headers.
    $id=hash('sha256',($_SERVER['REMOTE_ADDR']??'local').':'.$path); $bucket=(int)floor(time()/3600);
    $db->beginTransaction();
    try {
        query($db,'INSERT INTO rate_limits (id,bucket,count) VALUES (?,?,1) ON DUPLICATE KEY UPDATE count=IF(bucket=VALUES(bucket),count+1,1),bucket=VALUES(bucket)',[$id,$bucket]);
        $count=query($db,'SELECT count FROM rate_limits WHERE id=?',[$id])->fetchColumn(); $db->commit();
    } catch(Throwable $error) { $db->rollBack(); throw $error; }
    if ($count>($path==='/api/newsletter'?5:50)) problem('Aguarde alguns minutos antes de tentar novamente.',429);
}
