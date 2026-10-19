<?php
// Local preview only: php -S 127.0.0.1:4180 server/router.php
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
$public=realpath(dirname(__DIR__).'/public');
$file=realpath($public.'/'.$path);
if ($path!=='/catalog.js' && $file && str_starts_with($file,$public.DIRECTORY_SEPARATOR) && is_file($file)) {
    $types=['css'=>'text/css','js'=>'application/javascript','html'=>'text/html','png'=>'image/png','webp'=>'image/webp','svg'=>'image/svg+xml'];
    header('Content-Type: '.($types[pathinfo($file,PATHINFO_EXTENSION)]??'application/octet-stream'));
    readfile($file); return;
}
require dirname(__DIR__).'/index.php';
