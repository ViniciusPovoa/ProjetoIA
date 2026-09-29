# Upgrade 3.1 → 3.2

1. Faça backup dos arquivos publicados e do banco.
2. Preserve o `.env` de produção.
3. Substitua os arquivos do backend pelos da 3.2.
4. Não rode novamente `schema.sql` e não há migration adicional nesta versão.
5. No frontend, rode `npm install` e `npm run build`.
6. Publique o conteúdo de `frontend/dist/` no domínio do frontend.
7. Teste `GET /api/health`; a resposta deve mostrar `version: 3.2.0`.
8. Entre em **Minha empresa**, teste a avaliação de contexto e o botão **Melhorar com IA**.
9. Verifique a busca global com `Ctrl/Cmd + K` e as principais ferramentas.
