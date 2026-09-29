# Segurança de produção

## Aplicação

- Deixe `APP_DEBUG=false`.
- Use HTTPS obrigatório.
- Gere senhas fortes de banco/cPanel/SMTP.
- Não versionar `.env`, tokens ou dumps de produção.
- Mantenha PHP e dependências do frontend atualizados.
- Restrinja acesso ao painel administrativo a contas necessárias.

## Banco e autorização

A API usa IDs do usuário autenticado e `company_id` obtido no backend. Não confia em `user_id` enviado pelo frontend. As consultas de histórico, serviços, arquivos e dados de empresa verificam propriedade.

## Sessão, Bearer e CSRF

O banco guarda somente o hash do token de sessão. O backend aceita cookie `HttpOnly` para implantação same-site e Bearer. No deploy atual, frontend e API usam domínios-base diferentes, então o React usa Bearer em `sessionStorage` para não depender de cookies cross-site; o token desaparece ao encerrar a sessão do navegador. Mutações autenticadas por cookie exigem CSRF. A CSP do frontend, ausência de scripts de terceiros e headers de segurança reduzem a superfície de XSS.

Troca/reset de senha invalida sessões existentes e há limite para acúmulo de sessões antigas.

## Cadastro e confirmação de e-mail

- Novos usuários ficam `pending_verification` até confirmar o endereço.
- O token é gerado com `random_bytes`, tem uso único e expiração.
- Apenas `SHA-256(token)` é salvo no banco.
- Reenvio usa resposta genérica e rate limit para reduzir enumeração de contas.
- Login de conta não verificada é recusado.
- Política de senha exige 10+ caracteres, maiúscula, minúscula, número e símbolo.
- Não registre tokens de verificação em logs.

## Headers do frontend

O `.htaccess` do frontend aplica CSP, HSTS em HTTPS, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `Permissions-Policy`, `nosniff` e COOP. O `no-referrer` é especialmente importante na rota de verificação para não enviar o token da URL a destinos externos.

## Uploads

- Extensões permitidas: PDF, DOCX, TXT e CSV.
- MIME é conferido pelo conteúdo.
- Tamanho é limitado por `MAX_UPLOAD_MB`.
- Nome físico é aleatório.
- Arquivo não é executado.
- Download/conteúdo passa por autorização.
- Storage fica fora da pasta pública no layout recomendado.

## Prompt injection

O motor monta blocos separados de SYSTEM INSTRUCTIONS, COMPANY CONTEXT, USER INPUT e DOCUMENT CONTENT. Documento e entrada do usuário são explicitamente classificados como conteúdo não confiável. Isso reduz o risco, mas nenhuma defesa de prompt injection é absoluta; não dê aos modelos credenciais nem ações privilegiadas diretas.

## Webhook

Com `MERCADOPAGO_WEBHOOK_SECRET` configurado, `X-Signature` é verificado. Eventos recebem chave idempotente única. O backend consulta a API do Mercado Pago para obter o estado do pagamento antes de creditar.

## Logs

Não grave senha/API key. Restrinja acesso à pasta `storage/logs`. Revise logs de segurança, auditoria, pagamentos e webhook no painel administrativo.

## LGPD/operação

O sistema permite exclusão da conta, histórico e arquivos. Antes de lançar comercialmente, publique uma política de privacidade/termos adequados à sua operação, defina base legal/retenção e canal de atendimento a titulares. Avalie com profissional jurídico quando necessário.
