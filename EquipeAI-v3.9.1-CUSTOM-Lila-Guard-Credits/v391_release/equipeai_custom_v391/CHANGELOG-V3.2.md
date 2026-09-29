# EquipeAI 3.2 — UX 2026 + Context Intelligence

## Direção de produto

A versão 3.2 deixa o painel com aparência de dashboard genérico e passa a tratar o produto como um workspace operacional. A interface prioriza a tarefa do usuário, mantém a IA em segundo plano e torna contexto, custo, estado e controle mais visíveis.

## Workspace e design

- Dashboard reconstruído como workbench com duas tarefas principais e ações secundárias compactas.
- Atividade recente virou uma linha do tempo/tabela operacional mais densa, reduzindo espaço vazio.
- Plano, créditos e qualidade do contexto foram agrupados em um painel de pulso do workspace.
- Nova hierarquia tipográfica, maior contraste e escala de texto mais confortável.
- Novo sistema de superfícies, bordas, sombras, estados de hover/focus e densidade.
- Busca global `Ctrl/Cmd + K` para navegar entre ferramentas e áreas.
- Sidebar, topbar, formulários, tabelas, agentes, histórico, configurações e admin passam a compartilhar o mesmo padrão visual.
- Biblioteca de ferramentas ganhou busca local e cards com hierarquia mais clara.
- Executor de agentes ganhou briefing explícito, sinalização de contexto empresarial, custo, estado de geração e resultado com ações claras.

## Contexto empresarial

- A avaliação de qualidade agora é específica por campo; não depende apenas do comprimento do texto.
- Segmento, descrição, horário, público-alvo, diferenciais, regras e vocabulário usam critérios próprios.
- Campos opcionais vazios deixam de derrubar a nota geral.
- A meta mínima para um campo essencial ser considerado pronto é 82/100.
- `Melhorar com IA` usa também o contexto já cadastrado e o catálogo de serviços como fontes confiáveis.
- A melhoria faz até duas passagens curtas quando necessário e mira 88/100 ou mais, exigindo ganho real de qualidade antes de aplicar automaticamente.
- Uma melhoria só é aplicada automaticamente quando atinge o mínimo de qualidade e representa um ganho real sobre o texto anterior.
- Se faltarem fatos reais para atingir a meta, o sistema não inventa. Ele explica quais detalhes precisam ser adicionados.
- Depois de uma melhoria aplicada, o usuário pode desfazer imediatamente.
- Uso da IA de contexto continua registrado em `ai_usage_logs` e nos logs de requisição.

## Banco de dados

Nenhuma migration é necessária para atualizar da 3.1 para a 3.2.
