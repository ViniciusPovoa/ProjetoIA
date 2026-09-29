# EquipeAI v3.3 — Marketing Workspace + Geração de Imagens

## Novidades principais

- Nova experiência do agente de **Marketing** com resultado estruturado em formato de workspace.
- A resposta deixa de aparecer como um textão e passa a ser organizada em:
  - visão geral de conteúdos;
  - cards de navegação por conteúdo;
  - detalhe estruturado por blocos;
  - legenda, hashtags e CTA separados.
- Botões de ação por bloco e por conteúdo:
  - copiar;
  - alternar para texto bruto;
  - editar;
  - regenerar;
  - favoritar.
- Nova função **Gerar imagem** dentro do Marketing.
- Ao gerar imagem, o usuário escolhe entre:
  - **Story 9:16**;
  - **Publicação 4:5**.
- Integração com **Cloudflare Workers AI** para geração de imagem usando o modelo padrão `@cf/black-forest-labs/flux-1-schnell`.
- A geração de texto permanece no fluxo atual; a geração de imagem usa um fluxo separado.
- As imagens retornam em `data URI`, com preview e download direto no frontend.
- Custo de imagem controlado por `MARKETING_IMAGE_CREDIT_COST` no backend.

## Backend

- Novo endpoint: `POST /agents/{id}/generate-image`
- Novas classes:
  - `providers/ImageProviderInterface.php`
  - `providers/CloudflareImageProvider.php`
  - `services/ImageGenerationService.php`
- Atualização do `/health` para versão `3.3.0`.

## Frontend

- Reescrita da experiência de resultado em `pages/AgentRunner.tsx`.
- Novos estilos em `ux2026.css` para o Marketing Workspace.

## Configuração necessária no .env

```env
CLOUDFLARE_ACCOUNT_ID=
CLOUDFLARE_API_TOKEN=
CLOUDFLARE_AI_MODEL=@cf/black-forest-labs/flux-1-schnell
MARKETING_IMAGE_CREDIT_COST=4
```
