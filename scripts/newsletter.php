<?php
// Run from Hostinger Cron Jobs; never accessible through HTTP.
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/server/newsletter.php';
try {echo 'Mensagens enviadas: '.process_catalog(configuration()).PHP_EOL;}
catch(Throwable $error) {fwrite(STDERR,'Falha na newsletter. Verifique configuração, banco e painel do provedor.'.PHP_EOL);exit(1);}
