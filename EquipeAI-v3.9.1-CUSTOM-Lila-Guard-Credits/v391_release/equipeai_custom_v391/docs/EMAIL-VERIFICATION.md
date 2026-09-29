# E-mail transacional e verificação de cadastro

A versão 3.0 ativa contas somente depois que o endereço de e-mail é confirmado.

## Fluxo

1. O visitante envia nome completo, empresa, e-mail, senha e aceite dos termos.
2. O backend valida e normaliza os campos.
3. A conta nasce com `status=pending_verification`.
4. O backend gera um token aleatório de uso único e salva **somente o SHA-256** no banco.
5. O link enviado aponta para `FRONTEND_URL/verificar-email?token=...`.
6. O token expira em `EMAIL_VERIFY_MINUTES` (60 minutos por padrão).
7. Depois da confirmação, `email_verified_at` é preenchido e a conta muda para `active`.
8. Links usados/expirados não ativam a conta e o usuário pode solicitar um novo.

O endpoint de reenvio usa mensagem genérica para reduzir enumeração de contas e possui rate limit.

## SMTP em produção

No `backend/.env` configure um SMTP real:

```env
APP_NAME=EquipeAI
FRONTEND_URL=https://ia.vdeveloper.com.br

SMTP_HOST=smtp.seu-provedor.com
SMTP_PORT=587
SMTP_USER=seu-usuario
SMTP_PASSWORD=sua-senha
SMTP_ENCRYPTION=tls

MAIL_FROM=no-reply@seudominio.com.br
MAIL_FROM_NAME=EquipeAI
MAIL_SUPPORT=suporte@seudominio.com.br
EMAIL_VERIFY_MINUTES=60
TERMS_VERSION=2026-09
```

Para porta 465, normalmente use `SMTP_ENCRYPTION=ssl`. Para porta 587, use `tls`.

O backend valida o certificado TLS do servidor SMTP. Não desative essa validação em produção.

## Entregabilidade

Para a mensagem realmente chegar com boa reputação, configure no DNS do domínio usado em `MAIL_FROM`:

- SPF autorizado pelo provedor de e-mail;
- DKIM fornecido pelo provedor;
- DMARC, inicialmente em modo de monitoramento se necessário;
- endereço `MAIL_FROM` no mesmo domínio autenticado sempre que possível.

Sem SMTP, existe fallback para `mail()` do PHP, mas ele depende da configuração da hospedagem e não é a opção recomendada para um SaaS comercial.

## Banco existente

Se o banco já existia antes da versão 3.0, faça backup e execute:

```text
database/migrations/2026_09_28_email_verification_security.sql
```

A migration considera contas antigas como verificadas para não bloquear clientes já cadastrados. Novos cadastros passam pelo fluxo de confirmação.

## Teste mínimo antes de vender

- cadastrar com e-mail real;
- verificar recebimento em caixa de entrada e spam;
- clicar no link uma vez e confirmar ativação;
- testar o mesmo link uma segunda vez;
- testar link expirado;
- solicitar reenvio;
- confirmar que login é bloqueado antes da verificação e liberado depois;
- confirmar que a senha e o token nunca aparecem nos logs.
