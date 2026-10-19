<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/server/bootstrap.php';
$ok=true;
foreach(['pdo_mysql','curl','json','openssl'] as $extension) {
    $available=extension_loaded($extension);echo $extension.': '.($available?'OK':'AUSENTE').PHP_EOL;$ok=$ok&&$available;
}
try { $db=database(configuration()); foreach(['subscribers','quotes','orders','rate_limits','sync_lock','deliveries'] as $table) query($db,'SELECT 1 FROM '.$table.' LIMIT 1');echo 'Banco e tabelas: OK'.PHP_EOL; }
catch(Throwable) {echo 'Banco: configure config/local.php e importe database/schema.sql'.PHP_EOL;$ok=false;}
exit($ok?0:1);
