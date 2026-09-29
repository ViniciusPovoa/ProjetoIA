# EquipeAI v3.9.1 custom — Lila Guard + Créditos

## Escopo restrito

- Lila agora é explicitamente uma assistente interna do EquipeAI, não uma IA de uso geral.
- Novo firewall de escopo executado no backend antes da resposta principal.
- Permite apenas dúvidas sobre o EquipeAI ou assuntos materialmente ligados à operação da empresa.
- Bloqueia tarefas gerais, conteúdo escolar, programação genérica, entretenimento, notícias, vida pessoal e outros pedidos fora do escopo.
- Prompt principal reforçado contra jailbreak e tentativas de ampliar o papel da Lila.
- Quando um entregável já possui agente especializado, a Lila orienta o usuário para a ferramenta correta em vez de substituir o agente.

## Créditos

- Cada pergunta/resposta da Lila custa exatamente 1 crédito.
- O crédito é reservado antes do processamento e confirmado ao concluir.
- Erros técnicos devolvem o crédito automaticamente.
- Pedidos fora do escopo também retornam uma resposta de bloqueio e consomem 1 crédito, mantendo a regra de uma pergunta/resposta = um crédito.
- Uso continua registrado em `ai_usage_logs`.
- Não exige migration nova.

## Interface

- Chat informa claramente “1 crédito por resposta”.
- Tela inicial explica que a Lila atende somente EquipeAI e assuntos da empresa.
