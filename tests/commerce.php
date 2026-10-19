<?php
require dirname(__DIR__).'/server/bootstrap.php';
$count=0;
function check(bool $value, string $name): void {global $count;if(!$value)throw new RuntimeException('FALHOU: '.$name);$count++;echo 'OK: '.$name.PHP_EOL;}
function rejects(callable $action): bool {try{$action();return false;}catch(RuntimeException){return true;}}
$ready=readiness(['MERCADO_PAGO_ACCESS_TOKEN'=>'test','MELHOR_ENVIO_TOKEN'=>'test','SHIPPING_CONTACT_EMAIL'=>'test@example.com','PAYMENTS_ENABLED'=>true,'SITE_URL'=>'https://test.example']);
check(!$ready['paymentReady'],'Configuração não verificada bloqueia cobranças');
$products=[['id'=>'bag','name'=>'Bolsa','category'=>'Bolsas','priceCents'=>15990,'stock'=>2,'package'=>['width'=>20,'height'=>10,'length'=>30,'weight'=>0.5]]];
$rows=[['id'=>'bag','size'=>'Não se aplica','qty'=>1,'priceCents'=>1]];
check(validate_items($rows,$products)[0]['priceCents']===15990,'Preço do cliente é ignorado');
check(rejects(fn()=>validate_items([...$rows,...$rows],$products)),'Itens duplicados rejeitados');
check(rejects(fn()=>validate_items([['id'=>'bag','size'=>'Não se aplica','qty'=>3]],$products)),'Limite de estoque validado');
check(rejects(fn()=>validate_items([['id'=>'bag','size'=>'P','qty'=>1]],$products)),'Tamanho inválido rejeitado');
$options=shipping_options([['id'=>1,'name'=>'PAC','company'=>['name'=>'Correios'],'price'=>'10','custom_price'=>'18.50','custom_delivery_time'=>4],['id'=>2,'error'=>'Indisponível']]);
check(count($options)===1 && $options[0]['cents']===1850 && $options[0]['days']===6,'Frete usa valor final e preparação');
check(strlen(secret_token())===72 && preg_match('/^[a-f0-9-]{36}$/',uuid())===1,'Identificadores aleatórios válidos');
check(!email_valid('inválido') && email_valid('teste@example.com'),'Validação de e-mail');
$js=file_get_contents(dirname(__DIR__).'/public/editorial.js');
check(!str_contains($js,'${selectedProduct.source}') && !str_contains($js,'\n${product.source}'),'WhatsApp sem links de imagens');
foreach(catalog() as $product) if($product['category']==='Bolsas') check(str_contains($product['image'],'bolsa-studio-'),'Bolsa usa imagem reformulada');
echo $count.' verificações passaram.'.PHP_EOL;
