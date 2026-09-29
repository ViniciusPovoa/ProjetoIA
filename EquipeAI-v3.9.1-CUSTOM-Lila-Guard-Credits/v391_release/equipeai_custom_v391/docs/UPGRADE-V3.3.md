# Upgrade para v3.3

## Sem alterações de banco

A atualização da v3.2 para a v3.3 **não exige migration**.

## O que enviar

### Backend
Envie os arquivos atualizados do backend, principalmente:
- `public/index.php`
- `controllers/AgentController.php`
- `services/ImageGenerationService.php`
- `providers/ImageProviderInterface.php`
- `providers/CloudflareImageProvider.php`

### Frontend
Envie o frontend atualizado e rode novamente:

```bash
npm install
npm run build
```

Depois publique o conteúdo de `frontend/dist/` no domínio do frontend.

## Variáveis novas no .env

```env
CLOUDFLARE_ACCOUNT_ID=
CLOUDFLARE_API_TOKEN=
CLOUDFLARE_AI_MODEL=@cf/black-forest-labs/flux-1-schnell
MARKETING_IMAGE_CREDIT_COST=4
```
