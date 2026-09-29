<?php
namespace App\providers;

interface ImageProviderInterface {
    /** @return array{image_data_uri:string,mime:string,provider:string,model:string,meta?:array} */
    public function generate(string $prompt, array $options = []): array;
}
