# Integrações Meta — Instagram + WhatsApp

## Chave de criptografia

Gere uma chave e coloque no backend `.env`:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

```env
APP_ENCRYPTION_KEY=COLE_A_CHAVE_DE_64_HEX_AQUI
```

## Instagram

Variáveis:

```env
META_INSTAGRAM_APP_ID=
META_INSTAGRAM_APP_SECRET=
META_INSTAGRAM_REDIRECT_URI=https://api-ia.vpdeveloper.com.br/api/integrations/instagram/callback
META_INSTAGRAM_GRAPH_VERSION=v26.0
```

Importe:

`database/migrations/2026_09_28_instagram_publishing.sql`

O fluxo usa Instagram API with Instagram Login e solicita:

- `instagram_business_basic`
- `instagram_business_content_publish`

A conta precisa ser profissional (Business ou Creator). Em desenvolvimento, teste com uma conta autorizada no app da Meta. Para clientes externos, prepare App Review/Advanced Access conforme exigido pela Meta.

### Teste

1. Abra `Configurações` no EquipeAI.
2. Em `Integrações Meta`, clique `Conectar` no Instagram.
3. Autorize a conta profissional.
4. Abra Marketing e gere conteúdo.
5. Gere uma imagem raster compatível.
6. No painel Visual, clique `Publicar no Instagram`.
7. Revise a legenda e escolha Feed ou Story.
8. Confirme a publicação.

A imagem precisa estar publicamente acessível por HTTPS. O EquipeAI cria uma URL temporária por 48 horas.

### Peça editorial SVG

O renderer editorial customizado do projeto produz SVG. O backend converte SVG para JPEG automaticamente se `Imagick` estiver disponível. Sem `Imagick`, gere uma versão fotográfica/raster para publicar pelo Instagram.

## WhatsApp — Embedded Signup

Variáveis:

```env
META_WHATSAPP_APP_ID=
META_WHATSAPP_APP_SECRET=
META_WHATSAPP_CONFIG_ID=
META_WHATSAPP_GRAPH_VERSION=v26.0
META_WHATSAPP_EMBEDDED_FEATURE_TYPE=
```

Importe:

`database/migrations/2026_09_29_whatsapp_embedded_signup.sql`

No Meta for Developers, crie uma configuração em Facebook Login for Business usando a variação **WhatsApp Embedded Signup**, com as permissões necessárias para WhatsApp Business Management/Messaging. Copie o Configuration ID para `META_WHATSAPP_CONFIG_ID`.

### Fluxo do cliente

1. Empresa abre Comercial > Proposta ou Configurações > Integrações Meta.
2. Clica `Conectar meu WhatsApp`.
3. O popup da Meta permite selecionar/criar a WABA e o número da própria empresa.
4. O frontend recebe um código de autorização e dados da sessão.
5. O backend troca o código por um token de negócio e salva tudo criptografado por `company_id`.
6. As propostas passam a sair daquele número.

O sistema não usa mais um número central para envios dos clientes.

### Registro do número

Se a Meta exigir o registro da Cloud API, o usuário pode informar o PIN de 6 dígitos na própria tela. O endpoint chama `/{phone_number_id}/register`.

### Coexistência

Se você habilitar na Meta o onboarding que mantém o mesmo número também no aplicativo WhatsApp Business, configure `META_WHATSAPP_EMBEDDED_FEATURE_TYPE` de acordo com a configuração de coexistência usada no seu app Meta.
