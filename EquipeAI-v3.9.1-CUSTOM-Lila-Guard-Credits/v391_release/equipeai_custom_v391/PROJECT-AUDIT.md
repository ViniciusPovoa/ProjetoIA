# Auditoria final do MVP

Data da revisão: 26/09/2026.

## Resultado dos checks executados neste ambiente

- ✅ 51 arquivos PHP (backend + wrapper de deploy) passaram em `php -l`.
- ✅ 20 arquivos TypeScript/TSX passaram por parser/transpilação sintática do TypeScript.
- ✅ `frontend/package.json` é JSON válido e as versões foram fixadas para instalação reproduzível.
- ✅ Banco contém 23 tabelas com FKs, índices e constraints para o MVP.
- ✅ API contém 58 registros de rota.
- ✅ `CHECKLIST-44-44.md` contém as 44 seções do TXT mapeadas para implementação.
- ✅ Smoke test real de `GET /api/health`: HTTP 200.
- ✅ Headers de segurança foram verificados no smoke test (nosniff, frame deny, referrer, permissions e CSP).
- ✅ Varredura do pacote não encontrou `.env` de produção nem padrões de chaves/tokens preenchidos.
- ✅ Wrapper de deploy cPanel revisado para `/home/USUARIO/equipeai-backend`.
- ✅ Reserva/confirm/refund de créditos usa transação e lock de saldo.
- ✅ Renovação paga usa franquia por ciclo e idempotência por pagamento.
- ✅ PDF tem fallback de análise nativa via provider Gemini quando não há extrator local.

## O que este ambiente NÃO permitiu validar

O ambiente de geração não possui acesso DNS ao registry do NPM (`registry.npmjs.org`) e não possui as extensões PHP `pdo_mysql`, `curl`, `mbstring` e `zip`. Por isso, não foi possível neste ambiente:

- executar `npm install` e o build Vite completo;
- importar o schema em um MySQL real;
- executar cadastro/login com persistência MySQL real;
- chamar OpenAI/Anthropic/Gemini/DeepSeek com credenciais reais;
- enviar e-mail SMTP real;
- realizar checkout/cobrança/webhook real do Mercado Pago.

Isso não foi mascarado como “teste concluído”. O projeto inclui `scripts/verify.sh` e `docs/TESTING.md` para esses testes no seu computador/staging, onde as dependências e credenciais estarão disponíveis.

## Critério para abrir vendas

Considere o código do MVP concluído, mas somente abra produção depois que o roteiro de `docs/TESTING.md` passar no seu servidor com:

- MySQL;
- HTTPS;
- extensões PHP requeridas;
- pelo menos um provider de IA;
- Gemini se quiser análise nativa de PDF;
- SMTP;
- Mercado Pago + secret de webhook;
- custos atuais dos modelos configurados no admin;
- backup e cron ativos.

Esse é o ponto de separação entre **escopo implementado** e **integração externa validada em produção**.
