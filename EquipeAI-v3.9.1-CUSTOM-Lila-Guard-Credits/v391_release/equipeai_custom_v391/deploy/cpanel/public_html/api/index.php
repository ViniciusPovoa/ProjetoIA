<?php
// Estrutura recomendada:
// /home/SEU_USUARIO/equipeai-backend/   <- pasta backend deste projeto
// /home/SEU_USUARIO/public_html/        <- conteúdo de frontend/dist + esta pasta api
$accountHome = dirname(__DIR__, 2); // /home/SEU_USUARIO
$backendPath = $accountHome . '/equipeai-backend';

if (!is_file($backendPath . '/public/index.php')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Backend não encontrado. Ajuste $backendPath em public_html/api/index.php.',
    ]);
    exit;
}

require $backendPath . '/public/index.php';
