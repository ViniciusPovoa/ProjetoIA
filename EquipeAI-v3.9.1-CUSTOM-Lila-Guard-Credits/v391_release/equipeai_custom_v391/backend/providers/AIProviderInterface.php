<?php
namespace App\providers;
interface AIProviderInterface { public function generate(string $model,array $messages,array $options=[]): array; }
