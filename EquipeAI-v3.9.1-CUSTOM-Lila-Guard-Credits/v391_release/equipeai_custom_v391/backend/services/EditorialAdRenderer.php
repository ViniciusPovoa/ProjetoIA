<?php
namespace App\services;

final class EditorialAdRenderer {
    public function render(array $brief, int $companyId, string $format, ?array $illustration = null): array {
        $width = 1080;
        $height = $format === 'story' ? 1920 : 1350;
        $company = trim((new CompanyContextService())->companyName($companyId)) ?: 'Sua empresa';
        $platform = trim((string)($brief['platform'] ?? 'Instagram')) ?: 'Instagram';
        $blockLabel = trim((string)($brief['block_label'] ?? 'Conteúdo')) ?: 'Conteúdo';
        $blockIndex = max(1, (int)($brief['block_index'] ?? 1));
        $copy = $this->cleanCopy((string)($brief['body'] ?? $brief['summary'] ?? $brief['title'] ?? ''));
        [$headline, $support] = $this->splitCopy($copy);

        $palettes = [
            ['background' => '#F7F8FB', 'ink' => '#111827', 'muted' => '#667085', 'accent' => '#315CF5', 'soft' => '#E8EEFF'],
            ['background' => '#FBFAF7', 'ink' => '#17211B', 'muted' => '#66736A', 'accent' => '#167D5A', 'soft' => '#E2F3EA'],
            ['background' => '#F8F7FC', 'ink' => '#19162A', 'muted' => '#6E6A7C', 'accent' => '#7657E6', 'soft' => '#ECE7FF'],
            ['background' => '#FAF8F5', 'ink' => '#231A17', 'muted' => '#766761', 'accent' => '#E05B3F', 'soft' => '#FBE7E1'],
        ];
        $paletteIndex = random_int(0, count($palettes) - 1);
        $palette = $palettes[$paletteIndex];

        $headlineSize = $this->headlineSize($headline, $format);
        $headlineWidth = $width - 144;
        $headlineLines = $this->wrap($headline, max(12, (int)floor($headlineWidth / ($headlineSize * 0.53))), 4);
        $headlineLineHeight = (int)round($headlineSize * 1.04);
        $headlineY = $format === 'story' ? 390 : 300;
        $headlineSvg = $this->textLines($headlineLines, 72, $headlineY, $headlineSize, $headlineLineHeight, $palette['ink'], $palette['accent'], 760);

        $supportY = $headlineY + (count($headlineLines) * $headlineLineHeight) + 64;
        $supportSize = $format === 'story' ? 42 : 36;
        $supportLines = $support !== '' ? $this->wrap($support, $format === 'story' ? 39 : 48, $format === 'story' ? 6 : 5) : [];
        $supportSvg = $this->textLines($supportLines, 76, $supportY, $supportSize, (int)round($supportSize * 1.35), $palette['muted'], $palette['muted'], 430);

        $eyebrow = $this->xml(mb_strtoupper($company . '  •  ' . $platform, 'UTF-8'));
        $section = $this->xml(mb_strtoupper($blockLabel, 'UTF-8'));
        $index = str_pad((string)$blockIndex, 2, '0', STR_PAD_LEFT);
        $footerY = $height - 92;
        $decorY = $height - ($format === 'story' ? 470 : 300);
        $hasIllustration = is_array($illustration) && str_starts_with((string)($illustration['image_data_uri'] ?? ''), 'data:image/');
        $textBottom = $supportLines
            ? $supportY + (count($supportLines) * (int)round($supportSize * 1.35))
            : $headlineY + (count($headlineLines) * $headlineLineHeight);
        $photoY = max($textBottom + 56, $format === 'story' ? 1040 : 720);
        $photoHeight = max(230, $footerY - $photoY - 92);
        $photoBottom = $photoY + $photoHeight;
        $photoUri = $hasIllustration ? $this->xml((string)$illustration['image_data_uri']) : '';
        $visualSvg = $hasIllustration
            ? '<defs><clipPath id="editorialPhoto"><rect x="72" y="' . $photoY . '" width="936" height="' . $photoHeight . '" rx="34"/></clipPath></defs>' .
              '<rect x="72" y="' . ($photoY - 18) . '" width="210" height="6" rx="3" fill="' . $palette['accent'] . '"/>' .
              '<image href="' . $photoUri . '" x="72" y="' . $photoY . '" width="936" height="' . $photoHeight . '" preserveAspectRatio="xMidYMid slice" clip-path="url(#editorialPhoto)"/>' .
              '<rect x="72" y="' . $photoY . '" width="936" height="' . $photoHeight . '" rx="34" fill="none" stroke="' . $palette['ink'] . '" stroke-opacity="0.12" stroke-width="2"/>' .
              '<rect x="72" y="' . ($photoBottom - 16) . '" width="936" height="16" rx="8" fill="' . $palette['accent'] . '" fill-opacity="0.85"/>'
            : '<rect x="72" y="' . $decorY . '" width="936" height="6" rx="3" fill="' . $palette['soft'] . '"/>' .
              '<rect x="72" y="' . $decorY . '" width="260" height="6" rx="3" fill="' . $palette['accent'] . '"/>' .
              '<circle cx="932" cy="' . $decorY . '" r="42" fill="' . $palette['soft'] . '"/>' .
              '<circle cx="932" cy="' . $decorY . '" r="13" fill="' . $palette['accent'] . '"/>';

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
  <rect width="{$width}" height="{$height}" fill="{$palette['background']}"/>
  <rect x="72" y="70" width="10" height="42" rx="5" fill="{$palette['accent']}"/>
  <text x="104" y="100" fill="{$palette['accent']}" font-family="Inter, Segoe UI, Arial, sans-serif" font-size="20" font-weight="700" letter-spacing="2.4">{$eyebrow}</text>
  <line x1="72" y1="150" x2="1008" y2="150" stroke="{$palette['ink']}" stroke-opacity="0.12"/>
  <text x="936" y="101" text-anchor="end" fill="{$palette['muted']}" font-family="Inter, Segoe UI, Arial, sans-serif" font-size="18" font-weight="600" letter-spacing="1.5">{$section}</text>
  <text x="1008" y="101" text-anchor="end" fill="{$palette['ink']}" font-family="Inter, Segoe UI, Arial, sans-serif" font-size="20" font-weight="800">{$index}</text>
  {$headlineSvg}
  {$supportSvg}
  {$visualSvg}
  <text x="72" y="{$footerY}" fill="{$palette['muted']}" font-family="Inter, Segoe UI, Arial, sans-serif" font-size="19" font-weight="600" letter-spacing="1.5">CONTEÚDO EDITORIAL</text>
  <text x="1008" y="{$footerY}" text-anchor="end" fill="{$palette['accent']}" font-family="Inter, Segoe UI, Arial, sans-serif" font-size="19" font-weight="700">{$this->xml($platform)}</text>
</svg>
SVG;

        return [
            'image_data_uri' => 'data:image/svg+xml;base64,' . base64_encode($svg),
            'mime' => 'image/svg+xml',
            'provider' => $hasIllustration ? 'cloudflare-workers-ai+equipeai-editorial-renderer' : 'equipeai-editorial-renderer',
            'model' => $hasIllustration ? (string)($illustration['model'] ?? 'cloudflare-image') . '+editorial-svg-v2' : 'editorial-svg-v2',
            'meta' => [
                'width' => $width,
                'height' => $height,
                'exact_text' => true,
                'palette' => $paletteIndex + 1,
                'illustration_model' => $hasIllustration ? (string)($illustration['model'] ?? '') : null,
            ],
        ];
    }

    private function cleanCopy(string $copy): string {
        $copy = trim(preg_replace('/\s+/u', ' ', $copy) ?? $copy);
        $copy = preg_replace('/^\s*\([^)]{1,80}\)\s*:\s*/u', '', $copy) ?? $copy;
        return trim($copy, " \t\n\r\0\x0B\"'“”");
    }

    private function splitCopy(string $copy): array {
        if (preg_match('/^(.{3,90}\?)(?:\s+)(.+)$/u', $copy, $match)) {
            return [trim($match[1]), trim($match[2], " \t\n\r\0\x0B\"'“”")];
        }
        if (mb_strlen($copy) <= 82) return [$copy, ''];
        $words = preg_split('/\s+/u', $copy) ?: [$copy];
        $headlineWords = array_slice($words, 0, min(8, count($words)));
        $supportWords = array_slice($words, count($headlineWords));
        return [implode(' ', $headlineWords), implode(' ', $supportWords)];
    }

    private function headlineSize(string $headline, string $format): int {
        $length = mb_strlen($headline);
        $base = $format === 'story' ? 112 : 96;
        if ($length > 62) return $base - 22;
        if ($length > 38) return $base - 12;
        return $base;
    }

    private function wrap(string $text, int $maxChars, int $maxLines): array {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($current !== '' && mb_strlen($candidate) > $maxChars) {
                $lines[] = $current;
                $current = $word;
                if (count($lines) === $maxLines - 1) break;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '' && count($lines) < $maxLines) {
            $consumed = implode(' ', $lines);
            $remaining = trim(mb_substr($text, mb_strlen($consumed)));
            $lines[] = $remaining !== '' ? $remaining : $current;
        }
        return array_values(array_filter($lines, static fn(string $line): bool => trim($line) !== ''));
    }

    private function textLines(array $lines, int $x, int $y, int $fontSize, int $lineHeight, string $color, string $lastColor, int $weight): string {
        if (!$lines) return '';
        $parts = [];
        $last = count($lines) - 1;
        foreach ($lines as $index => $line) {
            $lineY = $y + ($index * $lineHeight);
            $fill = $index === $last ? $lastColor : $color;
            $parts[] = '<text x="' . $x . '" y="' . $lineY . '" fill="' . $fill . '" font-family="Inter, Segoe UI, Arial, sans-serif" font-size="' . $fontSize . '" font-weight="' . $weight . '" letter-spacing="-2">' . $this->xml($line) . '</text>';
        }
        return implode("\n  ", $parts);
    }

    private function xml(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
