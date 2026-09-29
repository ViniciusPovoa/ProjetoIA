<?php
namespace App\providers;

use RuntimeException;

final class CloudflareImageProvider implements ImageProviderInterface {
    public function generate(string $prompt, array $options = []): array {
        $accountId = trim((string)env('CLOUDFLARE_ACCOUNT_ID', ''));
        $apiToken = trim((string)env('CLOUDFLARE_API_TOKEN', ''));
        $primary = trim((string)env('CLOUDFLARE_AI_MODEL', '@cf/black-forest-labs/flux-1-schnell'));
        $fallback = trim((string)env('CLOUDFLARE_AI_FALLBACK_MODEL', '@cf/bytedance/stable-diffusion-xl-lightning'));
        if ($accountId === '' || $apiToken === '') {
            throw new RuntimeException('Geração de imagem indisponível. Configure CLOUDFLARE_ACCOUNT_ID e CLOUDFLARE_API_TOKEN no .env.');
        }

        $attempts = array_values(array_unique(array_filter([$primary, $fallback])));
        if (!empty($options['prefer_exact_aspect']) && str_contains($primary, 'flux-1-schnell') && $fallback !== '' && str_contains($fallback, 'stable-diffusion-xl')) {
            $attempts = array_values(array_unique(array_filter([$fallback, $primary])));
        }
        $errors = [];
        foreach ($attempts as $index => $model) {
            try {
                return $this->requestModel($accountId, $apiToken, $model, $prompt, $options, $index > 0);
            } catch (CloudflareImageException $e) {
                $errors[] = $e;
                // Invalid prompt/auth/private-model errors should not be retried with another model.
                if (!in_array($e->cloudflareCode, [3036, 3040, 5007, 5035], true) && $e->httpStatus < 500) {
                    throw new RuntimeException($this->friendlyMessage($e));
                }
            }
        }

        $last = end($errors);
        if ($last instanceof CloudflareImageException) {
            throw new RuntimeException($this->friendlyMessage($last));
        }
        throw new RuntimeException('Não foi possível gerar a imagem agora.');
    }

    private function requestModel(string $accountId, string $apiToken, string $model, string $prompt, array $options, bool $fallback): array {
        $payload = $this->payloadForModel($model, $prompt, $options);
        $url = 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($accountId) . '/ai/run/' . $model;

        $ch = curl_init($url);
        $curlOptions = [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiToken,
                'Content-Type: application/json',
                'Accept: application/json, image/*',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HEADER => true,
        ];
        $caFile = trim((string)env('CURL_CA_BUNDLE', ''));
        if ($caFile !== '' && is_file($caFile)) {
            $curlOptions[CURLOPT_CAINFO] = $caFile;
        }
        curl_setopt_array($ch, $curlOptions);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($raw === false || $curlError !== '') {
            throw new CloudflareImageException(0, 0, 'Falha de conexão com a Cloudflare.');
        }

        $body = substr((string)$raw, $headerSize);
        if ($status < 200 || $status >= 300) {
            $decoded = json_decode($body, true);
            $error = is_array($decoded) ? ($decoded['errors'][0] ?? $decoded['error'] ?? null) : null;
            $code = is_array($error) ? (int)($error['code'] ?? 0) : 0;
            $message = is_array($error) ? (string)($error['message'] ?? 'Erro externo') : (is_string($error) ? $error : 'Erro externo');
            if ($message === 'Erro externo' && is_array($decoded)) $message = (string)($decoded['message'] ?? $message);
            throw new CloudflareImageException($status, $code, $message);
        }

        $mime = str_contains(strtolower($contentType), 'png') ? 'image/png' : 'image/jpeg';
        $imageBase64 = '';
        if (str_contains(strtolower($contentType), 'application/json') || str_starts_with(ltrim($body), '{')) {
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) throw new CloudflareImageException($status, 0, 'Resposta de imagem inválida.');
            $imageBase64 = (string)($decoded['result']['image'] ?? $decoded['image'] ?? '');
            $mime = (string)($decoded['result']['mime_type'] ?? $decoded['mime_type'] ?? $mime);
        } else {
            $imageBase64 = base64_encode($body);
        }
        if ($imageBase64 === '') throw new CloudflareImageException($status, 0, 'O modelo não retornou uma imagem.');

        return [
            'image_data_uri' => 'data:' . $mime . ';base64,' . $imageBase64,
            'mime' => $mime,
            'provider' => 'cloudflare-workers-ai',
            'model' => $model,
            'meta' => [
                'seed' => $payload['seed'] ?? null,
                'steps' => $payload['steps'] ?? $payload['num_steps'] ?? null,
                'width' => $payload['width'] ?? null,
                'height' => $payload['height'] ?? null,
                'fallback_used' => $fallback,
            ],
        ];
    }

    private function payloadForModel(string $model, string $prompt, array $options): array {
        $payload = ['prompt' => $prompt, 'seed' => random_int(1, 2147483647)];
        $isFluxSchnell = str_contains($model, 'flux-1-schnell');
        if ($isFluxSchnell) {
            $payload = ['prompt' => $prompt, 'steps' => max(1, min(8, (int)($options['steps'] ?? 4)))];
            // FLUX.1 Schnell currently does not expose width/height in the Cloudflare schema.
            return $payload;
        }

        $isLucidOrigin = str_contains($model, 'lucid-origin');
        $maxDimension = $isLucidOrigin ? 2500 : 2048;
        $payload['width'] = max(256, min($maxDimension, (int)($options['width'] ?? 1024)));
        $payload['height'] = max(256, min($maxDimension, (int)($options['height'] ?? 1024)));
        $payload['num_steps'] = max(1, min($isLucidOrigin ? 40 : 20, (int)($options['steps'] ?? ($isLucidOrigin ? 28 : 6))));
        $payload['guidance'] = (float)($options['guidance'] ?? ($isLucidOrigin ? 4.5 : 7.0));
        if ($isLucidOrigin) return $payload;

        // SDXL-style models also support negative prompts.
        if (!empty($options['negative_prompt'])) $payload['negative_prompt'] = (string)$options['negative_prompt'];
        return $payload;
    }

    private function friendlyMessage(CloudflareImageException $e): string {
        return match ($e->cloudflareCode) {
            3036 => 'A cota gratuita diária do Workers AI desta conta foi esgotada. Ela é compartilhada por toda a conta e reinicia às 00:00 UTC. Tente novamente após a renovação da cota ou use um plano pago.',
            3040 => 'A Cloudflare está sem capacidade para esse modelo neste momento. Tente novamente em alguns minutos.',
            5035 => 'O modelo selecionado exige o plano Workers Paid. Troque o modelo no .env ou habilite o plano pago.',
            5007 => 'O modelo de imagem configurado não existe ou não está disponível nesta conta.',
            default => 'Cloudflare Workers AI: ' . mb_substr($e->getMessage(), 0, 260),
        };
    }
}

final class CloudflareImageException extends RuntimeException {
    public function __construct(public int $httpStatus, public int $cloudflareCode, string $message) {
        parent::__construct($message);
    }
}
