# EquipeAI v3.4 — Campaign Editor

## Marketing

A saída do agente de Marketing foi redesenhada por completo. A grade de cards da v3.3 foi removida e substituída por um **Editor de Campanha**.

### Nova experiência
- cabeçalho da campanha com metadados do briefing;
- briefing recolhido por padrão e aberto em drawer somente quando necessário;
- tabs por formato: Visão geral, Carrossel, Stories, Reels, Publicação;
- visão geral em lista editorial, sem cards grandes;
- Carrossel e Stories usam navegação sequencial horizontal;
- Reels usa timeline de cenas;
- conteúdo selecionado é mostrado em uma folha editorial ampla;
- legenda, CTA e hashtags ficam em propriedades separadas abaixo do conteúdo;
- painel Visual fixo no desktop e abaixo do conteúdo no mobile;
- geração de imagens por item selecionado (card, story ou cena), não pela campanha inteira;
- escolha de Story 9:16 ou Feed 4:5;
- escolha de estilo visual;
- versões de imagem com v1, v2, v3...;
- download da versão selecionada;
- edição manual do texto bruto continua disponível.

## Parser de Marketing

O parser não trata mais `Story 1`, `Story 2` etc. como conteúdos independentes. Esses itens passam a ser blocos internos da seção Stories. Isso corrige a fragmentação observada na v3.3.

## Cloudflare Workers AI

- FLUX.1 Schnell continua como modelo principal configurável.
- SDXL Lightning foi adicionado como fallback configurável.
- O fallback suporta dimensões reais para Story 9:16 e Feed 4:5.
- A integração agora aceita tanto respostas JSON/Base64 quanto respostas binárias de imagem.
- Tratamento específico dos erros Cloudflare:
  - `3036`: cota diária gratuita esgotada;
  - `3040`: capacidade temporariamente indisponível;
  - `5035`: modelo exige Workers Paid;
  - `5007`: modelo inválido/indisponível.
- Em falha, os créditos internos continuam sendo devolvidos.

## Banco de dados

Nenhuma migration é necessária da v3.3 para a v3.4.
