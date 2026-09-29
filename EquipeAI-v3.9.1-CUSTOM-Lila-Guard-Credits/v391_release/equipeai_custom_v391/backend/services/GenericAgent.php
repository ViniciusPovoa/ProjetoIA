<?php
namespace App\services;
use App\contracts\AgentInterface;
use RuntimeException;
final class GenericAgent implements AgentInterface {
    public function validateInput(array $input,array $schema): array {
        $clean=[];
        foreach($schema as $field){$name=(string)($field['name']??'');if($name==='')continue;$type=$field['type']??'text';$required=!empty($field['required']);$value=$input[$name]??null;
            if($required && ($value===null || trim((string)$value)===''))throw new RuntimeException('Preencha o campo: '.($field['label']??$name).'.');
            if($type==='file'){$clean[$name]=$value===null?null:(int)$value;continue;}
            $v=mb_substr(trim((string)($value??'')),0,$type==='textarea'?20000:2000);
            if($type==='select' && $v!=='' && isset($field['options']) && !in_array($v,$field['options'],true))throw new RuntimeException('Valor inválido para '.($field['label']??$name).'.');
            $clean[$name]=$v;
        }
        return $clean;
    }
    public function buildUserContent(array $input): string {return json_encode($input,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)?:'{}';}
}
