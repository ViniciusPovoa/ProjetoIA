# Roteiro de testes do MVP

Execute em ambiente de staging antes de produção.

## Teste automatizado/local

```bash
./scripts/verify.sh
```

## 1. Autenticação

- cadastrar conta;
- confirmar que recebe 20 créditos do plano Grátis;
- logout e login;
- testar senha errada e rate limit;
- solicitar recuperação de senha e usar o link recebido;
- trocar senha logado;
- confirmar que rotas privadas sem sessão retornam 401.

## 2. Isolamento

Crie duas contas A e B. Gere histórico/arquivo/serviço na A e tente acessar os IDs diretamente autenticado na B. A API nunca deve retornar dados da A.

## 3. Empresa

- preencher todos os campos;
- alterar tom, palavras a evitar/usar e regras;
- criar/editar/excluir serviços;
- gerar uma resposta e verificar se informações reais da empresa entram no contexto.

## 4. Agentes e créditos

Para cada agente:

- preencher campos obrigatórios;
- executar;
- verificar `agent_runs`, histórico e consumo;
- copiar/regenerar/reutilizar/favoritar/excluir histórico.

Force uma chave inválida/provedor indisponível e confirme que o saldo é devolvido.

## 5. Documentos

- TXT;
- CSV;
- DOCX (com extensão PHP `zip`);
- PDF textual;
- PDF com imagem/tabela usando o Agente de Documentos configurado em Gemini.

Teste MIME inválido, extensão falsa e arquivo acima do limite.

## 6. Admin

- visualizar métricas;
- bloquear/desbloquear usuário;
- mudar plano;
- ajustar créditos;
- criar/editar/desativar agente;
- mudar provider/modelo;
- atualizar custo por token;
- criar/editar plano;
- registrar custo de infraestrutura;
- conferir auditoria e logs.

## 7. Mercado Pago

Em staging/credenciais adequadas:

- iniciar assinatura Business;
- concluir checkout;
- confirmar recebimento do webhook;
- confirmar `payments.status=approved`;
- confirmar liberação de créditos uma única vez;
- reenviar o mesmo webhook e confirmar ausência de duplicação;
- testar pagamento rejeitado/pendente;
- cancelar assinatura;
- validar novo ciclo recorrente.

## 8. Financeiro

Depois de uma chamada real de IA e um pagamento:

- informe custos por milhão dos modelos;
- configure `USD_BRL_RATE` se usar preços em USD;
- registre infraestrutura/outros custos;
- confira Receita − IA − Infraestrutura − Outros = Margem.

## 9. Mobile

Testar pelo menos:

- 360×800;
- 390×844;
- tablet;
- notebook;
- desktop grande.

Verifique menu, tabelas, formulários, runner, checkout e admin.

## 10. Produção

Antes de abrir tráfego:

- `APP_DEBUG=false`;
- HTTPS;
- backups;
- cron;
- SMTP real;
- provider real;
- webhook público;
- logs sem segredos;
- teste de restauração de backup.
