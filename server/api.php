<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';

function api(string $path): never {
    $env=configuration(); $ready=readiness($env); $method=$_SERVER['REQUEST_METHOD'];
    if ($path==='/api/commerce/status' && $method==='GET') {
        $products=array_map(function($p){return ['id'=>$p['id'],'name'=>$p['name'],'image'=>$p['image'],'category'=>$p['category'],'color'=>$p['color'],'priceCents'=>settings()['verified']?$p['priceCents']:null];},catalog());
        respond([...$ready,'originCity'=>'Petrolina · PE','products'=>$products]);
    }
    if ($path==='/api/newsletter/unsubscribe' && $method==='GET') {
        $token=$_GET['token']??'';
        if (!is_string($token) || !preg_match('/^[a-f0-9]{72}$/',$token)) problem('Link inválido.');
        header('Content-Type: text/html; charset=utf-8'); header('Referrer-Policy: no-referrer'); header('Cache-Control: no-store');
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Preferências · Marília Modas</title><link rel="stylesheet" href="/commerce.css"><main class="legal"><h1>Suas preferências</h1><p>Deseja parar de receber novidades da Marília Modas?</p><form method="post"><input type="hidden" name="token" value="'.$token.'"><button>Cancelar inscrição</button></form><p><a href="/">Voltar à loja</a></p></main></html>'; exit;
    }
    if ($method!=='POST') respond(['error'=>'Rota não encontrada.'],404);
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    $expected=rtrim($env['SITE_URL']??'', '/');
    if (!$expected) $expected=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'');
    if ($origin!==$expected) problem('Origem da solicitação inválida.',403);
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>20000) problem('Solicitação muito grande.',413);
    $raw=file_get_contents('php://input',false,null,0,20001);
    if (strlen($raw)>20000) problem('Solicitação muito grande.',413);
    if ($path==='/api/newsletter/unsubscribe') { parse_str($raw,$body); }
    else { try {$body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);} catch(JsonException) {problem('Solicitação inválida.');} }
    if (!is_array($body)) problem('Solicitação inválida.');
    $db=database($env); rate_limit($db,$path);
    if ($path==='/api/newsletter') {
        if (!empty($body['website'])) respond(['message'=>'Inscrição recebida.']);
        if (!email_valid($body['email']??null) || ($body['consent']??false)!==true) problem('Informe seu e-mail e autorize o recebimento das novidades.');
        query($db,"INSERT INTO subscribers (email,token,status,consent_at,version) VALUES (?,?,'active',?,?) ON DUPLICATE KEY UPDATE email=email",[strtolower(trim($body['email'])),secret_token(),gmdate('c'),version()]);
        respond(['message'=>$ready['newsletterReady']?'Inscrição recebida. Você receberá as próximas novidades do catálogo.':'Inscrição registrada. Os avisos começarão quando o serviço de e-mail for ativado.']);
    }
    if ($path==='/api/newsletter/unsubscribe') {
        if (!is_string($body['token']??null) || !preg_match('/^[a-f0-9]{72}$/',$body['token'])) problem('Link inválido.');
        query($db,"UPDATE subscribers SET status='unsubscribed' WHERE token=?",[$body['token']]);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="/commerce.css"><main class="legal"><h1>Inscrição cancelada</h1><p>Você não receberá novas mensagens do catálogo.</p><a href="/">Voltar à loja</a></main></html>'; exit;
    }
    if ($path==='/api/shipping') {
        $cep=preg_replace('/\D/','',(string)($body['postalCode']??''));
        if (!preg_match('/^\d{8}$/',$cep)) problem('Informe um CEP com 8 dígitos.');
        if (!$ready['shippingReady']) problem('Consulte o frete pelo WhatsApp enquanto finalizamos a conexão com a transportadora.',503);
        $items=validate_items($body['items']??null);
        $rates=provider('https://www.melhorenvio.com.br/api/v2/me/shipment/calculate',$env['MELHOR_ENVIO_TOKEN'],['from'=>['postal_code'=>settings()['originPostalCode']],'to'=>['postal_code'=>$cep],'products'=>array_map(fn($p)=>['id'=>$p['id'],...$p['package'],'insurance_value'=>$p['priceCents']/100,'quantity'=>$p['qty']],$items),'options'=>['receipt'=>false,'own_hand'=>false]],['User-Agent: Marilia Modas ('.$env['SHIPPING_CONTACT_EMAIL'].')']);
        $options=shipping_options($rates);
        if (!$options) problem('Não há opções automáticas para este CEP. Consulte a loja.',409);
        $id=uuid(); $expires=(time()+900)*1000;
        query($db,'INSERT INTO quotes (id,postal_code,items,options,expires) VALUES (?,?,?,?,?)',[$id,$cep,json_encode($items),json_encode($options),$expires]);
        respond(['quoteId'=>$id,'options'=>$options,'expires'=>$expires]);
    }
    if ($path==='/api/checkout') {
        if (!$ready['paymentReady']) problem('O pagamento online estará disponível em breve. Finalize com a loja pelo WhatsApp.',503);
        if (!is_string($body['attempt']??null) || !preg_match('/^[a-f0-9-]{36}$/',$body['attempt'])) problem('Atualize a página e tente novamente.');
        $quote=query($db,'SELECT * FROM quotes WHERE id=?',[(string)($body['quoteId']??'')])->fetch();
        if (!$quote || $quote['expires']<time()*1000) problem('Calcule o frete novamente: a cotação expirou.',409);
        $items=validate_items($body['items']??null); $option=null;
        foreach(json_decode($quote['options'],true) as $o) if ($o['id']===(string)($body['shippingId']??'')) $option=$o;
        if (json_encode($items)!==$quote['items'] || !$option) problem('Sua seleção mudou. Calcule o frete novamente.',409);
        $customer=$body['customer']??[]; $address=$body['address']??[];
        if (!email_valid($customer['email']??null) || !is_string($customer['name']??null) || strlen(trim($customer['name']))<3 || strlen($customer['name'])>120) problem('Informe nome e e-mail válidos.');
        foreach(['street','number','district','city','state'] as $key) if (!is_string($address[$key]??null) || !strlen(trim($address[$key])) || strlen($address[$key])>150) problem('Revise o endereço de entrega.');
        if (preg_replace('/\D/','',(string)($address['postalCode']??''))!==$quote['postal_code'] || !preg_match('/^[A-Z]{2}$/',$address['state'])) problem('Revise o endereço de entrega.');
        $total=array_sum(array_map(fn($p)=>$p['priceCents']*$p['qty'],$items))+$option['cents']; $token=secret_token();
        query($db,"INSERT INTO orders (id,quote_id,token,total_cents,status) VALUES (?,?,?,?,'creating') ON DUPLICATE KEY UPDATE id=id",[$body['attempt'],$quote['id'],$token,$total]);
        $order=query($db,'SELECT * FROM orders WHERE id=?',[$body['attempt']])->fetch();
        if ($order['quote_id']!==$quote['id'] || (int)$order['total_cents']!==$total) problem('Inicie uma nova tentativa de pagamento.',409);
        if (!$order['checkout_url']) {
            if ($order['token']!==$token) problem('A tentativa anterior está sendo processada. Aguarde e consulte a loja antes de tentar novamente.',409);
            $base=rtrim($env['SITE_URL'],'/');
            $pref=provider('https://api.mercadopago.com/checkout/preferences',$env['MERCADO_PAGO_ACCESS_TOKEN'],['items'=>array_map(fn($p)=>['id'=>$p['id'],'title'=>$p['name'].' · '.$p['size'],'quantity'=>$p['qty'],'currency_id'=>'BRL','unit_price'=>$p['priceCents']/100],$items),'payer'=>['name'=>$customer['name'],'email'=>$customer['email']],'shipments'=>['cost'=>$option['cents']/100,'mode'=>'not_specified','receiver_address'=>['zip_code'=>$quote['postal_code'],'street_name'=>$address['street'],'street_number'=>$address['number'],'city_name'=>$address['city'],'state_name'=>$address['state'],'apartment'=>substr((string)($address['complement']??''),0,150)]],'external_reference'=>$order['id'],'back_urls'=>['success'=>$base.'/pagamento.html','pending'=>$base.'/pagamento.html','failure'=>$base.'/pagamento.html'],'auto_return'=>'approved','expires'=>true,'expiration_date_to'=>gmdate('Y-m-d\TH:i:s.000\Z',time()+1800)],['X-Idempotency-Key: '.$order['id']]);
            $url=$pref['init_point']??'';
            if (parse_url($url,PHP_URL_SCHEME)!=='https' || parse_url($url,PHP_URL_HOST)!=='www.mercadopago.com.br') problem('Não foi possível abrir o pagamento.',502);
            query($db,"UPDATE orders SET preference_id=?,checkout_url=?,status='pending' WHERE id=?",[(string)$pref['id'],$url,$order['id']]); $order['checkout_url']=$url;
        }
        setcookie('marilia_order',$order['id'].'.'.$order['token'],['expires'=>time()+86400,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
        respond(['url'=>$order['checkout_url']]);
    }
    if ($path==='/api/payment/status') {
        if (empty($env['MERCADO_PAGO_ACCESS_TOKEN']) || !preg_match('/^([a-f0-9-]{36})\.([a-f0-9]{72})$/',$_COOKIE['marilia_order']??'',$matches)) respond(['status'=>'unknown']);
        $order=query($db,'SELECT * FROM orders WHERE id=? AND token=?',[$matches[1],$matches[2]])->fetch();
        if (!$order) respond(['status'=>'unknown']);
        $payments=provider('https://api.mercadopago.com/v1/payments/search?external_reference='.rawurlencode($order['id']),$env['MERCADO_PAGO_ACCESS_TOKEN']); $paid=false;
        foreach($payments['results']??[] as $p) if ($p['status']==='approved' && $p['external_reference']===$order['id'] && $p['currency_id']==='BRL' && (int)round($p['transaction_amount']*100)===(int)$order['total_cents']) $paid=true;
        if ($paid) query($db,"UPDATE orders SET status='approved' WHERE id=?",[$order['id']]);
        respond(['status'=>$paid?'approved':'pending']);
    }
    respond(['error'=>'Rota não encontrada.'],404);
}
