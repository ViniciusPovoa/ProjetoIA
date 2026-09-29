<?php
namespace App\providers;
final class DeepSeekProvider implements AIProviderInterface {
    public function generate(string $model,array $messages,array $options=[]): array {
        $d=HttpClient::post('https://api.deepseek.com/chat/completions',['Authorization: Bearer '.env('DEEPSEEK_API_KEY','')],['model'=>$model,'messages'=>$messages,'temperature'=>$options['temperature']??0.4,'max_tokens'=>$options['max_tokens']??1200]);
        return ['text'=>$d['choices'][0]['message']['content']??'','input_tokens'=>$d['usage']['prompt_tokens']??0,'output_tokens'=>$d['usage']['completion_tokens']??0,'raw'=>$d];
    }
}
