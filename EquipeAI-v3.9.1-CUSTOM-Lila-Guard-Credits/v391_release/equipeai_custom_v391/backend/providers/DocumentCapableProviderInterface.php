<?php
namespace App\providers;

interface DocumentCapableProviderInterface
{
    /**
     * Execute a prompt with the original document attached to the provider.
     * Used when local text extraction is unavailable (for example PDFs on cPanel).
     */
    public function generateWithDocument(
        string $model,
        array $messages,
        string $filePath,
        string $mimeType,
        array $options = []
    ): array;
}
