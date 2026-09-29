<?php
namespace App\services;
use App\config\Database;
final class CompanyContextService {
    public function build(int $companyId): string {
        $pdo=Database::get();$s=$pdo->prepare('SELECT * FROM companies WHERE id=?');$s->execute([$companyId]);$c=$s->fetch()?:[];$ss=$pdo->prepare('SELECT name,description,price,duration,notes FROM company_services WHERE company_id=? AND active=1 ORDER BY id');$ss->execute([$companyId]);$services=$ss->fetchAll();
        $lines=['EMPRESA: '.($c['name']??''),'SEGMENTO: '.($c['segment']??''),'DESCRIÇÃO: '.($c['description']??''),'TELEFONE: '.($c['phone']??''),'WHATSAPP: '.($c['whatsapp']??''),'E-MAIL: '.($c['email']??''),'ENDEREÇO: '.($c['address']??''),'SITE: '.($c['website']??''),'INSTAGRAM: '.($c['instagram']??''),'HORÁRIO: '.($c['business_hours']??''),'PRODUTOS: '.($c['products']??''),'PÚBLICO-ALVO: '.($c['target_audience']??''),'DIFERENCIAIS: '.($c['differentials']??''),'TOM DE COMUNICAÇÃO: '.($c['communication_tone']??'Profissional'),'PALAVRAS A EVITAR: '.($c['avoid_words']??''),'PALAVRAS PREFERIDAS: '.($c['preferred_words']??''),'REGRAS: '.($c['specific_rules']??''),'INFORMAÇÕES ADICIONAIS: '.($c['additional_info']??'')];
        $lines[]='SERVIÇOS:';foreach($services as $v){$price=$v['price']!==null?'R$ '.number_format((float)$v['price'],2,',','.'):'preço não informado';$lines[]='- '.$v['name'].' | '.$price.' | duração: '.($v['duration']?:'não informada').' | '.trim(($v['description']??'').' '.($v['notes']??''));}
        return implode("\n",$lines);
    }

    public function servicesText(int $companyId):string{$s=Database::get()->prepare('SELECT name,description,price,duration,notes FROM company_services WHERE company_id=? AND active=1 ORDER BY id');$s->execute([$companyId]);$lines=[];foreach($s->fetchAll() as $v){$price=$v['price']!==null?'R$ '.number_format((float)$v['price'],2,',','.'):'preço não informado';$lines[]='- '.$v['name'].' | '.$price.' | duração: '.($v['duration']?:'não informada').' | '.trim(($v['description']??'').' '.($v['notes']??''));}return $lines?implode("\n",$lines):'Nenhum serviço cadastrado.';}
    public function tone(int $companyId):string{$s=Database::get()->prepare('SELECT communication_tone FROM companies WHERE id=?');$s->execute([$companyId]);return (string)($s->fetchColumn()?:'Profissional');}
    public function companyName(int $companyId):string{$s=Database::get()->prepare('SELECT name FROM companies WHERE id=?');$s->execute([$companyId]);return (string)$s->fetchColumn();}
}
