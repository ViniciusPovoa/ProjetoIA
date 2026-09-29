<?php
namespace App\services;

use App\config\Database;
use App\middleware\SecurityMiddleware;
use App\repositories\UserRepository;
use App\utils\ApiException;
use App\utils\Request;
use App\utils\Validator;
use RuntimeException;

final class AuthService {
    public function register(array $d): array {
        $name=Validator::clean($d['name']??'',120);
        $email=strtolower(Validator::clean($d['email']??'',190));
        $pass=(string)($d['password']??'');
        $company=Validator::clean($d['company_name']??'',150);
        $terms=filter_var($d['terms_accepted']??false,FILTER_VALIDATE_BOOLEAN);
        $fields=[];
        if(!Validator::fullName($name))$fields['name']='Informe seu nome completo usando apenas caracteres válidos.';
        if(!Validator::companyName($company))$fields['company_name']='Informe o nome da empresa (2 a 150 caracteres).';
        if(!Validator::email($email))$fields['email']='Informe um endereço de e-mail válido.';
        $passwordErrors=Validator::passwordErrors($pass);if($passwordErrors)$fields['password']=implode(' ',$passwordErrors);
        if(!$terms)$fields['terms_accepted']='Você precisa aceitar os Termos de Uso e a Política de Privacidade.';
        if($fields)throw new ApiException('Revise os campos destacados e tente novamente.',422,'VALIDATION_ERROR',['fields'=>$fields]);

        $pdo=Database::get();
        $existing=(new UserRepository())->findByEmail($email);
        if($existing){
            if(empty($existing['email_verified_at'])&&($existing['status']??'')==='pending_verification'){
                $sent=$this->issueEmailVerification((int)$existing['id'],$email,(string)$existing['name']);
                return ['message'=>'Este e-mail já possui um cadastro aguardando confirmação. Enviamos um novo link quando possível.','email'=>$this->maskEmail($email),'email_sent'=>$sent,'verification_required'=>true];
            }
            throw new ApiException('Já existe uma conta com este e-mail.',409,'EMAIL_IN_USE');
        }

        $pdo->beginTransaction();
        try{
            $pdo->prepare('INSERT INTO companies(name,email,created_at,updated_at) VALUES(?,?,NOW(),NOW())')->execute([$company,$email]);
            $cid=(int)$pdo->lastInsertId();
            $termsVersion=(string)env('TERMS_VERSION','2026-09');
            $pdo->prepare("INSERT INTO users(company_id,name,email,password_hash,role,status,email_verified_at,terms_accepted_at,privacy_accepted_at,terms_version,created_at,updated_at) VALUES(?,?,?,?, 'user','pending_verification',NULL,NOW(),NOW(),?,NOW(),NOW())")
                ->execute([$cid,$name,$email,password_hash($pass,PASSWORD_DEFAULT),$termsVersion]);
            $uid=(int)$pdo->lastInsertId();
            $free=$pdo->query("SELECT id,duration_days,credits FROM plans WHERE slug='gratis' AND active=1 LIMIT 1")->fetch();
            $welcomeCredits=$free?(int)$free['credits']:20;
            $pdo->prepare('INSERT INTO user_credits(user_id,balance,updated_at) VALUES(?,?,NOW())')->execute([$uid,$welcomeCredits]);
            $pdo->prepare("INSERT INTO credit_transactions(user_id,type,amount,balance_after,description,reference_type,status,created_at) VALUES(?,'credit',?,?,'Créditos de boas-vindas','signup','confirmed',NOW())")->execute([$uid,$welcomeCredits,$welcomeCredits]);
            if($free)$pdo->prepare("INSERT INTO subscriptions(user_id,plan_id,provider,status,starts_at,current_period_start,current_period_end,last_credits_granted_at,created_at,updated_at) VALUES(?,?,'internal','active',NOW(),NOW(),DATE_ADD(NOW(),INTERVAL ? DAY),NOW(),NOW(),NOW())")->execute([$uid,$free['id'],$free['duration_days']]);
            $pdo->commit();
            SecurityMiddleware::log('register_pending_verification',$uid,['domain'=>substr(strrchr($email,'@')?:'',1)]);
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}

        $sent=$this->issueEmailVerification($uid,$email,$name);
        return [
            'message'=>$sent?'Cadastro recebido. Enviamos um link de confirmação para o seu e-mail.':'Cadastro recebido, mas o servidor de e-mail ainda não conseguiu enviar a confirmação. Use a opção de reenviar após configurar o SMTP.',
            'email'=>$this->maskEmail($email),
            'email_sent'=>$sent,
            'verification_required'=>true
        ];
    }

    public function verifyEmail(string $token): array {
        if(strlen($token)<40||strlen($token)>200)throw new ApiException('Link de verificação inválido.',422,'INVALID_VERIFICATION_TOKEN');
        $pdo=Database::get();
        $st=$pdo->prepare('SELECT ev.id,ev.user_id,ev.expires_at,ev.used_at,u.email_verified_at,u.status FROM email_verifications ev JOIN users u ON u.id=ev.user_id WHERE ev.token_hash=? ORDER BY ev.id DESC LIMIT 1');
        $st->execute([hash('sha256',$token)]);$row=$st->fetch();
        if(!$row)throw new ApiException('Este link de verificação é inválido.',422,'INVALID_VERIFICATION_TOKEN');
        if(!empty($row['email_verified_at']))return ['message'=>'Seu e-mail já está confirmado. Você já pode entrar.','already_verified'=>true];
        if(!empty($row['used_at'])||strtotime((string)$row['expires_at'])<time())throw new ApiException('Este link expirou. Solicite um novo e-mail de confirmação.',410,'VERIFICATION_EXPIRED');
        $pdo->beginTransaction();
        try{
            $pdo->prepare('UPDATE email_verifications SET used_at=NOW() WHERE id=? AND used_at IS NULL')->execute([$row['id']]);
            $pdo->prepare("UPDATE users SET email_verified_at=NOW(),status='active',updated_at=NOW() WHERE id=?")->execute([$row['user_id']]);
            $pdo->prepare('DELETE FROM email_verifications WHERE user_id=? AND id<>?')->execute([$row['user_id'],$row['id']]);
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        SecurityMiddleware::log('email_verified',(int)$row['user_id']);
        return ['message'=>'E-mail confirmado com sucesso. Sua conta está pronta para uso.','verified'=>true];
    }

    public function resendVerification(string $email): array {
        $email=strtolower(trim($email));
        if(!Validator::email($email))return ['message'=>'Se existir uma conta aguardando confirmação, um novo link será enviado.'];
        $u=(new UserRepository())->findByEmail($email);
        if($u&&!$u['email_verified_at']&&($u['status']??'')==='pending_verification'){
            $this->issueEmailVerification((int)$u['id'],$email,(string)$u['name']);
            SecurityMiddleware::log('verification_resent',(int)$u['id']);
        }
        return ['message'=>'Se existir uma conta aguardando confirmação, um novo link será enviado.'];
    }

    public function login(string $email,string $password): array {
        $email=strtolower(trim($email));
        $repo=new UserRepository();$u=$repo->findByEmail($email);
        if(!$u||!password_verify($password,(string)$u['password_hash'])){
            SecurityMiddleware::log('login_failed',null,['email_hash'=>hash('sha256',$email)]);
            // A small fixed delay makes repeated credential guessing less attractive without revealing which field failed.
            usleep(180000);
            throw new ApiException('E-mail ou senha inválidos.',401,'INVALID_CREDENTIALS');
        }
        if(($u['status']??'')==='pending_verification'||empty($u['email_verified_at'])){
            SecurityMiddleware::log('login_unverified',(int)$u['id']);
            throw new ApiException('Confirme seu e-mail antes de entrar.',403,'EMAIL_NOT_VERIFIED',['email'=>$this->maskEmail((string)$u['email'])]);
        }
        if($u['status']!=='active')throw new ApiException('Conta bloqueada. Entre em contato com o suporte.',403,'ACCOUNT_BLOCKED');
        Database::get()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$u['id']]);
        SecurityMiddleware::log('login_success',(int)$u['id']);
        return $this->createSession((int)$u['id']);
    }

    public function logout(string $token):void{Database::get()->prepare('DELETE FROM auth_sessions WHERE token_hash=?')->execute([hash('sha256',$token)]);$this->clearCookie();}
    public function refreshCsrf(string $token):string{$csrf=bin2hex(random_bytes(24));Database::get()->prepare('UPDATE auth_sessions SET csrf_token_hash=? WHERE token_hash=? AND expires_at>NOW()')->execute([hash('sha256',$csrf),hash('sha256',$token)]);return $csrf;}

    private function createSession(int $uid): array {
        $token=bin2hex(random_bytes(32));$csrf=bin2hex(random_bytes(24));$days=max(1,(int)env('SESSION_DAYS','30'));$pdo=Database::get();
        $pdo->prepare('DELETE FROM auth_sessions WHERE expires_at<NOW()')->execute();
        // Limit session sprawl. Keep the four newest sessions before inserting the current one.
        $pdo->prepare('DELETE FROM auth_sessions WHERE user_id=? AND id NOT IN (SELECT id FROM (SELECT id FROM auth_sessions WHERE user_id=? ORDER BY created_at DESC LIMIT 4) x)')->execute([$uid,$uid]);
        $pdo->prepare('INSERT INTO auth_sessions(user_id,token_hash,csrf_token_hash,ip,user_agent,expires_at,created_at) VALUES(?,?,?,?,?,DATE_ADD(NOW(),INTERVAL ? DAY),NOW())')->execute([$uid,hash('sha256',$token),hash('sha256',$csrf),Request::ip(),Request::userAgent(),$days]);
        setcookie('saas_session',$token,['expires'=>time()+$days*86400,'path'=>'/','secure'=>(($_SERVER['HTTPS']??'')==='on'),'httponly'=>true,'samesite'=>'Lax']);
        $u=(new UserRepository())->find($uid);return ['token'=>$token,'csrf_token'=>$csrf,'user'=>$u];
    }
    private function clearCookie():void{setcookie('saas_session','',['expires'=>time()-3600,'path'=>'/','secure'=>(($_SERVER['HTTPS']??'')==='on'),'httponly'=>true,'samesite'=>'Lax']);}

    private function issueEmailVerification(int $uid,string $email,string $name): bool {
        $minutes=max(15,min(1440,(int)env('EMAIL_VERIFY_MINUTES','60')));
        $raw=bin2hex(random_bytes(32));$pdo=Database::get();
        $pdo->prepare('DELETE FROM email_verifications WHERE user_id=? OR expires_at<NOW()')->execute([$uid]);
        $pdo->prepare('INSERT INTO email_verifications(user_id,token_hash,expires_at,created_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL ? MINUTE),NOW())')->execute([$uid,hash('sha256',$raw),$minutes]);
        $link=rtrim((string)env('FRONTEND_URL',env('APP_URL','')),'/').'/verificar-email?token='.urlencode($raw);
        [$html,$text]=(new MailService())->verificationTemplate($name,$link,$minutes);
        $sent=(new MailService())->sendHtml($email,'Confirme seu e-mail · '.env('APP_NAME','EquipeAI'),$html,$text);
        SecurityMiddleware::log($sent?'verification_email_sent':'verification_email_failed',$uid);
        return $sent;
    }

    private function maskEmail(string $email):string{
        [$local,$domain]=array_pad(explode('@',$email,2),2,'');
        if($domain==='')return '***';
        $localLen=Validator::length($local);
        $shown=min(2,$localLen);
        $visible=Validator::slice($local,0,$shown);
        return $visible.str_repeat('•',max(3,$localLen-$shown)).'@'.$domain;
    }

    public function forgot(string $email): array {
        $email=strtolower(trim($email));$u=(new UserRepository())->findByEmail($email);
        if($u&&($u['status']??'')==='active'&&!empty($u['email_verified_at'])){
            $raw=bin2hex(random_bytes(32));$pdo=Database::get();
            $pdo->prepare('DELETE FROM password_resets WHERE user_id=? OR expires_at<NOW()')->execute([$u['id']]);
            $pdo->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at,created_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE),NOW())')->execute([$u['id'],hash('sha256',$raw)]);
            $link=rtrim((string)env('FRONTEND_URL',env('APP_URL','')),'/').'/redefinir-senha?token='.urlencode($raw);
            $mailer=new MailService();
            [$html,$text]=$mailer->passwordResetTemplate((string)($u['name']??''),$link,30);
            $mailer->sendHtml($email,'Redefinição de senha · '.env('APP_NAME','EquipeAI'),$html,$text);
            if(env('APP_ENV')!=='production')return ['message'=>'Se o e-mail existir, as instruções foram enviadas.','reset_token'=>$raw];
        }
        return ['message'=>'Se o e-mail existir, as instruções foram enviadas.'];
    }

    public function reset(string $token,string $password):void{
        if(!Validator::password($password))throw new ApiException(implode(' ',Validator::passwordErrors($password)),422,'WEAK_PASSWORD');
        $pdo=Database::get();$st=$pdo->prepare('SELECT id,user_id FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() ORDER BY id DESC LIMIT 1');$st->execute([hash('sha256',$token)]);$r=$st->fetch();
        if(!$r)throw new ApiException('Link inválido ou expirado.',410,'RESET_EXPIRED');
        $pdo->beginTransaction();try{$pdo->prepare('UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$r['user_id']]);$pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')->execute([$r['id']]);$pdo->prepare('DELETE FROM auth_sessions WHERE user_id=?')->execute([$r['user_id']]);$pdo->commit();}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function changePassword(int $uid,string $current,string $new):void{
        if(!Validator::password($new))throw new ApiException(implode(' ',Validator::passwordErrors($new)),422,'WEAK_PASSWORD');
        $s=Database::get()->prepare('SELECT password_hash FROM users WHERE id=?');$s->execute([$uid]);$hash=$s->fetchColumn();
        if(!$hash||!password_verify($current,(string)$hash))throw new ApiException('Senha atual incorreta.',422,'CURRENT_PASSWORD_INVALID');
        Database::get()->prepare('UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$uid]);
        Database::get()->prepare('DELETE FROM auth_sessions WHERE user_id=?')->execute([$uid]);$this->clearCookie();
    }

    public function deleteAccount(int $uid):void{
        $pdo=Database::get();$s=$pdo->prepare('SELECT company_id FROM users WHERE id=?');$s->execute([$uid]);$cid=(int)$s->fetchColumn();$pdo->beginTransaction();
        try{$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);$c=$pdo->prepare('SELECT COUNT(*) FROM users WHERE company_id=?');$c->execute([$cid]);if((int)$c->fetchColumn()===0)$pdo->prepare('DELETE FROM companies WHERE id=?')->execute([$cid]);$pdo->commit();}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        $dir=dirname(__DIR__).'/storage/uploads/'.$cid;if(is_dir($dir)){foreach(glob($dir.'/*')?:[] as $f)if(is_file($f))@unlink($f);@rmdir($dir);}$this->clearCookie();
    }
}
