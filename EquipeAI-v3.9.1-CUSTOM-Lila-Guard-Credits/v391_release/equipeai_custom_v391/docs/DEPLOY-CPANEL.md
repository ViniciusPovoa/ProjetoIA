# Deploy no cPanel

## Pré-requisitos

- PHP 8.2 ou superior.
- MySQL/MariaDB com InnoDB e suporte a JSON.
- Extensões PHP: `pdo_mysql`, `curl`, `fileinfo`, `mbstring`; `zip` recomendada.
- HTTPS ativo.
- Node/NPM pode estar apenas na máquina de build; o servidor final não precisa de Node.

## 1. Banco

No cPanel:

1. crie banco e usuário;
2. dê todas as permissões necessárias ao usuário nesse banco;
3. importe `database/schema.sql` pelo phpMyAdmin ou CLI.

## 2. Configuração

Copie `backend/.env.example` para `backend/.env`, preencha e **não coloque o `.env` no repositório público**.

Use URL HTTPS real em `APP_URL` e `FRONTEND_URL`.

## 3. Build e pacote

Em uma máquina com Node 20+:

```bash
cd /caminho/do/projeto
./scripts/package-cpanel.sh
```

O arquivo `equipeai-cpanel-release.zip` será criado na raiz do projeto.

## 4. Organização recomendada no servidor

```text
/home/SEU_USUARIO/
  equipeai-backend/        <- backend completo, .env e storage
  public_html/             <- conteúdo compilado do frontend + api/index.php + .htaccess
```

O wrapper de API em `public_html/api/index.php` procura automaticamente `equipeai-backend/public/index.php` como pasta irmã de `public_html`. Se usar outro nome/caminho, ajuste esse arquivo.

## 5. Permissões

Pastas graváveis:

```text
equipeai-backend/storage/uploads
equipeai-backend/storage/logs
equipeai-backend/storage/cache
```

Use a menor permissão necessária para o usuário do PHP. Evite `777`.

## 6. Cron

No cPanel → Cron Jobs, uma vez por hora:

```bash
/usr/local/bin/php /home/SEU_USUARIO/equipeai-backend/cron/maintenance.php
```

Se seu PHP CLI estiver em outro caminho, use o exibido pelo provedor da hospedagem.

## 7. Webhook Mercado Pago

Cadastre:

```text
https://SEU_DOMINIO/api/webhooks/mercadopago
```

Depois salve o secret em `MERCADOPAGO_WEBHOOK_SECRET`.

## 8. Smoke test

Abra:

```text
https://SEU_DOMINIO/api/health
```

Depois siga `TESTING.md`.

## 9. Backup

Antes de vender:

- backup automático diário do MySQL;
- backup dos uploads;
- retenção e restauração testadas;
- cópia fora da própria conta de hospedagem quando possível.
