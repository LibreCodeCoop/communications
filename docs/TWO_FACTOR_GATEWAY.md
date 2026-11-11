<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
# Two-Factor Gateway: envio opcional e consentimento

Referência: Comunicações 0.28.0, Nextcloud 36. Implementação local de laboratório.

**Estado do laboratório:** Two-Factor Gateway 6.0.0-dev.0 (upstream main@95e1c10fb33ebff77671746c42f6be2646a25323), instalado em montagem somente leitura. As seis classes de provedores e o serviço GatewayDirectIntegrationService foram resolvidos diretamente pelo Nextcloud. Nenhum gateway direto foi configurado; a disponibilidade retornada é *Não configurado*. Não houve tentativas de contato com provedores externos nem mensagens reais. O modo de manutenção está desativado e a autenticação das contas de teste continua operacional.

## Contrato

Assim como o LibreSign, o Comunicações utiliza OCA\TwoFactorGateway\Service\GatewayDirectIntegrationService e os métodos ensureAvailable(gatewayId), isGatewayComplete(gatewayId) e send(gatewayId, identifier, message).

São suportados SMS, Signal, Telegram, WhatsApp via gowhatsapp, WhatsApp Business via whatsappbusiness e XMPP.

Não chamamos diretamente endpoints de terceiros nem reutilizamos códigos OTP. O aplicativo e o gateway direto precisam estar configurados em Administração → Segurança. A UI distingue aplicativo ausente de provedor não configurado. Configurações por grupo sem fallback direto não habilitam este adaptador.

## Habilitação e consentimento

1. O administrador instala Two-Factor Gateway para Nextcloud 36 e configura seu provedor direto.
2. Cada destinatário abre Comunicações → Caixa de entrada → Preferências de avisos, seleciona o canal, informa seu destino e salva. A autorização para conteúdo patrocinado é separada e desmarcada por padrão.
3. Um editor abre Publicações → Nova publicação → Formas de entrega → Mensageiros e SMS (Two-Factor Gateway), escolhe provedores configurados e ativa a publicação.
4. O aviso referencia os detalhes autorizados da mesma publicação. A cópia na Caixa de entrada é opcional desde a versão 0.27.0. Banners anônimos não usam mensagens externas.

Dados pessoais cadastrados para autenticação em dois fatores NÃO equivalem a consentimento para mensagens. O destino informado é armazenado numa preferência independente, disponível apenas ao próprio usuário. Revogar e salvar apaga o identificador. Canais estão desativados por padrão.

Aceitamos telefone E.164 para SMS/Signal/WhatsApp, chat privado numérico do bot de Telegram ou JID XMPP. O usuário precisa ter iniciado conversa com o bot quando exigido. WhatsApp Business depende das condições do seu provedor, políticas e modelos aprovados.

## Envio e limites

O envio usa BoardBroadcastJob e BoardPublicationService, em lotes de até 100 usuários, com outbox por publicação, ativação, destinatário e canal, como gateway:telegram. A cada tentativa são conferidos público, consentimento, preferência sobre conteúdo patrocinado, destino e configuração do gateway. Erros externos são registrados por classe, sem conteúdo nem identificadores pessoais no outbox ou logs. A mensagem contém título localizado, texto e link à publicação autorizada.

A fila evita reenvios normais, mas não garante exatamente uma entrega externa se o provedor aceitar uma mensagem e a resposta do job se perder. Reativar uma publicação pode iniciar nova rodada. Sucesso na API não prova entrega ao destinatário final.

Um transporte real requer credenciais de serviço e destino de laboratório autorizado. Sem gateway configurado, a interface mostra indisponibilidade. Mesmo instalado, nenhum canal aparece como pronto até que a configuração real seja validada. Testes controlados devem utilizar um serviço falso, sem disparar mensagens reais.

## Testes

- python3 test_gateway_integration_v26.py: catálogo, contrato, preferências, consentimento, revogação e fila simulada.
- node test_gateway_ui_v26.cjs: editor, preferências, estados de disponibilidade e responsividade.
- Regressões de publicações, Caixa de entrada e paridade anteriores.
