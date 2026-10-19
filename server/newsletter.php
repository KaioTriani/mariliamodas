<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';

function process_catalog(array $env): int {
    if (!readiness($env)['newsletterReady']) return 0;
    $db=database($env); $owner=uuid(); $now=time()*1000; $current=version();
    $db->beginTransaction();
    try {
        query($db,"INSERT INTO sync_lock (id,owner,expires) VALUES ('catalog','',0) ON DUPLICATE KEY UPDATE id=id");
        $lock=query($db,"SELECT * FROM sync_lock WHERE id='catalog' FOR UPDATE")->fetch();
        if ($lock['expires']>$now) {$db->commit();return 0;}
        query($db,"UPDATE sync_lock SET owner=?,expires=? WHERE id='catalog'",[$owner,$now+300000]); $db->commit();
    } catch(Throwable $error) {$db->rollBack();throw $error;}
    $sent=0;
    try {
        $subscribers=query($db,"SELECT * FROM subscribers s WHERE status='active' AND version<>? AND NOT EXISTS (SELECT 1 FROM deliveries d WHERE d.email=s.email AND d.version=? AND d.status='review') LIMIT 10",[$current,$current])->fetchAll();
        foreach ($subscribers as $subscriber) {
            $id=hash('sha256',$subscriber['email'].':'.$current);
            query($db,"INSERT INTO deliveries (id,email,version,status,created_at) VALUES (?,?,?,'pending',?) ON DUPLICATE KEY UPDATE id=id",[$id,$subscriber['email'],$current,$now]);
            $delivery=query($db,'SELECT * FROM deliveries WHERE id=?',[$id])->fetch();
            if ($delivery['status']==='sent') {query($db,'UPDATE subscribers SET version=? WHERE email=?',[$current,$subscriber['email']]);continue;}
            if ($now-$delivery['created_at']>23*3600000) {query($db,"UPDATE deliveries SET status='review' WHERE id=?",[$id]);continue;}
            if (!query($db,"SELECT email FROM subscribers WHERE email=? AND status='active'",[$subscriber['email']])->fetch()) continue;
            $base=htmlspecialchars(rtrim($env['SITE_URL'],'/'),ENT_QUOTES,'UTF-8');
            $html='<div style="max-width:560px;margin:40px auto;font-family:Georgia;color:#423b32"><p>MARÍLIA MODAS</p><h1>Um novo olhar para o seu próximo look.</h1><p>Nosso catálogo foi atualizado. Venha conhecer as peças e escolher seus próximos favoritos.</p><p><a href="'.$base.'">Explorar o catálogo</a></p><hr><p style="font-size:12px">Você se inscreveu para receber nossas novidades. <a href="'.$base.'/api/newsletter/unsubscribe?token='.$subscriber['token'].'">Cancelar inscrição</a></p></div>';
            provider('https://api.resend.com/emails',$env['RESEND_API_KEY'],['from'=>$env['NEWSLETTER_FROM'],'to'=>[$subscriber['email']],'subject'=>'Novas escolhas esperam por você · Marília Modas','html'=>$html],['Idempotency-Key: '.$id]);
            $db->beginTransaction();
            try {query($db,"UPDATE deliveries SET status='sent' WHERE id=?",[$id]);query($db,'UPDATE subscribers SET version=? WHERE email=?',[$current,$subscriber['email']]);$db->commit();}
            catch(Throwable $error) {$db->rollBack();throw $error;}
            $sent++;usleep(650000);
        }
    } finally {query($db,"DELETE FROM sync_lock WHERE id='catalog' AND owner=?",[$owner]);}
    return $sent;
}
