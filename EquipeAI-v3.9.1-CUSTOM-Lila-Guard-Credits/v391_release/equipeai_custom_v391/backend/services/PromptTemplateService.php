<?php
namespace App\services;

final class PromptTemplateService
{
    public function render(string $template, int $companyId): string
    {
        $context = new CompanyContextService();
        return strtr($template, [
            '{{company_name}}' => $context->companyName($companyId),
            '{{company_context}}' => $context->build($companyId),
            '{{services}}' => $context->servicesText($companyId),
            '{{tone}}' => $context->tone($companyId),
        ]);
    }
}
