<?php
namespace App\services;

use App\providers\CloudflareImageProvider;
use App\providers\ImageProviderInterface;
use App\repositories\AgentRepository;
use App\utils\Logger;
use App\utils\Validator;
use RuntimeException;

final class ImageGenerationService {
    private ImageProviderInterface $provider;

    public function __construct(?ImageProviderInterface $provider = null) {
        $this->provider = $provider ?? new CloudflareImageProvider();
    }

    public function generateMarketingImage(array $user, int $agentId, array $input): array {
        $agent = (new AgentRepository())->findActive($agentId);
        if (!$agent) throw new RuntimeException('Agente não encontrado.');
        $slug = (string)($agent['slug'] ?? '');
        if ($slug !== 'marketing') throw new RuntimeException('Geração de imagem disponível apenas no agente de Marketing.');

        $format = strtolower(Validator::clean((string)($input['format'] ?? 'publication'), 40));
        if (!in_array($format, ['story', 'publication'], true)) {
            throw new RuntimeException('Formato de imagem inválido.');
        }

        $brief = [
            'title' => Validator::clean((string)($input['title'] ?? ''), 160),
            'summary' => Validator::clean((string)($input['summary'] ?? ''), 900),
            'body' => Validator::clean((string)($input['body'] ?? ''), 2400),
            'platform' => Validator::clean((string)($input['platform'] ?? ''), 80),
            'objective' => Validator::clean((string)($input['objective'] ?? ''), 120),
            'audience' => Validator::clean((string)($input['audience'] ?? ''), 220),
            'tone' => Validator::clean((string)($input['tone'] ?? ''), 80),
            'additional_info' => Validator::clean((string)($input['additional_info'] ?? ''), 800),
            'content_type' => Validator::clean((string)($input['content_type'] ?? ''), 40),
            'style' => Validator::clean((string)($input['style'] ?? 'Arte publicitária editorial'), 80),
            'visual_direction' => Validator::clean((string)($input['visual_direction'] ?? ''), 900),
            'block_label' => Validator::clean((string)($input['block_label'] ?? 'Conteúdo'), 100),
            'block_index' => max(1, (int)($input['block_index'] ?? 1)),
        ];
        if ($brief['title'] === '' && $brief['summary'] === '' && $brief['body'] === '') {
            throw new RuntimeException('Envie o conteúdo do post antes de gerar a imagem.');
        }

        $creditsCost = max(1, (int)env('MARKETING_IMAGE_CREDIT_COST', '4'));
        $txId = (new CreditService())->reserve((int)$user['id'], $creditsCost, 'Geração de imagem de marketing', 'image_generation');
        $started = microtime(true);
        try {
            $isEditorial = str_contains(mb_strtolower($brief['style'], 'UTF-8'), 'publicitária editorial');
            if ($isEditorial) {
                $illustrationPrompt = $this->editorialIllustrationPrompt($brief);
                $visualPlan = [
                    'visual_brief' => 'Arte publicitária híbrida: foto ilustrativa gerada por IA, integrada a um layout vetorial com tipografia exata, hierarquia editorial, espaço em branco e cor de destaque.',
                    'prompt' => $illustrationPrompt,
                    'negative_prompt' => 'text, letters, words, typography, caption, logo, watermark, poster, mockup, 3d render, cgi, cartoon, illustration',
                    'must_include' => [$brief['body']],
                    'source' => 'hybrid-editorial',
                ];
                $illustration = $this->provider->generate($illustrationPrompt, [
                    'width' => 1024,
                    'height' => 768,
                    'steps' => 24,
                    'guidance' => 4.5,
                    'negative_prompt' => $visualPlan['negative_prompt'],
                ]);
                $result = (new EditorialAdRenderer())->render($brief, (int)$user['company_id'], $format, $illustration);
            } else {
                $visualPlan = (new VisualPromptService())->create($user, $agent, $brief, $format);
                $imageOptions = $this->formatOptions($format);
                if (!empty($visualPlan['negative_prompt'])) {
                    $imageOptions['negative_prompt'] = trim((string)$imageOptions['negative_prompt'] . ', ' . (string)$visualPlan['negative_prompt']);
                }
                $result = $this->provider->generate((string)$visualPlan['prompt'], $imageOptions);
            }
            $asset = (new GeneratedMediaService())->persist((int)$user['company_id'], (string)$result['image_data_uri'], (string)$result['mime']);
            (new CreditService())->confirm($txId);
            $durationMs = (int)((microtime(true) - $started) * 1000);
            Logger::write('ai_requests', 'marketing_image_success', [
                'user_id' => (int)$user['id'],
                'company_id' => (int)$user['company_id'],
                'agent_id' => $agentId,
                'provider' => $result['provider'],
                'model' => $result['model'],
                'duration_ms' => $durationMs,
                'format' => $format,
            ]);
            return [
                'image' => $result['image_data_uri'],
                'mime' => $result['mime'],
                'media_url' => $asset['public_url'],
                'media_asset_id' => $asset['id'],
                'media_expires_in_hours' => $asset['expires_in_hours'],
                'media_publishable' => (bool)($asset['publishable'] ?? false),
                'provider' => $result['provider'],
                'model' => $result['model'],
                'credits_used' => $creditsCost,
                'format' => $format,
                'visual_brief' => (string)($visualPlan['visual_brief'] ?? ''),
                'prompt_source' => (string)($visualPlan['source'] ?? ''),
                'must_include' => $visualPlan['must_include'] ?? [],
                'meta' => $result['meta'] ?? [],
            ];
        } catch (\Throwable $e) {
            (new CreditService())->refund($txId);
            Logger::write('ai_requests', 'marketing_image_error', [
                'user_id' => (int)$user['id'],
                'company_id' => (int)$user['company_id'],
                'agent_id' => $agentId,
                'format' => $format,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function formatOptions(string $format): array {
        if ($format === 'story') {
            return ['width' => 1024, 'height' => 1792, 'steps' => 28, 'guidance' => 4.5, 'prefer_exact_aspect' => true, 'negative_prompt' => '3d render, cgi, illustration, cartoon, isometric scene, mockup, plastic objects, watermark, logo artifacts, unreadable text, blurry, low resolution, cluttered composition'];
        }
        return ['width' => 1024, 'height' => 1280, 'steps' => 28, 'guidance' => 4.5, 'prefer_exact_aspect' => true, 'negative_prompt' => '3d render, cgi, illustration, cartoon, isometric scene, mockup, plastic objects, watermark, logo artifacts, unreadable text, blurry, low resolution, cluttered composition'];
    }

    private function editorialIllustrationPrompt(array $brief): string {
        $content = trim((string)($brief['body'] ?? $brief['summary'] ?? $brief['title'] ?? ''));
        $content = preg_replace('/\s+/u', ' ', $content) ?? $content;
        return implode("\n", [
            'Create one premium realistic commercial photograph to support a clean editorial social-media advertisement.',
            'TOPIC TO ILLUSTRATE: "' . mb_substr($content, 0, 700) . '"',
            'The photograph must visually communicate this topic immediately and accurately.',
            'When the topic names a service, show that service being performed in a tasteful, safe, non-graphic and professional way.',
            'Use one clear scene, natural human proportions, authentic materials, soft professional lighting and a restrained neutral palette.',
            'Compose for a wide 4:3 image panel with the subject centered and enough breathing room around it.',
            'IMAGE ONLY. Do not render any text, letters, numbers, captions, labels, logos, signs, watermarks, interface elements, frames or graphic-design layout.',
            'No 3D render, CGI, cartoon, vector illustration, isometric scene, plastic objects or glossy mockup.',
        ]);
    }

}
