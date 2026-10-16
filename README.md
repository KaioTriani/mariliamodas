# Marília Modas — Hostinger

Versão **PHP 8.3+ + MySQL/MariaDB**, pronta para importar como site PHP/HTML na hospedagem comum da Hostinger. Não requer Node.js, npm, Composer ou etapa de build. Inclui catálogo, filtros, favoritos, sacola, checkout, cálculo de frete, integração Mercado Pago e newsletter Resend.

## 1. Importar o repositório

No hPanel, escolha um site **PHP/HTML**. Abra **Avançado → Git**, conecte o GitHub e selecione:

| Campo | Valor |
|---|---|
| Repositório | `KaioTriani/mariliamodas` |
| Branch | `main` |
| Diretório | `public_html` |
| Build | Nenhum |
| PHP | 8.3 ou superior |

Também é possível usar **Importar site → Implantar do GitHub**, quando disponível no plano. Não selecionar Node.js ou Criador de Sites. Se o diretório já contém outro site, faça backup e use um site/domínio vazio para esta importação.

A estrutura tem `index.php` e `.htaccess` na raiz. **Não mover somente a pasta public**. O catálogo, as fotos e os botões de WhatsApp funcionam logo após importar. HTTPS deve estar ativo no domínio.

## 2. Criar o banco e configurar a loja

1. No hPanel, crie um banco MySQL e um usuário com acesso a ele.
2. Abra esse banco no phpMyAdmin e importe `database/schema.sql`.
3. No Gerenciador de Arquivos, copie `config/local.example.php` para `config/local.php`.
4. Preencha `SITE_URL` com o domínio HTTPS exato, sem barra final, e `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` com os dados mostrados pela Hostinger.
5. Confirme que as extensões PHP `pdo_mysql`, `curl`, `json` e `openssl` estão habilitadas.

`local.php` é ignorado pelo Git e protegido pelo `.htaccess`. Nunca publique senhas ou tokens no repositório. Guarde uma cópia segura desse arquivo antes de reimplantar. Sem banco configurado, o catálogo continua disponível, mas o cadastro de e-mail informa indisponibilidade, sem simular gravação.

## 3. Pagamentos e frete

Em `config/local.php`, preencha `MERCADO_PAGO_ACCESS_TOKEN`, `MELHOR_ENVIO_TOKEN` e `SHIPPING_CONTACT_EMAIL`. Em `config/commerce.json`, informe o CEP real de origem e o prazo de preparação. Em `config/catalog.json`, confirme `priceCents` (centavos inteiros), `stock`, `availableSizes` (roupas) e `package` (`width`, `height`, `length` em cm e `weight` em kg) de cada produto.

Depois de validar os dados, altere `verified` para `true` em `config/commerce.json` e `PAYMENTS_ENABLED` para `true` em `config/local.php`. Os endpoints de pagamento e frete usam produção. Teste nas contas dos provedores antes da abertura da loja; o projeto não inclui credenciais, estoque, preços ou frete inventados.

O checkout valida valores no servidor, guarda a cotação por 15 minutos e redireciona ao Mercado Pago. A página de retorno consulta o pagamento no servidor e confere referência, moeda e valor. Sem as credenciais/dados, a compra segue pelo WhatsApp. Não há emissão automática de etiquetas nem webhook de expedição. Acompanhe os pedidos no painel Mercado Pago. `stock` limita a quantidade por pedido; não substitui um ERP ou reserva concorrente de estoque.

## 4. Newsletter automática

Após configurar o banco, o formulário salva inscrições e consentimento. Para enviar, configure `RESEND_API_KEY` e `NEWSLETTER_FROM` (remetente em domínio verificado no Resend) e habilite `NEWSLETTER_ENABLED`.

Em **Avançado → Cron Jobs**, adicione uma tarefa PHP a cada cinco minutos para o arquivo:

```text
/home/SEU_USUARIO/domains/SEU_DOMINIO/public_html/scripts/newsletter.php
```

Use o caminho real exibido no Gerenciador de Arquivos e o executável PHP oferecido pelo painel; o agendamento é `*/5 * * * *`. Se o painel solicitar comando completo, use o caminho do PHP do seu plano seguido do caminho absoluto acima.

Cada mudança em `config/catalog.json` é detectada pela tarefa. Ela envia lotes de até dez e-mails, registra o envio e evita duplicatas com idempotência. Não depende de visitas à loja. Novos inscritos recebem mudanças posteriores à inscrição. Os e-mails têm cancelamento; inscritos cancelados não são reativados automaticamente por outro visitante. Tentativas ambíguas com mais de 23 horas ficam em `deliveries.status = review` para conferência no Resend antes de qualquer reenvio.

## Manutenção e validação

- Produtos: editar `config/catalog.json`. O catálogo JavaScript é gerado pelo PHP, sem build.
- Fotos: colocar em `public/assets` e referenciar no catálogo.
- Configurações comerciais: `config/commerce.json`.
- Segredos: `config/local.php`, apenas na hospedagem.
- Testes: `php tests/commerce.php`.
- Diagnóstico de extensões, conexão e tabelas (via SSH): `php scripts/diagnose.php`.
- Prévia local: `php -S 127.0.0.1:4180 server/router.php`.

Antes de divulgar, valide no domínio final: inscrição de teste no banco, cotação de frete, pagamento nas contas da loja e cancelamento do e-mail. A conexão MySQL da sua conta e os provedores precisam das credenciais reais; esses testes não são substituídos pelos testes locais de código. Solicitações de exclusão de dados devem ser atendidas removendo os registros correspondentes no banco. Mantenha backups do banco pelo hPanel.

## Referências oficiais

- [Deploy Git na Hostinger](https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/)
- [Configuração de PHP](https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/)
- [Mercado Pago](https://www.mercadopago.com.br/developers/pt/reference/online-payments/checkout-pro-preferences/overview)
- [Melhor Envio](https://docs.melhorenvio.com.br/reference/calculo-de-fretes-por-produtos)
- [Resend](https://resend.com/docs/api-reference/emails/send-email)

