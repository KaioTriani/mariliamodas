<?php
declare(strict_types=1);
require_once __DIR__.'/server/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
try {
    if (str_starts_with($path,'/api/')) {require __DIR__.'/server/api.php'; api($path);}
    if ($path==='/catalog.js') {
        header('Content-Type: application/javascript; charset=utf-8'); header('Cache-Control: no-cache');
        $products=array_map(function($p){unset($p['priceCents'],$p['stock'],$p['package']);return $p;},catalog());
        echo 'const products = '.json_encode($products,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR).';'; exit;
    }
    if ($path==='/' || $path==='/index.php') {header('Content-Type: text/html; charset=utf-8'); readfile(__DIR__.'/public/store.html'); exit;}
    http_response_code(404); echo 'Página não encontrada.';
} catch(Throwable $error) {
    $status=$error instanceof RuntimeException && $error->getCode()>=400 && $error->getCode()<=599 ? $error->getCode() : 500;
    if ($status===500) error_log('Marilia backend failure: '.get_class($error));
    respond(['error'=>$status===500?'Não foi possível concluir. Tente novamente ou fale com a loja.':$error->getMessage()],$status);
}
