# Upgrade v3.3 → v3.4

Não há alteração de banco de dados.

## Backend alterado
- `backend/providers/CloudflareImageProvider.php`
- `backend/services/ImageGenerationService.php`
- `backend/public/index.php`
- arquivos `.env.example`

## Frontend alterado
- `frontend/src/pages/AgentRunner.tsx`
- `frontend/src/ux2026.css`
- `frontend/package.json`

## Variáveis recomendadas

```env
CLOUDFLARE_ACCOUNT_ID=
CLOUDFLARE_API_TOKEN=
CLOUDFLARE_AI_MODEL=@cf/black-forest-labs/flux-1-schnell
CLOUDFLARE_AI_FALLBACK_MODEL=@cf/bytedance/stable-diffusion-xl-lightning
MARKETING_IMAGE_CREDIT_COST=4
```

O fallback é acionado automaticamente em falhas de capacidade/cota/modelo quando possível.
