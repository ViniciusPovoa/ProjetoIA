# Upgrade rápido para a versão 3.0

## Backend
1. Faça backup do banco e dos arquivos atuais.
2. Substitua o backend pelos arquivos da versão 3.0.
3. Se já houver banco em produção, rode `database/migrations/2026_09_28_email_verification_security.sql`.
4. Complete as variáveis SMTP e de verificação no `.env`.
5. Mantenha o `.env` fora de qualquer repositório público.

## Frontend
1. Na pasta `frontend`, execute `npm install`.
2. Execute `npm run build`.
3. Envie **o conteúdo** de `frontend/dist/` para a raiz de `ia.vdeveloper.com.br`.
4. Confirme que `.htaccess` foi enviado junto.

## URLs de produção esperadas
- Frontend: `https://ia.vdeveloper.com.br`
- API: `https://api-ia.vpdeveloper.com.br`
- Link do e-mail: `https://ia.vdeveloper.com.br/verificar-email?token=...`

## Checklist pós-deploy
- `/api/health` responde com versão `3.0.0`.
- cadastro rejeita e-mail inválido, senha fraca e termos não aceitos;
- cadastro válido recebe e-mail;
- login antes da confirmação é recusado;
- confirmação ativa a conta;
- reenvio funciona sem revelar se outro e-mail existe;
- rotas internas da SPA não retornam 404 após refresh;
- API não exibe stack trace em produção.
