# EquipeAI 3.1 — Navegação, legibilidade e qualidade de contexto

## Sidebar
- Sidebar desktop agora pode ser recolhida para modo compacto e expandida novamente.
- Preferência de sidebar é persistida no navegador.
- “Ferramentas” e “Gestão” são grupos recolhíveis; não ficam mais abertos por padrão no dashboard.
- O grupo da rota ativa é aberto automaticamente quando necessário.
- Modo compacto mantém somente os ícones e preserva navegação rápida.
- Navegação mobile mantém comportamento próprio e não herda o modo compacto desktop.

## Legibilidade
- Aumentados títulos, subtítulos, labels, cards, histórico, tabelas, topbar e itens de navegação.
- Mantidos pesos tipográficos leves/moderados, sem retornar a títulos pesados em bold.
- Formulários e placeholders receberam escala mais confortável para leitura.

## Contexto empresarial
- Tooltips explicativos em todos os campos da empresa.
- Validação visual de qualidade nos campos contextuais em quatro níveis: Muito básico, Básico, Bom e Excelente.
- Score geral do contexto de 0 a 100, levando em conta preenchimento e especificidade estimada.
- Indicadores locais reagem enquanto o usuário digita, sem chamada de IA.
- Validação adicional de e-mail, telefone, site, Instagram, limites e tom de comunicação no frontend e backend.
- Campos de contexto muito básicos oferecem “Melhorar com IA”.
- Refinamento usa uma chamada curta, limitada a 110 tokens de saída e no máximo 2 frases/320 caracteres.
- Prompt de refinamento proíbe inventar fatos, preços, serviços ou características não fornecidas.
- Refinamento possui rate limit de 8 chamadas a cada 5 minutos por usuário.
- Uso do refinamento é registrado em `ai_usage_logs` e nos logs de IA, sem descontar créditos do cliente.

## API
- Novo endpoint autenticado: `POST /api/company/context/improve`.
- Versão da API atualizada para 3.1.0.
- Nenhuma migration adicional de banco é necessária para este upgrade.
