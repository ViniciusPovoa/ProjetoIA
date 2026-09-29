<?php
namespace App\services;

use App\utils\SimplePdf;

final class ProposalPdfService {
    public function templates():array{
        return [
            ['slug'=>'executive','name'=>'Executive Blue','description'=>'Executivo, direto e sofisticado.','accent'=>'#315CF5','background'=>'#0F172A'],
            ['slug'=>'minimal','name'=>'Minimal White','description'=>'Limpo, leve e editorial.','accent'=>'#111827','background'=>'#FFFFFF'],
            ['slug'=>'contrast','name'=>'Cobalt Contrast','description'=>'Mais comercial, forte e moderno.','accent'=>'#2457F5','background'=>'#EAF0FF'],
            ['slug'=>'warm','name'=>'Warm Business','description'=>'Elegante, acolhedor e consultivo.','accent'=>'#B66A3C','background'=>'#F7F1EA'],
        ];
    }

    public function render(array $company,array $data,string $template='executive'):string{
        if(!in_array($template,array_column($this->templates(),'slug'),true))$template='executive';
        $pdf=new SimplePdf();$pdf->addPage();
        $palette=$this->palette($template);$margin=48.0;$contentWidth=$pdf->width()-($margin*2);$y=0.0;

        $companyName=trim((string)($company['name']??'Empresa')) ?: 'Empresa';
        $client=trim((string)($data['client_name']??''));
        $service=trim((string)($data['service']??''));
        $value=trim((string)($data['value']??''));
        $deadline=trim((string)($data['deadline']??''));
        $title=trim((string)($data['title']??'')) ?: 'Proposta Comercial';
        $body=trim((string)($data['content']??''));

        $this->drawCover($pdf,$template,$palette,$companyName,$client,$title,$service,$value,$deadline);
        $y=$template==='minimal'?292:312;

        $sections=$this->parseSections($body);
        if(!$sections)$sections=[['title'=>'Proposta','body'=>$body]];
        foreach($sections as $section){
            $needed=52+min(160,count($pdf->wrap($section['body'],$contentWidth,10.8))*15);
            if($y+$needed>770){$pdf->addPage();$this->drawContinuationHeader($pdf,$template,$palette,$companyName,$title);$y=92;}
            $pdf->text($margin,$y,$section['title'],12.5,true,$palette['heading']);$y+=20;
            foreach(preg_split('/\R/u',$section['body'])?:[] as $paragraph){
                $paragraph=trim($paragraph);if($paragraph===''){$y+=7;continue;}
                $bullet=preg_match('/^[-*•]\s+/u',$paragraph)===1;
                $clean=preg_replace('/^[-*•]\s+/u','',$paragraph)??$paragraph;
                $lines=$pdf->wrap($clean,$contentWidth-($bullet?16:0),10.6);
                foreach($lines as $line){
                    if($y>785){$pdf->addPage();$this->drawContinuationHeader($pdf,$template,$palette,$companyName,$title);$y=92;}
                    if($bullet){$pdf->fillRect($margin+2,$y-7,4,4,$palette['accent']);$pdf->text($margin+16,$y,$line,10.6,false,$palette['body']);}
                    else{$pdf->text($margin,$y,$line,10.6,false,$palette['body']);}
                    $y+=15;
                }
                $y+=5;
            }
            $y+=10;
        }

        if($y>720){$pdf->addPage();$this->drawContinuationHeader($pdf,$template,$palette,$companyName,$title);$y=108;}
        $pdf->line($margin,$y,$margin+$contentWidth,$y,$palette['line'],0.8);$y+=22;
        $pdf->text($margin,$y,'Próximo passo',11.5,true,$palette['heading']);$y+=19;
        $next='Se esta proposta estiver de acordo, responda a esta mensagem para alinharmos os próximos passos.';
        $pdf->multiline($margin,$y,$next,$contentWidth,10.5,15,false,$palette['body']);

        $this->drawFooter($pdf,$palette,$company,$companyName);
        return $pdf->output();
    }

    private function palette(string $template):array{
        return match($template){
            'minimal'=>['accent'=>[17,24,39],'dark'=>[17,24,39],'soft'=>[247,248,250],'heading'=>[17,24,39],'body'=>[73,82,98],'line'=>[220,225,232],'muted'=>[130,140,154]],
            'contrast'=>['accent'=>[36,87,245],'dark'=>[19,37,82],'soft'=>[234,240,255],'heading'=>[21,37,78],'body'=>[62,76,107],'line'=>[203,215,244],'muted'=>[111,127,165]],
            'warm'=>['accent'=>[182,106,60],'dark'=>[52,42,36],'soft'=>[247,241,234],'heading'=>[52,42,36],'body'=>[91,78,68],'line'=>[222,210,198],'muted'=>[137,121,108]],
            default=>['accent'=>[49,92,245],'dark'=>[15,23,42],'soft'=>[243,246,252],'heading'=>[17,26,43],'body'=>[73,85,105],'line'=>[218,225,235],'muted'=>[128,142,163]],
        };
    }

    private function drawCover(SimplePdf $pdf,string $template,array $p,string $company,string $client,string $title,string $service,string $value,string $deadline):void{
        $w=$pdf->width();$margin=48.0;
        if($template==='minimal'){
            $pdf->text($margin,54,$company,12,true,$p['heading']);$pdf->line($margin,72,$w-$margin,72,$p['line'],1);
            $pdf->text($margin,118,'PROPOSTA COMERCIAL',8.5,true,$p['muted']);$pdf->text($margin,151,$title,28,true,$p['heading']);
            if($client!=='')$pdf->text($margin,185,'Preparada para '.$client,11,false,$p['body']);
        }elseif($template==='contrast'){
            $pdf->fillRect(0,0,18,842,$p['accent']);$pdf->fillRect(18,0,$w-18,210,$p['soft']);
            $pdf->text($margin,52,$company,11,true,$p['heading']);$pdf->text($margin,104,'PROPOSTA',8.5,true,$p['accent']);$pdf->multiline($margin,136,$title,460,26,31,true,$p['heading']);
            if($client!=='')$pdf->text($margin,194,'Para '.$client,11,false,$p['body']);
        }else{
            $pdf->fillRect(0,0,$w,210,$template==='warm'?$p['soft']:$p['dark']);
            $titleColor=$template==='warm'?$p['heading']:[255,255,255];$muted=$template==='warm'?$p['muted']:[174,187,210];
            $pdf->text($margin,48,$company,11,true,$titleColor);$pdf->text($margin,94,'PROPOSTA COMERCIAL',8.5,true,$template==='warm'?$p['accent']:[124,151,255]);$pdf->multiline($margin,126,$title,480,27,31,true,$titleColor);
            if($client!=='')$pdf->text($margin,191,'Preparada para '.$client,10.5,false,$muted);
        }
        $top=$template==='minimal'?205:224;$items=[];
        if($service!=='')$items[]=['Serviço',$service];if($value!=='')$items[]=['Investimento',$value];if($deadline!=='')$items[]=['Prazo',$deadline];
        if($items){$col=($w-($margin*2)-20*(count($items)-1))/count($items);foreach($items as $i=>$item){$x=$margin+$i*($col+20);$pdf->fillRect($x,$top,$col,58,$p['soft']);$pdf->text($x+13,$top+20,strtoupper($item[0]),7.5,true,$p['muted']);$pdf->multiline($x+13,$top+39,$item[1],$col-26,10.5,13,true,$p['heading']);}}
    }

    private function drawContinuationHeader(SimplePdf $pdf,string $template,array $p,string $company,string $title):void{
        $margin=48.0;$pdf->text($margin,48,$company,10.5,true,$p['heading']);$pdf->text($pdf->width()-200,48,$title,8.5,false,$p['muted']);$pdf->line($margin,65,$pdf->width()-$margin,65,$p['line'],0.7);
        if($template==='contrast')$pdf->fillRect(0,0,8,842,$p['accent']);
    }

    private function drawFooter(SimplePdf $pdf,array $p,array $company,string $companyName):void{
        $margin=48.0;$y=814;$pdf->line($margin,$y-17,$pdf->width()-$margin,$y-17,$p['line'],0.6);
        $left=$companyName;$contact=[];foreach(['phone','whatsapp','email','website'] as $key){$v=trim((string)($company[$key]??''));if($v!=='')$contact[]=$v;}
        $pdf->text($margin,$y,$left,8.2,true,$p['muted']);if($contact)$pdf->text($margin+145,$y,implode('  |  ',array_slice($contact,0,3)),7.8,false,$p['muted']);
    }

    private function parseSections(string $text):array{
        $text=str_replace("\r",'',trim($text));if($text==='')return [];$sections=[];$currentTitle='Proposta';$body=[];
        foreach(explode("\n",$text) as $raw){$line=trim($raw);$heading='';
            if(preg_match('/^#{1,4}\s+(.+)$/u',$line,$m))$heading=trim($m[1]);
            elseif(preg_match('/^(resumo executivo|objetivo|escopo|solução proposta|proposta|investimento|valor|prazo|cronograma|entregáveis|inclui|condições|próximos passos|observações)\s*:?$/iu',$line,$m))$heading=ucfirst(function_exists('mb_strtolower')?mb_strtolower(trim($m[1]),'UTF-8'):strtolower(trim($m[1])));
            if($heading!==''){if(trim(implode("\n",$body))!=='')$sections[]=['title'=>$currentTitle,'body'=>trim(implode("\n",$body))];$currentTitle=$heading;$body=[];continue;}
            $body[]=$raw;
        }
        if(trim(implode("\n",$body))!=='')$sections[]=['title'=>$currentTitle,'body'=>trim(implode("\n",$body))];
        return $sections;
    }
}
