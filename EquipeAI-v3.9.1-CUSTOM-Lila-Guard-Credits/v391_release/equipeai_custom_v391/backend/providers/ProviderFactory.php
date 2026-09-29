<?php
namespace App\providers;
use RuntimeException;
final class ProviderFactory {public static function make(string $slug):AIProviderInterface{return match($slug){'openai'=>new OpenAIProvider(),'anthropic'=>new AnthropicProvider(),'gemini'=>new GeminiProvider(),'deepseek'=>new DeepSeekProvider(),default=>throw new RuntimeException('Provedor de IA não suportado.')};}}
