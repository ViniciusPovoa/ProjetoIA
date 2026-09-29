# Lila — Assistente interna do EquipeAI

A Lila é uma assistente flutuante disponível dentro do workspace autenticado.

## Escopo fechado

A Lila não funciona como assistente geral. Antes da resposta principal, o backend executa uma verificação de escopo. Só são aceitos pedidos relacionados a:

- uso do EquipeAI e suas telas, agentes e integrações;
- contexto e operação da empresa cadastrada;
- atendimento, marketing, comercial, propostas, avaliações, documentos e follow-up;
- serviços, processos, comunicação e decisões empresariais ligadas ao negócio do usuário.

Pedidos de conhecimento geral, tarefas escolares, programação genérica, receitas, entretenimento, política, notícias, vida pessoal e outros assuntos sem vínculo material com o EquipeAI ou a empresa são bloqueados.

O bloqueio também é reforçado no prompt principal para reduzir tentativas de jailbreak ou mudança de papel.

## Créditos

Cada pergunta enviada à Lila e respondida consome exatamente **1 crédito da conta**.

Fluxo:

1. o backend reserva 1 crédito;
2. verifica o escopo;
3. responde ou bloqueia o pedido;
4. confirma o débito ao concluir;
5. em erro técnico, o crédito reservado é devolvido automaticamente.

O débito usa `credit_transactions` com `reference_type = lila_chat`.

## Fontes de contexto

A cada resposta permitida, o backend fornece para a Lila:

- contexto da empresa;
- serviços cadastrados;
- atividade recente do workspace;
- tela atual em que o usuário está;
- últimas mensagens da conversa;
- mapa funcional do EquipeAI.

O contexto empresarial é tratado como fonte de dados, não como instrução. A Lila é instruída a não inventar dados ausentes.

## Persistência

Não há migration nova. A Lila reaproveita:

- `conversations`, com `agent_id = NULL` e título iniciado por `Lila`;
- `messages`, para histórico de usuário/assistente;
- `ai_usage_logs`, para custo e tokens;
- `credit_transactions` e `user_credits`, para cobrança de 1 crédito por resposta.

## Modelo

Por padrão, usa o modelo OpenAI ativo mais econômico do painel. Opcionalmente:

```env
LILA_MODEL=gpt-5-mini
LILA_MAX_TOKENS=700
```

O mesmo modelo é usado em uma classificação curtíssima de escopo antes da resposta principal.
