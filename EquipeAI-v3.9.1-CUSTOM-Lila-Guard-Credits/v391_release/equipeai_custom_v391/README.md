# EquipeAI v3.4 3.2 — SaaS operacional para pequenas empresas

MVP full stack criado para funcionar em hospedagem PHP/cPanel, com frontend React + TypeScript e backend PHP 8.2+ + MySQL. O usuário escolhe a tarefa/agente; a plataforma decide o provider/modelo configurado no painel administrativo.


## Novidades da 3.2

- Workspace redesenhado com hierarquia editorial, workbench de tarefas e painel operacional mais denso e legível.
- Busca global por teclado (`Ctrl/Cmd + K`) para navegar entre ferramentas e áreas do produto.
- Novo padrão visual aplicado aos componentes internos: superfícies, formulários, tabelas, agentes, histórico, configurações e admin.
- Contexto empresarial com avaliação semântica por tipo de campo, em vez de apenas comprimento de texto.
- “Melhorar com IA” usa contexto já cadastrado e catálogo de serviços, faz até duas passagens curtas, exige ganho real de qualidade e só aplica automaticamente quando o campo passa a rubrica mínima.
- Quando faltam fatos reais, o sistema não inventa: informa exatamente quais detalhes precisam ser adicionados.
- Desfazer imediato após uma melhoria aplicada.
- Nenhuma alteração de banco é necessária para migrar de 3.1 para 3.2.

## O que já existe no projeto

- Landing page comercial e aplicação responsiva desktop/mobile.
- Cadastro profissional com validação completa, confirmação de e-mail por link, login, logout, recuperação/troca de senha e exclusão de conta.
- Sessão em cookie `HttpOnly`, CSRF para mutações e Bearer como alternativa para integração de API.
- Rate limiting por IP/usuário/endpoint.
- Perfil completo da empresa, personalidade, regras e catálogo de serviços.
- 7 agentes iniciais: Atendimento, Marketing, Comercial, Avaliações, Documentos, Administrativo e Follow-up.
- Formulários dos agentes definidos por `input_schema` no banco: novos agentes/campos podem ser criados pelo admin sem alterar React.
- Providers desacoplados: OpenAI, Anthropic, Gemini e DeepSeek.
- Roteamento Agente → Provider → Modelo armazenado no banco.
- Sistema de créditos com reserva, confirmação e reembolso transacional.
- Histórico paginado, favorito, copiar, reutilizar e excluir.
- Upload seguro PDF/DOCX/TXT/CSV; PDF pode ser enviado nativamente ao Gemini quando não houver extração local.
- Planos, assinatura recorrente Mercado Pago, cancelamento, webhooks assinados e idempotência.
- Painel admin para usuários, planos, agentes, providers, modelos/custos, créditos, finanças e logs.
- Monitoramento: receita, custo estimado de IA, infraestrutura, outros custos e margem.
- Logs de IA, pagamentos, segurança, erros, autenticação/auditoria e webhooks.
- Job de manutenção/renovação de ciclos internos e limpeza de sessões/logs.
- Estrutura e empacotamento para cPanel.

## Stack

### Frontend
- React
- TypeScript
- Vite
- Design system próprio + react-icons
- Axios
- React Router
- react-icons

### Backend
- PHP 8.2+
- PDO / MySQL 8 ou MariaDB compatível
- API REST
- cURL
- `fileinfo`
- `mbstring`
- `zip` recomendado para DOCX

Não usa Laravel, Next.js, Node no backend, Redis, Docker ou processo persistente.

## Estrutura

```text
frontend/                  React + TypeScript
backend/
  config/                  bootstrap e banco
  contracts/               contratos dos agentes
  controllers/             camada HTTP
  middleware/              auth, CSRF, rate limit, segurança
  providers/               OpenAI/Anthropic/Gemini/DeepSeek
  repositories/            acesso a dados
  services/                regras de negócio
  utils/                    request/response/router/log/auditoria
  public/                   front controller da API
  cron/                     manutenção
  storage/                  uploads/logs/cache (não público)
database/schema.sql         estrutura + seeds
deploy/cpanel/              wrappers/.htaccess para cPanel
scripts/                    validação e pacote de release
docs/                       deploy, segurança e testes
```

## Instalação local

### 1. Banco

Crie um banco vazio e importe:

```bash
mysql -u root -p equipeai < database/schema.sql
```

### 2. Backend

```bash
cp backend/.env.example backend/.env
```

Preencha pelo menos:

```env
APP_URL=http://localhost:8080
FRONTEND_URL=http://localhost:5173
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=equipeai
DB_USER=seu_usuario
DB_PASSWORD=sua_senha
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
GEMINI_API_KEY=
DEEPSEEK_API_KEY=
```

Para desenvolvimento:

```bash
php -S 127.0.0.1:8080 -t backend/public backend/public/router.php
```

A API deve responder em `http://127.0.0.1:8080/api/health` ou `/health`, dependendo do proxy usado.

### 3. Frontend

```bash
cd frontend
npm install
npm run dev
```

O Vite usa `/api` para falar com o backend. Em produção, frontend e API devem preferencialmente usar o mesmo domínio.

## Primeiro administrador

1. Cadastre uma conta normalmente.
2. No banco, promova apenas a sua conta:

```sql
UPDATE users SET role='admin', updated_at=NOW() WHERE email='SEU_EMAIL';
```

Depois entre novamente. Não deixe cadastro público criando administradores.

## Providers de IA

As chaves ficam **somente no backend**. O admin escolhe o provider e modelo de cada agente. Os nomes e preços dos modelos são configuráveis sem alteração de código.

Os custos por milhão de tokens vêm como `NULL` no seed de propósito: antes de vender, abra **Administrador → IA & custos** e informe os preços atuais da sua conta. `USD_BRL_RATE` converte custos em USD para BRL no dashboard financeiro.

O Agente de Documentos vem inicialmente em Gemini porque o provider suporta anexar o PDF original. TXT/CSV e, quando a extensão PHP `zip` estiver disponível, DOCX são extraídos localmente. PDFs textuais também podem usar `pdftotext`/Poppler se a hospedagem oferecer o binário.

## Mercado Pago recorrente

Configure:

```env
MERCADOPAGO_ACCESS_TOKEN=
MERCADOPAGO_WEBHOOK_SECRET=
```

Webhook público:

```text
https://SEU_DOMINIO/api/webhooks/mercadopago
```

Ative no Mercado Pago as notificações relacionadas a assinaturas/preapproval, authorized payments e payments. O backend:

1. cria a assinatura;
2. envia o usuário ao `init_point`;
3. valida a assinatura do webhook quando o secret estiver configurado;
4. registra um `event_key` único;
5. consulta o recurso novamente no Mercado Pago;
6. credita apenas pagamentos aprovados;
7. impede crédito duplicado usando `provider_payment_id` + referência de transação.

Nunca libere crédito com base apenas no retorno do navegador.

## E-mail / verificação / recuperação de senha

Novos cadastros ficam pendentes até o usuário clicar no link de confirmação enviado por e-mail. O token é aleatório, expira e somente o hash é persistido no banco. Configure SMTP em produção; sem SMTP, o backend tenta `mail()`, que depende da hospedagem.

```env
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASSWORD=
SMTP_ENCRYPTION=tls
MAIL_FROM=no-reply@seudominio.com.br
MAIL_FROM_NAME=EquipeAI
MAIL_SUPPORT=suporte@seudominio.com.br
EMAIL_VERIFY_MINUTES=60
TERMS_VERSION=2026-09
```

Para banco já existente, execute `database/migrations/2026_09_28_email_verification_security.sql`. Veja `docs/EMAIL-VERIFICATION.md` e `docs/UPGRADE-V3.md`.

## Cron obrigatório/recomendado

No cPanel, rode uma vez por hora:

```bash
/usr/local/bin/php /home/USUARIO/equipeai-backend/cron/maintenance.php
```

O job limpa sessões/reset/rate limits vencidos, renova ciclos de planos internos/gratuitos e remove arquivos de log antigos conforme `LOG_RETENTION_DAYS`.

## Deploy no cPanel

Veja `docs/DEPLOY-CPANEL.md`. Em resumo:

```bash
./scripts/package-cpanel.sh
```

O script executa o build do frontend e gera um pacote com:

- `public_html/` — SPA compilada + entrada `/api`;
- `equipeai-backend/` — código PHP fora da pasta pública.

## Segurança implementada

A versão 3.0 acrescenta confirmação obrigatória de e-mail, validação de senha forte, tokens de verificação com hash/expiração/uso único, headers adicionais e CSP no frontend.

- `password_hash()` / `password_verify()`.
- Tokens de sessão armazenados como hash no banco.
- Cookie `HttpOnly`, `SameSite=Lax` e `Secure` em HTTPS.
- CSRF nas mutações autenticadas por cookie.
- PDO com prepared statements.
- Controle de propriedade por usuário/empresa.
- Admin guard separado.
- Rate limiting em login/cadastro/reset/IA/upload.
- MIME + extensão + limite + nome aleatório nos uploads.
- Uploads fora do diretório público e `.htaccess` de bloqueio.
- Separação SYSTEM / COMPANY CONTEXT / USER INPUT / DOCUMENT CONTENT.
- Webhook idempotente e assinatura verificável.
- Erros internos não são exibidos em produção.
- Segredos nunca devem entrar no Git.

Veja `docs/SECURITY.md` para o checklist de produção.

## Validação

```bash
./scripts/verify.sh
```

Esse script exige `node_modules` instalado e executa:

- lint sintático de todos os PHP;
- build de produção do frontend.

Para testes funcionais externos (IA, SMTP e Mercado Pago), configure as credenciais de sandbox/produção e execute `docs/TESTING.md`.

## Antes de abrir vendas

O código cobre o escopo funcional do MVP, mas a operação real depende das suas contas externas e da configuração do servidor. Antes de receber clientes:

1. instale/importa banco e configure HTTPS;
2. configure SMTP;
3. configure pelo menos um provider de IA e faça chamadas reais;
4. configure custos atuais dos modelos;
5. configure Mercado Pago e valide um ciclo em sandbox/valor controlado;
6. valide webhook no domínio público;
7. crie termos de uso/política de privacidade adequados ao seu negócio e à LGPD;
8. configure backups do banco e dos uploads;
9. habilite cron e monitore logs/admin financeiro.

## Licenciamento e marca

`EquipeAI` é apenas o nome de trabalho usado no projeto. Troque nome, domínio, identidade visual, textos legais e dados comerciais antes de lançamento se desejar outra marca.

## Novidades da versão 3.1
- Sidebar desktop recolhível e grupos de navegação fechados por padrão.
- Escala tipográfica do produto revisada para melhorar leitura em telas desktop.
- Minha empresa agora mostra tooltips, qualidade estimada por campo e score geral de contexto.
- Campos contextuais muito básicos podem ser refinados por IA com uma geração curta e controlada.
- O refinamento não desconta créditos do usuário, possui rate limit e entra no log de custo/uso da IA.
- Consulte `docs/UPGRADE-V3.1.md` para atualizar uma instalação 3.0 existente.

## Novidades da versão 3.2
- Workspace redesenhado para uma experiência operacional mais madura e menos parecida com dashboard genérico.
- Busca global `Ctrl/Cmd + K`.
- Dashboard com workbench, atividade compacta e pulso de créditos/contexto.
- Biblioteca de ferramentas e executor de agentes revisados.
- Avaliação de contexto específica por tipo de campo.
- `Melhorar com IA` usa contexto da empresa + serviços, exige melhoria real e só aplica automaticamente quando atinge a meta de qualidade.
- Não há migration de banco nesta atualização.
- Consulte `docs/UPGRADE-V3.2.md` para atualizar uma instalação 3.1 existente.

## Lila v3.9.1

A Lila possui escopo fechado ao EquipeAI e à operação da empresa. Cada pergunta/resposta consome 1 crédito. Pedidos fora do escopo recebem uma resposta de bloqueio e também consomem 1 crédito. Falhas técnicas devolvem automaticamente o crédito reservado. Consulte `docs/LILA.md`.
