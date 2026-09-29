# Upgrade v3.5

Não há alteração de banco de dados.

## Backend atualizado
- `services/ImageGenerationService.php`
- `services/VisualPromptService.php`
- `.env.example`
- `.env.deploy.example`
- `public/index.php`

## Frontend atualizado
- `src/pages/AgentRunner.tsx`
- `src/ux2026.css`

## Novas variáveis opcionais
```env
IMAGE_PROMPT_ENHANCER_ENABLED=true
IMAGE_PROMPT_MAX_TOKENS=320
```

Se `IMAGE_PROMPT_ENHANCER_ENABLED=false`, o sistema usa o planejador determinístico local.
