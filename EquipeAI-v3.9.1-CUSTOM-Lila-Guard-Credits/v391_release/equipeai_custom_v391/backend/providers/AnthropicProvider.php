<?php
namespace App\providers;
final class AnthropicProvider implements AIProviderInterface {
    public function generate(string $model,array $messages,array $options=[]): array {
        $system='';$msgs=[];foreach($messages as $m){if($m['role']==='system')$system.=$m['content']."\n";else$msgs[]=['role'=>$m['role']==='assistant'?'assistant':'user','content'=>$m['content']];}
        $d=HttpClient::post('https://api.anthropic.com/v1/messages',['x-api-key: '.env('ANTHROPIC_API_KEY',''),'anthropic-version: 2023-06-01'],['model'=>$model,'max_tokens'=>$options['max_tokens']??1200,'temperature'=>$options['temperature']??0.4,'system'=>$system,'messages'=>$msgs]);
        return ['text'=>$d['content'][0]['text']??'','input_tokens'=>$d['usage']['input_tokens']??0,'output_tokens'=>$d['usage']['output_tokens']??0,'raw'=>$d];
    }
}
