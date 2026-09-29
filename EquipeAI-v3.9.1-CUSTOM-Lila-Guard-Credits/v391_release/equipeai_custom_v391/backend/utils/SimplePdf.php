<?php
namespace App\utils;

final class SimplePdf {
    private float $width=595.28;
    private float $height=841.89;
    /** @var string[] */
    private array $pages=[];
    private int $pageIndex=-1;

    public function width():float{return $this->width;}
    public function height():float{return $this->height;}
    public function addPage():void{$this->pages[]='';$this->pageIndex=count($this->pages)-1;}
    public function pageCount():int{return count($this->pages);}

    private function out(string $cmd):void{if($this->pageIndex<0)$this->addPage();$this->pages[$this->pageIndex].=$cmd."\n";}
    private function topY(float $y):float{return $this->height-$y;}
    private function rgb(array $rgb):string{return implode(' ',array_map(fn($v)=>number_format(max(0,min(255,(int)$v))/255,3,'.',''),$rgb));}
    private function enc(string $text):string{
        $value=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text);
        if($value===false)$value=preg_replace('/[^\x20-\x7E]/','?', $text)??$text;
        return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$value);
    }

    public function fillRect(float $x,float $y,float $w,float $h,array $color):void{
        $pdfY=$this->height-$y-$h;$this->out($this->rgb($color).' rg');$this->out(sprintf('%.2f %.2f %.2f %.2f re f',$x,$pdfY,$w,$h));
    }
    public function strokeRect(float $x,float $y,float $w,float $h,array $color,float $line=1):void{
        $pdfY=$this->height-$y-$h;$this->out($this->rgb($color).' RG');$this->out(sprintf('%.2f w',$line));$this->out(sprintf('%.2f %.2f %.2f %.2f re S',$x,$pdfY,$w,$h));
    }
    public function line(float $x1,float $y1,float $x2,float $y2,array $color,float $line=1):void{
        $this->out($this->rgb($color).' RG');$this->out(sprintf('%.2f w',$line));$this->out(sprintf('%.2f %.2f m %.2f %.2f l S',$x1,$this->topY($y1),$x2,$this->topY($y2)));
    }
    public function text(float $x,float $y,string $text,float $size=11,bool $bold=false,array $color=[20,28,45]):void{
        $font=$bold?'F2':'F1';$this->out('BT');$this->out('/'.$font.' '.number_format($size,2,'.','').' Tf');$this->out($this->rgb($color).' rg');$this->out(sprintf('1 0 0 1 %.2f %.2f Tm',$x,$this->topY($y)));$this->out('('.$this->enc($text).') Tj');$this->out('ET');
    }
    public function wrap(string $text,float $maxWidth,float $fontSize=11):array{
        $text=preg_replace('/\s+/u',' ',trim($text))??trim($text);if($text==='')return [''];
        $avg=$fontSize*.50;$maxChars=max(8,(int)floor($maxWidth/$avg));$words=preg_split('/\s+/u',$text)?:[];$lines=[];$line='';
        foreach($words as $word){$candidate=$line===''?$word:$line.' '.$word;if((function_exists('mb_strlen')?mb_strlen($candidate,'UTF-8'):strlen($candidate))<=$maxChars){$line=$candidate;continue;}if($line!=='')$lines[]=$line;if((function_exists('mb_strlen')?mb_strlen($word,'UTF-8'):strlen($word))>$maxChars){while((function_exists('mb_strlen')?mb_strlen($word,'UTF-8'):strlen($word))>$maxChars){$lines[]=function_exists('mb_substr')?mb_substr($word,0,$maxChars,'UTF-8'):substr($word,0,$maxChars);$word=function_exists('mb_substr')?mb_substr($word,$maxChars,null,'UTF-8'):substr($word,$maxChars);}}$line=$word;}
        if($line!=='')$lines[]=$line;return $lines;
    }
    public function multiline(float $x,float $y,string $text,float $maxWidth,float $fontSize=11,float $lineHeight=16,bool $bold=false,array $color=[30,40,58]):float{
        $cursor=$y;foreach(preg_split('/\R/u',$text)?:[$text] as $paragraph){$paragraph=trim($paragraph);if($paragraph===''){$cursor+=$lineHeight*.6;continue;}foreach($this->wrap($paragraph,$maxWidth,$fontSize) as $line){$this->text($x,$cursor,$line,$fontSize,$bold,$color);$cursor+=$lineHeight;}$cursor+=$lineHeight*.2;}return $cursor;
    }

    public function output():string{
        if(!$this->pages)$this->addPage();
        $objects=[];
        $pageIds=[];
        for($i=0;$i<count($this->pages);$i++)$pageIds[]=6+($i*2);
        $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ',array_map(fn($id)=>$id.' 0 R',$pageIds)).'] /Count '.count($pageIds).' >>';
        $objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        foreach($this->pages as $i=>$stream){$contentId=5+($i*2);$pageId=6+($i*2);$objects[$contentId]='<< /Length '.strlen($stream).' >>' . "\nstream\n".$stream."endstream";$objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$this->width.' '.$this->height.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';}
        ksort($objects);$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0=>0];
        foreach($objects as $id=>$obj){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$obj."\nendobj\n";}
        $xref=strlen($pdf);$max=max(array_keys($objects));$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($i=1;$i<=$max;$i++)$pdf.=sprintf('%010d 00000 n ', $offsets[$i]??0)."\n";$pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";return $pdf;
    }
}
