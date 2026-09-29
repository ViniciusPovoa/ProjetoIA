# Proposal Studio + WhatsApp/PDF - merge sobre sua v3.5 customizada

Este pacote foi gerado usando **o ZIP customizado enviado por você como base**. As alterações locais existentes foram preservadas; apenas a funcionalidade de propostas foi integrada.

## Banco
Importe:

`database/migrations/2026_09_29_proposal_studio.sql`

Ela cria `proposal_documents` e `proposal_deliveries`.

## E-mail
O envio do PDF por e-mail reutiliza o SMTP existente do EquipeAI.

## WhatsApp Cloud API
Adicione ao **seu `.env` atual** (ele não foi sobrescrito pelo merge):

```env
WHATSAPP_CLOUD_ACCESS_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_GRAPH_VERSION=v26.0
WHATSAPP_PROPOSAL_TEMPLATE_NAME=
WHATSAPP_PROPOSAL_TEMPLATE_LANGUAGE=pt_BR
```

O template é opcional e serve para cenários em que a Meta exige template aprovado fora da janela de atendimento.

## APP_URL
O `APP_URL` precisa apontar para a URL HTTPS pública do backend, pois o WhatsApp precisa buscar o PDF pela internet.

## Frontend
O componente `ProposalStudio.tsx` é aberto automaticamente quando `Comercial -> O que deseja criar? -> Proposta` termina a geração.

## Importante
O arquivo real `backend/.env` do ZIP enviado foi preservado exatamente como estava. Não compartilhe o pacote publicamente se ele contiver credenciais reais.
