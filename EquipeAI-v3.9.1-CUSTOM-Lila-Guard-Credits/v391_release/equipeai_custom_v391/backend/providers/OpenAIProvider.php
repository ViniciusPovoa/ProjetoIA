<?php
namespace App\providers;
final class OpenAIProvider implements AIProviderInterface {
    public function generate(string $model,array $messages,array $options=[]):array{
        $requestedTokens=(int)($options['max_tokens']??1200);
        $isGpt5=str_starts_with($model,'gpt-5');
        $body=['model'=>$model,'messages'=>$messages,'max_completion_tokens'=>$isGpt5?max(4096,$requestedTokens):$requestedTokens];
        if(!empty($options['json_mode']))$body['response_format']=['type'=>'json_object'];
        if($isGpt5){$body['reasoning_effort']='minimal';}else{$body['temperature']=$options['temperature']??.4;}
        $d=HttpClient::post('https://api.openai.com/v1/chat/completions',['Authorization: Bearer '.env('OPENAI_API_KEY','')],$body);
        return ['text'=>$d['choices'][0]['message']['content']??'','input_tokens'=>$d['usage']['prompt_tokens']??0,'output_tokens'=>$d['usage']['completion_tokens']??0,'raw'=>$d];
    }
}
