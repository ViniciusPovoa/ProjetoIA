# Upgrade 3.0 → 3.1

Este upgrade não altera o schema do banco.

1. Faça backup da versão atual.
2. Substitua os arquivos do backend pelos arquivos da v3.1, preservando o `.env` de produção.
3. Gere novamente o frontend com `npm install` e `npm run build`.
4. Suba o conteúdo de `frontend/dist/` no domínio do frontend.
5. Garanta que `OPENAI_API_KEY` continue configurada no backend. O recurso “Melhorar com IA” usa o primeiro modelo OpenAI ativo configurado no banco.
6. Teste `/api/health`; deve retornar `version: 3.1.0`.
7. Teste a tela Minha empresa e um refinamento de contexto.

O refinamento é deliberadamente curto: máximo de 110 tokens de saída, até duas frases e 320 caracteres.
