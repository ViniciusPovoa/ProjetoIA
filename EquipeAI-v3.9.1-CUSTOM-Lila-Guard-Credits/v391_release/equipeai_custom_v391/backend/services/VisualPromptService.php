<?php
namespace App\services;

use App\providers\ProviderFactory;
use App\repositories\CompanyRepository;
use App\utils\Logger;

final class VisualPromptService {
    public function create(array $user, array $agent, array $brief, string $format): array {
        $company = (new CompanyRepository())->find((int)$user['company_id']) ?: [];
        $customDirection = trim((string)($brief['visual_direction'] ?? ''));

        if (!env_bool('IMAGE_PROMPT_ENHANCER_ENABLED', true)) {
            return $this->fallback($company, $brief, $format, $customDirection);
        }

        try {
            $provider = ProviderFactory::make((string)$agent['provider_slug']);
            $model = (string)$agent['model_name'];
            $messages = [
                [
                    'role' => 'system',
                    'content' => implode("\n", [
                        'You are a senior advertising art director and image-prompt engineer.',
                        'Your job is to convert a marketing message into ONE concrete visual scene for a text-to-image model.',
                        'The image must clearly and immediately communicate the exact topic of the selected post/story.',
                        'Treat SELECTED CONTENT as the only source of truth for visible details.',
                        'If SELECTED CONTENT contains an explicit image, photo, scene or visual suggestion, follow it with absolute priority.',
                        'If it offers alternatives using words such as "or", choose the first safe option; never merge alternatives into a new scene.',
                        'Preserve concrete facts from the source, but do not turn campaign metadata into visible facts.',
                        'Audience age is targeting context only. Never create a person of an exact age unless SELECTED CONTENT explicitly requires that person to appear.',
                        'Do NOT invent medical procedures, instruments, techniques, liquids, foam, uniforms, labels, logos, packaging, locations, staff appearance, ethnicity, brand colors or claims.',
                        'Do not place the company name on objects, bottles, uniforms, walls or signs unless SELECTED CONTENT explicitly requests visible branding.',
                        'When SELECTED CONTENT is only a title or benefit, prefer a simple non-procedural outcome instead of fabricating a detailed procedure.',
                        'A service name alone does not authorize showing how the service is performed or adding professional equipment.',
                        'For a headline about a service and its benefit, show only the benefit or outcome explicitly named in the headline.',
                        'Example: for "dental cleaning: the first step to a healthy smile", a clean healthy smile is supported; dental staff, gloves, hands, mirrors, scalers, sprays and clinical procedures are not.',
                        'Before answering, remove every visual element whose noun or action is not supported by SELECTED CONTENT.',
                        'Every person, object and action in the final prompt must be directly supported by SELECTED CONTENT.',
                        'When REQUESTED VISUAL STYLE is advertising/editorial, create a finished flat 2D advertising layout, not a standalone photograph.',
                        'Use clean typography, strong hierarchy, generous whitespace, a restrained color palette, and one accent color.',
                        'For advertising/editorial style, typography must be the dominant focal point and occupy at least half of the canvas.',
                        'Default to a typography-first poster with a clean background and subtle flat geometric accents.',
                        'Do not add a person, face, product photograph or decorative scene unless SELECTED CONTENT explicitly requests an image, photo, person or product.',
                        'Include a short dominant headline inside the artwork using only words found in SELECTED CONTENT. Put the exact rendered headline in quotation marks in the image prompt.',
                        'Split the headline across two or three intentional lines and highlight its most important words with the single accent color.',
                        'A short supporting line or CTA is allowed only when its exact wording exists in SELECTED CONTENT.',
                        'Never render long paragraphs. Prefer one headline of 2 to 8 words and, when supported, one short secondary line.',
                        'Do NOT create 3D renders, CGI, cartoons, isometric scenes, floating objects, glossy mockups, plastic typography or decorative illustrations.',
                        'The intended result is a normal professional social-media advertisement: flat layout, crisp sans-serif typography, editorial spacing and brand-like color blocking.',
                        'Write visual_brief as a short explanation in Brazilian Portuguese. Write prompt in English for the image model.',
                        'The final prompt must be concise and specific without adding unsupported detail.',
                        'Output VALID JSON ONLY with keys: visual_brief, prompt, negative_prompt, must_include.',
                        'must_include must be an array of 2 to 6 short concrete visual elements.',
                    ]),
                ],
                [
                    'role' => 'user',
                    'content' => implode("\n", [
                        'SELECTED CONTENT:',
                        (string)($brief['body'] ?? ''),
                        '',
                        'TITLE / CONTEXT:',
                        (string)($brief['title'] ?? ''),
                        '',
                        'CAMPAIGN OBJECTIVE: ' . ((string)($brief['objective'] ?? '') ?: 'not provided'),
                        'TARGET AUDIENCE (campaign targeting only; do not depict literally unless SELECTED CONTENT asks for it): ' . ((string)($brief['audience'] ?? '') ?: (string)($company['target_audience'] ?? 'not provided')),
                        'PLATFORM: ' . ((string)($brief['platform'] ?? '') ?: 'Instagram'),
                        'FORMAT: ' . ($format === 'story' ? 'vertical 9:16 story' : 'vertical 4:5 feed post'),
                        'REQUESTED VISUAL STYLE: ' . ((string)($brief['style'] ?? '') ?: 'Arte publicitária editorial'),
                        'USER VISUAL DIRECTION: ' . ($customDirection !== '' ? $customDirection : 'none'),
                        '',
                        'BUSINESS CONTEXT (use only when visually relevant):',
                        'Company: ' . (string)($company['name'] ?? ''),
                        'Segment: ' . (string)($company['segment'] ?? ''),
                        'Description: ' . $this->clip((string)($company['description'] ?? ''), 420),
                        'Products/services: ' . $this->clip((string)($company['products'] ?? ''), 360),
                        'Audience: ' . $this->clip((string)($company['target_audience'] ?? ''), 300),
                        'Differentials: ' . $this->clip((string)($company['differentials'] ?? ''), 320),
                        'Tone: ' . (string)($company['communication_tone'] ?? ''),
                        '',
                        'CRITICAL RELEVANCE RULES:',
                        '1. The visual concept must come from SELECTED CONTENT, not from company context or campaign metadata.',
                        '2. Explicit visual suggestions in SELECTED CONTENT override every other instruction.',
                        '3. Do not infer how a service is performed. Show a procedure only when its action and visible elements are explicitly stated.',
                        '4. Use a physical environment only when SELECTED CONTENT states or clearly requires it.',
                        '5. Keep the composition simple: one primary scene, one clear focal point, no unrelated props.',
                        '6. Use negative space as part of the graphic layout. Render only a short supported headline, never paragraphs or invented branding.',
                    ]),
                ],
            ];
            $result = $provider->generate($model, $messages, [
                'temperature' => 0.1,
                'max_tokens' => max(180, min(420, (int)env('IMAGE_PROMPT_MAX_TOKENS', '320'))),
            ]);
            $text = trim((string)($result['text'] ?? ''));
            $parsed = $this->parseJson($text);
            if (!$parsed || trim((string)($parsed['prompt'] ?? '')) === '') {
                throw new \RuntimeException('Visual planner returned invalid JSON.');
            }

            $visualBrief = trim((string)($parsed['visual_brief'] ?? ''));
            $prompt = trim((string)$parsed['prompt']);
            $negative = trim((string)($parsed['negative_prompt'] ?? ''));
            $mustInclude = array_values(array_filter(array_map('strval', is_array($parsed['must_include'] ?? null) ? $parsed['must_include'] : [])));

            // Lock the final image prompt to the exact selected content so the planner cannot drift.
            $relevanceLock = $this->relevanceLock($brief, $company);
            $prompt = $this->clip($relevanceLock . "\n" . $prompt, 1950);
            if ($customDirection !== '') {
                $prompt = $this->clip('USER VISUAL DIRECTION — FOLLOW THIS CLOSELY: ' . $customDirection . "\n" . $prompt, 1950);
            }

            Logger::write('ai_requests', 'visual_prompt_success', [
                'user_id' => (int)$user['id'],
                'agent_id' => (int)$agent['id'],
                'provider' => (string)$agent['provider_slug'],
                'model' => $model,
                'input_tokens' => (int)($result['input_tokens'] ?? 0),
                'output_tokens' => (int)($result['output_tokens'] ?? 0),
            ]);

            return [
                'visual_brief' => $visualBrief !== '' ? $visualBrief : $this->humanBrief($brief, $company),
                'prompt' => $prompt,
                'negative_prompt' => $negative,
                'must_include' => $mustInclude,
                'source' => 'ai-planner',
            ];
        } catch (\Throwable $e) {
            Logger::write('ai_requests', 'visual_prompt_fallback', [
                'user_id' => (int)$user['id'],
                'agent_id' => (int)$agent['id'],
                'error' => $e->getMessage(),
            ]);
            return $this->fallback($company, $brief, $format, $customDirection);
        }
    }

    private function fallback(array $company, array $brief, string $format, string $customDirection): array {
        $body = trim((string)($brief['body'] ?? ''));
        $title = trim((string)($brief['title'] ?? ''));
        $audience = trim((string)($brief['audience'] ?? '')) ?: trim((string)($company['target_audience'] ?? ''));
        $segment = trim((string)($company['segment'] ?? ''));
        $style = trim((string)($brief['style'] ?? 'Arte publicitária editorial'));
        $orientation = $format === 'story' ? 'vertical 9:16 social media story' : 'vertical 4:5 social media feed post';
        $source = trim($title . '. ' . $body);
        $visualBrief = $customDirection !== '' ? $customDirection : $this->humanBrief($brief, $company);
        $prompt = implode("\n", array_filter([
            'Create ONE finished flat 2D advertising artwork in ' . $orientation . ' format for this exact marketing message: "' . $this->clip($source, 650) . '".',
            $customDirection !== '' ? 'USER VISUAL DIRECTION — FOLLOW THIS CLOSELY: ' . $customDirection : '',
            'Visual style: ' . $style . '.',
            'Use crisp sans-serif typography, strong hierarchy, generous whitespace, a restrained palette and one accent color.',
            'Render one short headline using only exact words from the selected message. Do not render paragraphs.',
            'No 3D, CGI, cartoons, isometric scenes, floating objects, glossy mockups, plastic typography, unrelated stock imagery or invented branding.',
        ]));
        return [
            'visual_brief' => $visualBrief,
            'prompt' => $this->clip($prompt, 1950),
            'negative_prompt' => 'unrelated subject, generic stock photo, random objects, wrong age group, wrong profession, wrong service, watermark, logos, unreadable text, paragraphs, malformed anatomy, blurry, low resolution, cluttered composition',
            'must_include' => [],
            'source' => 'deterministic-fallback',
        ];
    }

    private function relevanceLock(array $brief, array $company): string {
        $selected = $this->clip(trim((string)($brief['body'] ?? '')), 520);
        $parts = [
            'RELEVANCE LOCK: The image MUST visibly represent this exact selected content: "' . $selected . '".',
        ];
        $parts[] = 'Do not add people, objects, actions, procedures, environments or branding that are not supported by the selected content.';
        return implode(' ', $parts);
    }

    private function humanBrief(array $brief, array $company): string {
        $body = trim((string)($brief['body'] ?? ''));
        $audience = trim((string)($brief['audience'] ?? '')) ?: trim((string)($company['target_audience'] ?? ''));
        $segment = trim((string)($company['segment'] ?? ''));
        $pieces = [$body];
        if ($audience !== '') $pieces[] = 'Público: ' . $audience;
        if ($segment !== '') $pieces[] = 'Segmento: ' . $segment;
        return $this->clip(implode(' · ', array_filter($pieces)), 520);
    }

    private function parseJson(string $text): ?array {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;
        $data = json_decode($clean, true);
        if (is_array($data)) return $data;
        if (preg_match('/\{.*\}/s', $clean, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data)) return $data;
        }
        return null;
    }

    private function clip(string $text, int $max): string {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
        if (function_exists('mb_substr')) return mb_substr($text, 0, $max);
        return substr($text, 0, $max);
    }
}
