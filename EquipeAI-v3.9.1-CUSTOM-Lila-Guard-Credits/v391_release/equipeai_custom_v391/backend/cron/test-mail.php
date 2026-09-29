<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/config/bootstrap.php';

use App\services\MailService;

$email=$argv[1]??'';
if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
    fwrite(STDERR,"Uso: php backend/cron/test-mail.php seu@email.com\n");
    exit(2);
}
$mail=new MailService();
$url=rtrim((string)env('FRONTEND_URL','https://ia.vdeveloper.com.br'),'/').'/verificar-email?token=TESTE-DE-CONFIGURACAO';
[$html,$text]=$mail->verificationTemplate('Teste de entrega',$url,(int)env('EMAIL_VERIFY_MINUTES','60'));
$ok=$mail->sendHtml($email,'Teste de e-mail — '.env('APP_NAME','EquipeAI'),$html,$text);
echo $ok?"E-mail aceito pelo transporte configurado.\n":"Falha no envio. Consulte backend/storage/logs/errors.log e revise o SMTP.\n";
exit($ok?0:1);
