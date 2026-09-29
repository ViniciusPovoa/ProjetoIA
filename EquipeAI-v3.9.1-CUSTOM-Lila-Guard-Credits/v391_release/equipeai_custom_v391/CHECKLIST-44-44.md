# Checklist do TXT — 44/44 no código do MVP

> **Escopo implementado** significa que a funcionalidade existe no código e está conectada ao banco/API/interface quando aplicável. Integrações externas (IA, SMTP e Mercado Pago) ainda precisam das suas credenciais e de teste real no ambiente onde o produto será publicado.

| # | Requisito do TXT | Status | Implementação |
|---:|---|---|---|
| 1 | Objetivo do produto | ✅ | SaaS de agentes especializados para tarefas empresariais, sem exigir que cliente escolha GPT/Claude/Gemini. |
| 2 | Tecnologias | ✅ | React + TS + Vite + Bootstrap/Axios/Router/icons; PHP 8.2+ puro + PDO/MySQL/cURL. |
| 3 | Arquitetura | ✅ | Frontend/backend separados; controllers/services/repositories/middleware/providers/contracts/utils. |
| 4 | Sistema de agentes | ✅ | `AgentInterface`, `GenericAgent`, `AgentService`, schema/configuração no banco, execução modular. |
| 5 | Agente de Atendimento | ✅ | Campos, tom, contexto da empresa, gerar/regenerar/editar/copiar. |
| 6 | Agente de Marketing | ✅ | Tema, objetivo, público, plataforma, tom e adicionais via schema dinâmico. |
| 7 | Agente Comercial | ✅ | Propostas, orçamentos, lead, script e objeções; valor/prazo/contexto. |
| 8 | Agente de Avaliações | ✅ | Positiva/neutra/negativa e prompt reputacional não agressivo. |
| 9 | Agente de Documentos | ✅ | PDF/DOCX/TXT/CSV; validação; extração local; PDF nativo via Gemini quando necessário. |
| 10 | Agente Administrativo | ✅ | E-mail, comunicado, relatório, reunião e organização de informações. |
| 11 | Agente de Follow-up | ✅ | Lead, serviço, última conversa, tempo decorrido e objetivo. |
| 12 | Perfil da Empresa | ✅ | Cadastro completo + CRUD de serviços/preços/duração/observações; contexto automático. |
| 13 | Personalidade da Empresa | ✅ | Tom, palavras a evitar/usar, regras e informações adicionais. |
| 14 | Sistema Multi-IA | ✅ | `AIProviderInterface` + OpenAI/Anthropic/Gemini/DeepSeek separados. |
| 15 | Roteamento Inteligente | ✅ | Agente → provider → modelo no banco e editável pelo admin sem alterar código. |
| 16 | Sistema de Créditos | ✅ | Planos, saldo, custo por agente e bloqueio de saldo insuficiente. |
| 17 | Controle de Custos | ✅ | Reserva → IA → confirmação; erro → refund; transações MySQL/ledger. |
| 18 | Histórico | ✅ | Entrada/saída/data/agente/modelo/créditos; copiar, excluir, reutilizar, favoritar; paginação. |
| 19 | Autenticação | ✅ | Cadastro/login/logout/reset/troca; `password_hash/verify`; sessão segura. |
| 20 | Segurança da API | ✅ | Auth em rotas privadas, usuário derivado da sessão, ownership e admin guard. |
| 21 | Rate Limiting | ✅ | IP/usuário/endpoint para cadastro/login/reset/IA/upload. |
| 22 | Banco de Dados | ✅ | Tabelas solicitadas + sessões/reset/rate limit/webhook/custos/auditoria; FKs/índices/constraints. |
| 23 | Pagamento | ✅ | Assinatura recorrente Mercado Pago, webhook, verificação, idempotência, logs e crédito após aprovação. |
| 24 | Dashboard do Cliente | ✅ | Ações rápidas, créditos, uso, atividades e plano atual. |
| 25 | Design | ✅ | Design system branco/cinza/roxo, sem gradientes, cards/estados/tabelas/formulários responsivos. |
| 26 | Menu | ✅ | Dashboard, submenu dinâmico de agentes, histórico, empresa, arquivos, créditos, assinatura, configurações e admin. |
| 27 | Administrador | ✅ | Usuários, agentes, providers/modelos, planos, créditos, custos, logs, bloqueio/exclusão/troca de plano. |
| 28 | Monitoramento Financeiro | ✅ | Receita, custo IA, infraestrutura, outros custos, margem e margem %. |
| 29 | Prompts dos Agentes | ✅ | Prompts armazenados no banco, variáveis e blocos estruturados. |
| 30 | Contexto da Empresa | ✅ | `CompanyContextService`: empresa + serviços + tom + regras + adicionais. |
| 31 | Proteção contra Prompt Injection | ✅ | SYSTEM/COMPANY/USER/DOCUMENT separados e instruções explícitas de prioridade/segurança. |
| 32 | Uploads | ✅ | Tamanho/MIME/extensão/nome aleatório/storage fora do público/autorização/exclusão. |
| 33 | Privacidade | ✅ | Dados relacionados a usuário/empresa; excluir histórico/arquivos/conta. |
| 34 | Landing Page | ✅ | Hero, proposta comercial, agentes, contexto empresarial, segurança e CTA. |
| 35 | Responsividade | ✅ | Desktop/notebook/tablet/mobile com breakpoints e navegação mobile dedicada. |
| 36 | Performance | ✅ | Lazy loading de páginas, paginação, cache curto de configuração, índices e limites de upload. |
| 37 | Custo de Infraestrutura | ✅ | Backend PHP/cPanel; inferência feita pelas APIs externas; sem modelo local/GPU. |
| 38 | Configuração | ✅ | `.env.example` com DB/providers/Mercado Pago/URLs/SMTP; segredos ignorados pelo Git. |
| 39 | Tratamento de Erros | ✅ | Erro interno oculto em produção, logging estruturado e refund de crédito em falha de IA. |
| 40 | Logs | ✅ | IA, pagamentos, segurança, erros, autenticação, auditoria, webhook e manutenção. |
| 41 | API | ✅ | Rotas do TXT + rotas complementares para sessão, admin, arquivos, assinatura e segurança. |
| 42 | MVP em Fases | ✅ | O pacote final incorpora Fases 1–6; arquitetura permite ativar integrações progressivamente. |
| 43 | Regra Fundamental | ✅ | Usuário escolhe o que quer fazer; provider/modelo ficam ocultos e controlados pelo sistema/admin. |
| 44 | Resultado Final | ✅ | Frontend + backend + banco conectados, modular, cPanel-ready, multiempresa, créditos, admin, histórico e pagamentos. |

## Validação técnica executável

Use:

```bash
./scripts/verify.sh
```

Além disso, siga `docs/TESTING.md` para os testes ponta a ponta. Chamadas reais de IA, envio SMTP, checkout e webhook Mercado Pago **não podem ser validados sem suas credenciais e um domínio/ambiente real**, por isso são parte do checklist de staging antes da abertura das vendas.
