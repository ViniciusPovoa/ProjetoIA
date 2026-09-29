# EquipeAI custom v3.8 — Meta Channels

Esta versão foi construída **sobre o ZIP customizado do usuário**, preservando as alterações locais da v3.5 e o Proposal Studio/PDF já mesclado.

## WhatsApp — modo SaaS multiempresa

- Removido o envio dependente de um `WHATSAPP_PHONE_NUMBER_ID` global para os usuários do SaaS.
- Cada `company_id` agora conecta sua própria conta/número pelo **WhatsApp Embedded Signup** da Meta.
- Token de negócio fica criptografado no banco.
- A proposta em PDF é enviada pelo número conectado àquela empresa.
- Tela de conexão disponível no Proposal Studio e em Configurações > Integrações Meta.
- Endpoint opcional de registro de número com PIN de 6 dígitos.

### Novos endpoints

- `GET /integrations/whatsapp`
- `POST /integrations/whatsapp/complete`
- `POST /integrations/whatsapp/register`
- `DELETE /integrations/whatsapp`

### Nova migration

- `database/migrations/2026_09_29_whatsapp_embedded_signup.sql`

## Instagram Publishing incorporado ao projeto custom

- Conexão via Instagram API with Instagram Login.
- Conta conectada por empresa.
- Publicação de imagem no Feed ou Story diretamente do Marketing.
- Legenda, CTA e hashtags são pré-preenchidos no modal de publicação.
- Imagens geradas ganham URL pública temporária para a Meta buscar o arquivo.
- Tokens armazenados criptografados.
- Integração também aparece em Configurações > Integrações Meta.

### Migration

- `database/migrations/2026_09_28_instagram_publishing.sql`

## Compatibilidade com as alterações locais

Foram preservadas as alterações locais existentes, incluindo o renderer editorial, parser de Marketing, VisualPromptService e Proposal Studio.

Peças editoriais que saem como SVG só são convertidas automaticamente para JPEG se o servidor tiver a extensão PHP `Imagick`. Sem Imagick, use um estilo de imagem raster/fotográfico para publicar diretamente no Instagram.
