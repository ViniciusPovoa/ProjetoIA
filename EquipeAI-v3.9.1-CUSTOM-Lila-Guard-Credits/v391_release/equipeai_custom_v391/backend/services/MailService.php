<?php
namespace App\services;

use App\utils\Logger;

final class MailService {
    public function send(string $to,string $subject,string $text): bool {
        return $this->sendHtml($to,$subject,nl2br(htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')),$text);
    }

    public function sendHtml(string $to,string $subject,string $html,string $text=''): bool {
        if (!filter_var($to,FILTER_VALIDATE_EMAIL)) return false;
        $subject=$this->safeHeader($subject);
        $text=$text!==''?$text:trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?\s*>/i',"\n",$html)??$html),ENT_QUOTES|ENT_HTML5,'UTF-8'));
        if ((string)env('SMTP_HOST','')!=='') return $this->smtp($to,$subject,$html,$text);

        $from=$this->safeHeader((string)env('MAIL_FROM','no-reply@localhost'));
        $name=$this->safeHeader((string)env('MAIL_FROM_NAME',env('APP_NAME','EquipeAI')));
        $boundary='=_Part_'.bin2hex(random_bytes(12));
        $headers=[
            'From: '.$this->mimeHeader($name).' <'.$from.'>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="'.$boundary.'"',
            'X-Auto-Response-Suppress: All',
        ];
        $body=$this->multipartBody($boundary,$text,$html);
        return @mail($to,$this->mimeHeader($subject),$body,implode("\r\n",$headers));
    }

    public function sendWithAttachment(string $to,string $subject,string $html,string $text,string $path,string $filename,string $mime='application/octet-stream'): bool {
        if (!filter_var($to,FILTER_VALIDATE_EMAIL) || !is_file($path)) return false;
        $subject=$this->safeHeader($subject);$filename=$this->safeHeader($filename);
        if ((string)env('SMTP_HOST','')!=='') return $this->smtpAttachment($to,$subject,$html,$text,$path,$filename,$mime);
        $from=$this->safeHeader((string)env('MAIL_FROM','no-reply@localhost'));
        $name=$this->safeHeader((string)env('MAIL_FROM_NAME',env('APP_NAME','EquipeAI')));
        $mixed='=_Mixed_'.bin2hex(random_bytes(12));$alt='=_Alt_'.bin2hex(random_bytes(12));
        $headers=['From: '.$this->mimeHeader($name).' <'.$from.'>','MIME-Version: 1.0','Content-Type: multipart/mixed; boundary="'.$mixed.'"','X-Auto-Response-Suppress: All'];
        $body=$this->multipartMixedBody($mixed,$alt,$text,$html,$path,$filename,$mime);
        return @mail($to,$this->mimeHeader($subject),$body,implode("\r\n",$headers));
    }

    public function verificationTemplate(string $name,string $verificationUrl,int $minutes): array {
        $app=htmlspecialchars((string)env('APP_NAME','EquipeAI'),ENT_QUOTES,'UTF-8');
        $safeName=htmlspecialchars($name,ENT_QUOTES,'UTF-8');
        $safeUrl=htmlspecialchars($verificationUrl,ENT_QUOTES,'UTF-8');
        $support=htmlspecialchars((string)env('MAIL_SUPPORT',env('MAIL_FROM','')),ENT_QUOTES,'UTF-8');
        $year=date('Y');
        $html='<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;background:#f3f5f8;color:#172033;font-family:Arial,Helvetica,sans-serif"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f5f8;padding:34px 14px"><tr><td align="center"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #e1e6ee;border-radius:18px;overflow:hidden"><tr><td style="background:#101827;padding:24px 30px"><table role="presentation" width="100%"><tr><td><span style="display:inline-block;width:30px;height:30px;line-height:30px;text-align:center;border-radius:8px;background:#2f6bff;color:#fff;font-size:12px;letter-spacing:.5px">EA</span><span style="margin-left:10px;color:#fff;font-size:15px;font-weight:500">'.$app.'</span></td><td align="right" style="color:#8fa0bb;font-size:11px">VERIFICAÇÃO DE CONTA</td></tr></table></td></tr><tr><td style="padding:40px 34px 18px"><div style="font-size:12px;color:#58708f;letter-spacing:.08em;text-transform:uppercase;margin-bottom:13px">Confirme seu e-mail</div><h1 style="margin:0 0 16px;font-size:28px;line-height:1.25;font-weight:500;color:#121a29">Só falta validar seu acesso.</h1><p style="margin:0;color:#5e6879;font-size:15px;line-height:1.7">Olá, '.$safeName.'. Recebemos seu cadastro no '.$app.'. Confirme este endereço de e-mail para ativar sua conta e proteger o acesso ao workspace.</p></td></tr><tr><td style="padding:10px 34px 18px"><a href="'.$safeUrl.'" style="display:inline-block;background:#235ee7;color:#ffffff;text-decoration:none;border-radius:10px;padding:14px 21px;font-size:14px;font-weight:500">Confirmar meu e-mail</a></td></tr><tr><td style="padding:8px 34px 34px"><div style="border-top:1px solid #e7ebf1;padding-top:22px"><p style="margin:0 0 8px;color:#697386;font-size:12px;line-height:1.6">Este link expira em '.$minutes.' minutos e só pode ser utilizado uma vez.</p><p style="margin:0 0 10px;color:#697386;font-size:12px;line-height:1.6">Se o botão não abrir, copie este endereço:</p><p style="margin:0;word-break:break-all;color:#235ee7;font-size:11px;line-height:1.6">'.$safeUrl.'</p></div></td></tr><tr><td style="background:#f8fafc;padding:20px 34px;border-top:1px solid #e7ebf1;color:#8a94a5;font-size:11px;line-height:1.6">Se você não criou esta conta, ignore esta mensagem. '.($support!==''?'Suporte: '.$support.'. ':'').'© '.$year.' '.$app.'.</td></tr></table></td></tr></table></body></html>';
        $text="Olá, {$name}.\n\nConfirme seu e-mail para ativar sua conta no ".env('APP_NAME','EquipeAI').":\n{$verificationUrl}\n\nO link expira em {$minutes} minutos e só pode ser usado uma vez.\n\nSe você não criou esta conta, ignore esta mensagem.";
        return [$html,$text];
    }

    public function passwordResetTemplate(string $name,string $resetUrl,int $minutes=30): array {
        $app=htmlspecialchars((string)env('APP_NAME','EquipeAI'),ENT_QUOTES,'UTF-8');
        $safeName=htmlspecialchars($name!==''?$name:'Olá',ENT_QUOTES,'UTF-8');
        $safeUrl=htmlspecialchars($resetUrl,ENT_QUOTES,'UTF-8');
        $support=htmlspecialchars((string)env('MAIL_SUPPORT',env('MAIL_FROM','')),ENT_QUOTES,'UTF-8');
        $year=date('Y');
        $html='<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;background:#f3f5f8;color:#172033;font-family:Arial,Helvetica,sans-serif"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f5f8;padding:34px 14px"><tr><td align="center"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #e1e6ee;border-radius:18px;overflow:hidden"><tr><td style="background:#101827;padding:24px 30px"><span style="display:inline-block;width:30px;height:30px;line-height:30px;text-align:center;border-radius:8px;background:#245fe6;color:#fff;font-size:12px;letter-spacing:.5px">EA</span><span style="margin-left:10px;color:#fff;font-size:15px;font-weight:500">'.$app.'</span></td></tr><tr><td style="padding:40px 34px 18px"><div style="font-size:12px;color:#58708f;letter-spacing:.08em;text-transform:uppercase;margin-bottom:13px">Segurança da conta</div><h1 style="margin:0 0 16px;font-size:28px;line-height:1.25;font-weight:500;color:#121a29">Redefina sua senha.</h1><p style="margin:0;color:#5e6879;font-size:15px;line-height:1.7">'.$safeName.', recebemos uma solicitação para redefinir a senha da sua conta. Use o botão abaixo somente se a solicitação foi feita por você.</p></td></tr><tr><td style="padding:10px 34px 18px"><a href="'.$safeUrl.'" style="display:inline-block;background:#245fe6;color:#fff;text-decoration:none;border-radius:10px;padding:14px 21px;font-size:14px;font-weight:500">Criar nova senha</a></td></tr><tr><td style="padding:8px 34px 34px"><div style="border-top:1px solid #e7ebf1;padding-top:22px"><p style="margin:0 0 8px;color:#697386;font-size:12px;line-height:1.6">Este link expira em '.$minutes.' minutos e só pode ser utilizado uma vez.</p><p style="margin:0;word-break:break-all;color:#245fe6;font-size:11px;line-height:1.6">'.$safeUrl.'</p></div></td></tr><tr><td style="background:#f8fafc;padding:20px 34px;border-top:1px solid #e7ebf1;color:#8a94a5;font-size:11px;line-height:1.6">Se você não solicitou a troca, ignore esta mensagem e sua senha continuará a mesma. '.($support!==''?'Suporte: '.$support.'. ':'').'© '.$year.' '.$app.'.</td></tr></table></td></tr></table></body></html>';
        $text="Olá, {$name}.\n\nUse este link para redefinir sua senha no ".env('APP_NAME','EquipeAI').":\n{$resetUrl}\n\nO link expira em {$minutes} minutos e só pode ser usado uma vez.\n\nSe você não solicitou, ignore esta mensagem.";
        return [$html,$text];
    }

    private function smtp(string $to,string $subject,string $html,string $text): bool {
        $host=(string)env('SMTP_HOST','');$port=(int)env('SMTP_PORT','587');$enc=strtolower((string)env('SMTP_ENCRYPTION','tls'));
        $remote=($enc==='ssl'?'ssl://':'').$host.':'.$port;
        $context=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]]);
        $fp=@stream_socket_client($remote,$errno,$errstr,15,STREAM_CLIENT_CONNECT,$context);
        if(!$fp){Logger::write('errors','smtp_connect',['error'=>$errstr,'code'=>$errno]);return false;}
        stream_set_timeout($fp,15);
        $read=function()use($fp):array{$resp='';$code=0;while(($r=fgets($fp,2048))!==false){$resp.=$r;$code=(int)substr($r,0,3);if(strlen($r)<4||$r[3]!=='-')break;}return [$code,$resp];};
        $cmd=function(string $line,array $ok=[250])use($fp,$read):bool{fwrite($fp,$line."\r\n");[$code]=$read();return in_array($code,$ok,true);};
        [$hello]=$read();if($hello!==220){fclose($fp);return false;}
        $domain=$_SERVER['SERVER_NAME']??'localhost';if(!$cmd('EHLO '.$domain,[250])){fclose($fp);return false;}
        if($enc==='tls'){
            if(!$cmd('STARTTLS',[220])||!@stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)){fclose($fp);return false;}
            if(!$cmd('EHLO '.$domain,[250])){fclose($fp);return false;}
        }
        $user=(string)env('SMTP_USER','');$pass=(string)env('SMTP_PASSWORD','');
        if($user!==''){
            if(!$cmd('AUTH LOGIN',[334])||!$cmd(base64_encode($user),[334])||!$cmd(base64_encode($pass),[235])){Logger::write('errors','smtp_auth',['host'=>$host]);fclose($fp);return false;}
        }
        $from=$this->safeHeader((string)env('MAIL_FROM','no-reply@localhost'));
        $name=$this->safeHeader((string)env('MAIL_FROM_NAME',env('APP_NAME','EquipeAI')));
        if(!$cmd('MAIL FROM:<'.$from.'>',[250])||!$cmd('RCPT TO:<'.$to.'>',[250,251])||!$cmd('DATA',[354])){fclose($fp);return false;}
        $boundary='=_Part_'.bin2hex(random_bytes(12));
        $messageId='<'.bin2hex(random_bytes(12)).'@'.($domain?:'localhost').'>';
        $headers=[
            'Date: '.date(DATE_RFC2822),
            'Message-ID: '.$messageId,
            'From: '.$this->mimeHeader($name).' <'.$from.'>',
            'To: <'.$to.'>',
            'Subject: '.$this->mimeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="'.$boundary.'"',
            'X-Auto-Response-Suppress: All'
        ];
        $body=implode("\r\n",$headers)."\r\n\r\n".$this->multipartBody($boundary,$text,$html);
        $body=preg_replace('/(?m)^\./','..',$body)??$body;
        fwrite($fp,$body."\r\n.\r\n");[$code]=$read();$ok=$code>=200&&$code<300;
        $cmd('QUIT',[221,250]);fclose($fp);
        if(!$ok)Logger::write('errors','smtp_send_failed',['code'=>$code,'host'=>$host]);
        return $ok;
    }

    private function smtpAttachment(string $to,string $subject,string $html,string $text,string $path,string $filename,string $mime): bool {
        $host=(string)env('SMTP_HOST','');$port=(int)env('SMTP_PORT','587');$enc=strtolower((string)env('SMTP_ENCRYPTION','tls'));
        $remote=($enc==='ssl'?'ssl://':'').$host.':'.$port;$context=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]]);
        $fp=@stream_socket_client($remote,$errno,$errstr,15,STREAM_CLIENT_CONNECT,$context);if(!$fp){Logger::write('errors','smtp_connect',['error'=>$errstr,'code'=>$errno]);return false;}stream_set_timeout($fp,20);
        $read=function()use($fp):array{$resp='';$code=0;while(($r=fgets($fp,2048))!==false){$resp.=$r;$code=(int)substr($r,0,3);if(strlen($r)<4||$r[3]!=='-')break;}return [$code,$resp];};
        $cmd=function(string $line,array $ok=[250])use($fp,$read):bool{fwrite($fp,$line."\r\n");[$code]=$read();return in_array($code,$ok,true);};
        [$hello]=$read();if($hello!==220){fclose($fp);return false;}$domain=$_SERVER['SERVER_NAME']??'localhost';if(!$cmd('EHLO '.$domain,[250])){fclose($fp);return false;}
        if($enc==='tls'){if(!$cmd('STARTTLS',[220])||!@stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)){fclose($fp);return false;}if(!$cmd('EHLO '.$domain,[250])){fclose($fp);return false;}}
        $user=(string)env('SMTP_USER','');$pass=(string)env('SMTP_PASSWORD','');if($user!==''&&(!$cmd('AUTH LOGIN',[334])||!$cmd(base64_encode($user),[334])||!$cmd(base64_encode($pass),[235]))){fclose($fp);return false;}
        $from=$this->safeHeader((string)env('MAIL_FROM','no-reply@localhost'));$name=$this->safeHeader((string)env('MAIL_FROM_NAME',env('APP_NAME','EquipeAI')));
        if(!$cmd('MAIL FROM:<'.$from.'>',[250])||!$cmd('RCPT TO:<'.$to.'>',[250,251])||!$cmd('DATA',[354])){fclose($fp);return false;}
        $mixed='=_Mixed_'.bin2hex(random_bytes(12));$alt='=_Alt_'.bin2hex(random_bytes(12));$headers=['Date: '.date(DATE_RFC2822),'Message-ID: <'.bin2hex(random_bytes(12)).'@'.($domain?:'localhost').'>','From: '.$this->mimeHeader($name).' <'.$from.'>','To: <'.$to.'>','Subject: '.$this->mimeHeader($subject),'MIME-Version: 1.0','Content-Type: multipart/mixed; boundary="'.$mixed.'"','X-Auto-Response-Suppress: All'];
        $body=implode("\r\n",$headers)."\r\n\r\n".$this->multipartMixedBody($mixed,$alt,$text,$html,$path,$filename,$mime);$body=preg_replace('/(?m)^\./','..',$body)??$body;fwrite($fp,$body."\r\n.\r\n");[$code]=$read();$ok=$code>=200&&$code<300;$cmd('QUIT',[221,250]);fclose($fp);return $ok;
    }

    private function multipartMixedBody(string $mixed,string $alt,string $text,string $html,string $path,string $filename,string $mime): string {
        $attachment=chunk_split(base64_encode((string)file_get_contents($path)),76,"\r\n");
        $out='--'.$mixed."\r\nContent-Type: multipart/alternative; boundary=\"".$alt."\"\r\n\r\n";
        $out.='--'.$alt."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".$this->normalize($text)."\r\n";
        $out.='--'.$alt."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".$this->normalize($html)."\r\n--".$alt."--\r\n";
        $out.='--'.$mixed."\r\nContent-Type: ".$mime."; name=\"".$filename."\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"".$filename."\"\r\n\r\n".$attachment."\r\n--".$mixed."--\r\n";
        return $out;
    }

    private function multipartBody(string $boundary,string $text,string $html): string {
        return '--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".$this->normalize($text)."\r\n--".$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".$this->normalize($html)."\r\n--".$boundary."--\r\n";
    }
    private function normalize(string $value): string { return preg_replace("/\r\n|\r|\n/","\r\n",$value)??$value; }
    private function safeHeader(string $value): string { return trim(str_replace(["\r","\n"],' ',$value)); }
    private function mimeHeader(string $value): string { return function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($value,'UTF-8','B','\r\n') : $this->safeHeader($value); }
}
