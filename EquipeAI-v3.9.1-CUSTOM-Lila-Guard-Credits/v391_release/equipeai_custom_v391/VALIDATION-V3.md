# Validação técnica — EquipeAI 3.0

Data da revisão: 28/09/2026.

## Executado neste ambiente

- PHP lint em todos os arquivos do backend.
- API iniciada com o servidor embutido do PHP.
- `GET /api/health` retornou HTTP 200 e versão `3.0.0`.
- Parser TypeScript executado sobre todos os arquivos `.ts` e `.tsx`: sem erros de sintaxe.
- Validação unitária direta das regras de nome, empresa, e-mail e senha.
- Varredura do pacote para evitar inclusão acidental das credenciais sensíveis compartilhadas durante a conversa.
- Varredura visual por emojis/clichês gráficos no código do frontend e pesos tipográficos pesados na nova camada visual.

## Limite do ambiente

O ambiente de geração não possui acesso funcional ao registro NPM, então não foi possível executar um `npm install && npm run build` real desta versão. O código TypeScript/TSX foi verificado pelo compilador local em modo de parsing. Antes do deploy, execute o build normal em uma máquina com acesso ao NPM.

Também não há um MySQL de produção nem credenciais SMTP reais neste ambiente. Por isso, cadastro completo + persistência + entrega real do e-mail precisam do teste pós-deploy descrito em `docs/EMAIL-VERIFICATION.md`.

## Gate de produção recomendado

1. `npm install && npm run build` no frontend.
2. Backup do banco atual.
3. Migration `database/migrations/2026_09_28_email_verification_security.sql`.
4. Configuração SMTP + SPF/DKIM/DMARC.
5. Cadastro com e-mail real, confirmação e login.
6. Teste de expiração e reenvio do link.
7. Teste de recuperação de senha.
8. Teste de um agente com provider real.
9. Revisão dos Termos/Política de Privacidade por responsável jurídico antes de escala comercial.
