# EquipeAI v3.5 — Visual Relevance Engine

## Problema corrigido
Na v3.4 o prompt enviado ao modelo de imagem misturava o conteúdo selecionado com contexto empresarial amplo. Em modelos de difusão isso podia diluir o assunto principal e produzir imagens genéricas ou pouco relacionadas ao post.

## Novo fluxo
1. O usuário seleciona exatamente o card/story/cena que deseja ilustrar.
2. Um novo `VisualPromptService` transforma esse trecho em uma direção visual concreta e curta.
3. O planner preserva fatos importantes: serviço, público/idade, ação, ambiente, formato e estilo.
4. O prompt final recebe um `RELEVANCE LOCK` com o conteúdo selecionado para impedir deriva para imagens genéricas.
5. Somente depois o prompt visual é enviado ao Cloudflare Workers AI.

## Controle do usuário
O painel Visual agora possui o campo **Direção visual**.
- vazio: a aplicação cria a direção automaticamente;
- após a primeira geração: a direção usada aparece no campo;
- o usuário pode ajustar a direção e gerar outra versão.

## Fallback seguro
Se o planner textual falhar, a aplicação usa um prompt determinístico, curto e focado no trecho selecionado, sem despejar todo o contexto da empresa no gerador de imagem.

## Configuração
```env
IMAGE_PROMPT_ENHANCER_ENABLED=true
IMAGE_PROMPT_MAX_TOKENS=320
```

O planner utiliza o mesmo provedor/modelo textual configurado para o agente de Marketing. A chamada é curta e ocorre somente ao pedir uma imagem.

## Banco
Nenhuma migration necessária.
