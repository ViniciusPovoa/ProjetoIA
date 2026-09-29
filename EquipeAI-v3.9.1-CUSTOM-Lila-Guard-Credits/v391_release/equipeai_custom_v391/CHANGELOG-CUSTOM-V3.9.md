# EquipeAI v3.9 custom — Lila

## Lila, assistente do workspace

- Botão flutuante no canto inferior direito em todas as telas autenticadas.
- Chat em painel lateral/flutuante com experiência responsiva.
- Usa o contexto empresarial e serviços cadastrados para responder dúvidas da empresa.
- Entende a tela atual do EquipeAI para explicar o caminho mais útil dentro da aplicação.
- Conhece as áreas atuais do produto: Atendimento, Marketing, Comercial/Proposal Studio, Avaliações, Documentos, Follow-up, contexto empresarial, Instagram, WhatsApp, arquivos, créditos e assinatura.
- Histórico persistente reaproveitando as tabelas `conversations` e `messages` já existentes. Não exige migration.
- Botão “Nova conversa”.
- Sugestões iniciais para dúvidas comuns.
- Respostas curtas e orientadas a ação, sem inventar informações da empresa.
- Uso registrado em `ai_usage_logs` para acompanhamento de custo.

## API

- `GET /assistant/lila`
- `POST /assistant/lila/chat`
- `POST /assistant/lila/new`

## Configuração opcional

```env
LILA_MODEL=
LILA_MAX_TOKENS=700
```

Se `LILA_MODEL` estiver vazio, a Lila escolhe o modelo OpenAI ativo de menor custo cadastrado no banco.
