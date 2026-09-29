# EquipeAI 3.0 — Visual, cadastro e segurança

## Cadastro e identidade
- Cadastro redesenhado com experiência empresarial, validação em tempo real e confirmação de senha.
- Política de senha: 10+ caracteres, maiúscula, minúscula, número e símbolo.
- Aceite explícito de Termos de Uso e Política de Privacidade.
- Contas novas ficam pendentes até confirmação do e-mail.
- Página de “verifique sua caixa de entrada”, reenvio com cooldown e página de confirmação.
- E-mail HTML responsivo de confirmação com CTA, expiração e fallback de link.
- Token de verificação de uso único, expirável e armazenado apenas como hash.

## Segurança
- Rate limit dedicado para verificação e reenvio.
- Mensagem genérica no reenvio para reduzir enumeração de contas.
- Limite de sessões simultâneas antigas.
- Sessões invalidadas após troca/reset de senha.
- Headers adicionais: HSTS, CSP no frontend, COOP, Referrer-Policy e Permissions-Policy.
- CSP e `no-referrer` reduzem risco de vazamento do token de confirmação.
- Validação e normalização reforçadas de nome, empresa, e-mail e senha.
- Auditoria dos eventos de verificação.

## Design
- Nova direção visual autoral: carvão, branco/off-white e cobalto funcional.
- Remoção do visual neon/gradiente e dos clichês de “site de IA”.
- Pesos tipográficos leves/moderados; sem títulos em bold pesado.
- Nova marca geométrica, navegação lateral, topbar, landing, dashboard, agentes e autenticação.
- Cards com borda precisa, pouca sombra, hierarquia editorial e maior densidade informacional.
- Ícones `react-icons` já presentes no projeto substituem emojis decorativos.
- Camada visual do Bootstrap deixou de ser importada; os estilos do produto são próprios.
- Experiência responsiva revisada para desktop, tablet e celular.
