# Validação — EquipeAI v3.9.1 custom

- Backend: 76 arquivos PHP validados com `php -l` sem erro.
- Frontend: 29 arquivos TypeScript/TSX validados com `tsc --noEmit` sem erro.
- `/api/health`: HTTP 200, versão `3.9.1-custom`.
- Lila: cobrança implementada via `CreditService` com reserva de 1 crédito, confirmação no sucesso e reembolso em falha técnica.
- Lila: firewall de escopo obrigatório antes da resposta principal.
- Sem migration nova.
- Pacote distribuível não inclui `backend/.env` nem `node_modules`.

Observação: o teste funcional completo das respostas da Lila depende das credenciais OpenAI e do banco configurado no ambiente de produção.
