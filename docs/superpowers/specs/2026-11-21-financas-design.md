# Finanças — Design do sistema

Data: 2026-11-21 · Autor: Cristhian · Status: rascunho para aprovação

## 1. Intenção e o porquê

Sistema web de controle financeiro pessoal, com dois objetivos:

1. **Uso real pelo autor.** Hoje ele não tem controle nenhum: quase tudo é pago no
   cartão de crédito, o salário vai inteiro para quitar as faturas e há meses em que
   gasta mais do que pode. O problema central **não é velocidade de lançamento, é
   lembrar de atualizar**. O sistema precisa ser intuitivo e "chamar" o usuário.
2. **Peça de portfólio** junto ao currículo. Precisa demonstrar MVC bem feito
   (no padrão Struts-like da Dotplan: XML de mapeamentos, Form, Action, forwards),
   código limpo, segurança e um visual profissional.

Futuro possível (não agora): publicar na Play Store como app gratuito (via PWA/TWA).

## 2. Metas, não-metas e anti-metas

**Metas**
- Cartão de crédito como centro: fechamento, vencimento, faturas e compras parceladas
  que se espalham pelas faturas futuras.
- Previsão do mês: receitas − faturas − gastos fixos = quanto sobra.
- Gastos/receitas fixos (salário, aluguel, assinaturas) lançados automaticamente todo mês.
- Limites por categoria e geral, com aviso antes de estourar.
- Importação da fatura (CSV/OFX) para não depender de digitar compra a compra.
- Relatório e lembretes por **e-mail** (único canal no início).
- Multiusuário desde o primeiro dia (cada usuário só vê os próprios dados).

**Não-metas (por enquanto)**
- WhatsApp, Telegram, push notification.
- Integração direta com bancos (Open Finance).
- Investimentos, metas de poupança, divisão de contas com outras pessoas.
- App nativo.

**Anti-metas (seria fracasso mesmo funcionando)**
- Exigir lançamento diário tedioso — o usuário abandona.
- Visual "Bootstrap genérico de tutorial".
- Brechas de segurança básicas (SQL injection, XSS, CSRF, senha em texto, credenciais no git)
  — recrutadores olham isso.
- Painel com números falsos/de exemplo: estados vazios devem ser honestos e orientar o
  primeiro passo.

## 3. Restrições

- **Stack existente:** PHP 8.2 (XAMPP), Apache, PostgreSQL 18 (instalado localmente),
  Phinx para migrations, Composer, Smarty para templates (`.tpl`).
- **Padrão Struts-like** já iniciado: `phpmvc-config.xml` com `form-beans` e
  `action-mappings`, URL `?do=<módulo>&action=<método>`, Action retorna nome de forward.
  Deve ser mantido — é o que o autor quer mostrar.
- **Custo zero:** sem serviços pagos. Hospedagem futura em servidor gratuito.
- **Credenciais nunca no git.** (A senha antiga do banco vazou em commits da `main`; o
  autor vai trocá-la.)
- **Ambiente local:** a extensão `pdo_pgsql` está desabilitada no `php.ini` do XAMPP e
  precisa ser habilitada (`extension=pdo_pgsql`).
- Fluxo git: trabalho na branch `cristhian`, PR para `main`.

## 4. Abordagens consideradas

**Visual (escolhido pelo autor: A)**

| | Abordagem | Trade-off |
|---|---|---|
| **A** | **Bootstrap 5 + tema próprio** | Profissional rápido, responsivo, componentes prontos; exige personalização para não parecer genérico. **Escolhida.** |
| B | Tailwind CSS | Moderno, mas adiciona Node.js ao projeto PHP. |
| C | CSS próprio | Único, porém muito mais trabalho. |

**Canal de lembretes (escolhido pelo autor: e-mail)**

| Canal | Trade-off |
|---|---|
| **E-mail (SMTP gratuito)** | Custo zero, simples. **Escolhido.** |
| WhatsApp Business API | Mensagens iniciadas pelo sistema são cobradas pela Meta; descartado por custo. Bibliotecas não oficiais violam termos. |
| Push (PWA) | Exige HTTPS e mais trabalho; fica para a fase de PWA. |

O envio fica atrás de uma interface `Notificador`, para trocar/somar canais depois sem
mexer no resto.

## 5. Arquitetura (decidido)

### 5.1 Camadas

```
Requisição → public/index.php → FrontController
                                   │ lê phpmvc-config.xml (ConfigLoader com cache)
                                   ├─ Form   : recebe e valida dados da requisição
                                   ├─ Action : controller fino; chama Service; devolve nome do forward
                                   │            └─ Service : regras de negócio (faturas, parcelas, previsão)
                                   │                  └─ DAO : SQL via PDO (prepared statements)
                                   │                        └─ Model : objetos de domínio (Usuario, Cartao, Fatura...)
                                   └─ Forward → View Smarty (.tpl) ou redirect
```

- **Action nunca contém SQL nem regra de negócio.** Isso permite, no futuro, uma API JSON
  reutilizando os mesmos Services (caminho para app/Play Store).
- **Autoload PSR-4 via Composer** (`App\` → `desenvolvimento/classes/`). O `type` do XML
  (`classes.action.UsuarioAction`) é resolvido para `App\Action\UsuarioAction`.
- **Filtro de autenticação** no FrontController: toda action exige sessão, exceto as
  marcadas como públicas no XML (login, cadastro).
- **Dinheiro:** `NUMERIC(12,2)` no banco; no PHP, inteiro em centavos dentro de um value
  object `Dinheiro` (nunca `float`).

### 5.2 Estrutura de pastas (proposta)

```
financas/
├─ .htaccess                 → reescreve tudo para WEB-INF/public/ e bloqueia o resto
├─ docs/
└─ WEB-INF/
   ├─ public/                ← única pasta servida pelo navegador
   │  ├─ index.php           ← entrada (substitui Main.php)
   │  └─ assets/ (css, js, img)
   ├─ desenvolvimento/
   │  ├─ classes/
   │  │  ├─ core/            FrontController, ConfigLoader, Database, Session, Csrf, View(SmartyFactory)
   │  │  ├─ action/          UsuarioAction, DashboardAction, CartaoAction...
   │  │  ├─ form/            UsuarioForm, CartaoForm...
   │  │  ├─ service/
   │  │  ├─ dao/
   │  │  ├─ model/
   │  │  └─ notificacao/     Notificador (interface), EmailNotificador
   │  ├─ config/phpmvc-config.xml
   │  ├─ tpl/                layout/, usuario/, dashboard/...
   │  ├─ cache/  templates_c/
   ├─ migrations/  seeds/
   ├─ tests/                 PHPUnit
   ├─ .env.example
   └─ composer.json
```

### 5.3 Configuração e segredos
- `.env` (ignorado pelo git) com dados do banco e SMTP, lido por `vlucas/phpdotenv`.
- `phinx.yml` é substituído por `phinx.php`, que lê o mesmo `.env` — uma única fonte de
  credenciais. `.env.example` vai para o repositório.

### 5.4 Segurança (mínimo obrigatório)
- `password_hash`/`password_verify`; `session_regenerate_id` no login; cookie
  `HttpOnly` + `SameSite=Lax`.
- Token CSRF em todo formulário POST.
- Smarty com escape HTML por padrão.
- Toda consulta filtrada por `usuario_id` da sessão (nunca vindo do formulário).
- Erros detalhados só em desenvolvimento.

## 6. Banco de dados (PostgreSQL)

Todas as tabelas têm `id BIGSERIAL`, `criado_em` e `atualizado_em TIMESTAMPTZ`.
Valores em `NUMERIC(12,2)`.

| Tabela | Campos principais | Observações |
|---|---|---|
| `usuarios` | nome, email (único), senha_hash, receber_relatorio_email (bool, padrão true) | |
| `contas` | usuario_id, nome, tipo (`corrente`,`poupanca`,`carteira`), saldo_inicial, ativo | De onde sai/entra dinheiro fora do cartão. |
| `cartoes` | usuario_id, nome, bandeira, limite, dia_fechamento, dia_vencimento, conta_pagamento_id, cor, ativo | |
| `categorias` | usuario_id (NULL = padrão do sistema), nome, tipo (`despesa`,`receita`), cor, icone | Seed com categorias padrão (Mercado, Transporte, Lazer, Salário...). |
| `faturas` | cartao_id, competencia (1º dia do mês), data_fechamento, data_vencimento, status (`aberta`,`fechada`,`paga`), valor_pago, pago_em | Único (cartao_id, competencia). Criada sob demanda. |
| `compras_cartao` | usuario_id, cartao_id, categoria_id, descricao, valor_total, qtd_parcelas, data_compra, origem (`manual`,`importacao`), hash_importacao | hash evita duplicar na reimportação. |
| `parcelas` | compra_id, fatura_id, numero, valor | Uma compra em 10x gera 10 parcelas em 10 faturas. Total da fatura = soma das parcelas. |
| `lancamentos` | usuario_id, conta_id, categoria_id, tipo (`receita`,`despesa`), descricao, valor, data, efetivado (bool), recorrencia_id, fatura_id | Movimentos fora do cartão. Pagamento de fatura = lançamento com fatura_id. |
| `recorrencias` | usuario_id, tipo, descricao, valor, categoria_id, conta_id **ou** cartao_id, dia_do_mes, inicio, fim, ativo | Gera lançamentos/compras automaticamente a cada mês. |
| `limites` | usuario_id, categoria_id (NULL = limite geral), valor_mensal | Base dos alertas. |
| `notificacoes_enviadas` | usuario_id, tipo, referencia, enviado_em | Evita mandar o mesmo e-mail duas vezes. |

**Regra de competência** *(decisão do Claude — confirmar)*: `competencia` = mês do
**vencimento** da fatura, porque é o mês em que o salário paga a conta e é o que a
previsão do mês subtrai. Compra feita **antes** do dia de fechamento entra na fatura
que fecha naquele mês; compra **no dia do fechamento ou depois** vai para a próxima (é o
"melhor dia de compra" da maioria dos cartões brasileiros). Parcelas seguintes nas
faturas subsequentes. Arredondamento: centavos restantes vão na 1ª parcela.

`compras_cartao` também recebe `recorrencia_id` (assinaturas no cartão geradas por
recorrência), para a geração mensal não duplicar.

Todas as tabelas acima são criadas já na Fase 1 (migrations), mesmo que as telas venham
depois.

## 7. Fases

| Fase | Entrega |
|---|---|
| **1 — Base (escopo do primeiro plano)** | Corrigir/refatorar o núcleo (FrontController, ConfigLoader, autoload, Smarty, PDO, `.env`, `.htaccess`); migrations de todo o banco + seed de categorias; cadastro, login, logout; layout principal (menu lateral, topo, responsivo) e painel com estados vazios honestos; testes PHPUnit do núcleo. |
| 2 — Núcleo financeiro | Contas, cartões, compras (com parcelas), faturas, lançamentos, recorrências; painel com dados reais e previsão do mês. |
| 3 — Importação | Upload de fatura CSV/OFX, conferência e categorização antes de salvar. |
| 4 — Limites e e-mail | Limites e alertas; `EmailNotificador` (PHPMailer + SMTP gratuito); relatório semanal e aviso de fechamento de fatura, disparados por URL protegida por token (chamável por um cron externo gratuito). |
| 5 — PWA e hospedagem | Manifest, service worker, deploy gratuito. |

Cada fase vira um plano e um PR para `main`.

### 7.1 Tela principal (rascunho)

```
┌──────────────┬───────────────────────────────────────────────┐
│  ◆ Finanças  │  Novembro 2026  ‹ ›                 Cristhian ▾ │
│              ├───────────────────────────────────────────────┤
│  Painel      │ ┌─Receitas──┐ ┌─Faturas───┐ ┌─Fixos─────┐ ┌─Sobra─┐ │
│  Cartões     │ │ R$ 0,00   │ │ R$ 0,00   │ │ R$ 0,00   │ │R$ 0,00│ │
│  Lançamentos │ └───────────┘ └───────────┘ └───────────┘ └───────┘ │
│  Fixos       │ ┌─Próximas faturas────────┐ ┌─Gastos por categoria─┐ │
│  Limites     │ │ Nenhum cartão ainda.    │ │  (gráfico)           │ │
│  Relatórios  │ │ [+ Cadastrar cartão]    │ │                      │ │
│              │ └─────────────────────────┘ └──────────────────────┘ │
└──────────────┴───────────────────────────────────────────────┘
```
No celular o menu lateral vira menu inferior/hambúrguer.

### 7.2 Decisões da Fase 1 *(decisões do Claude — confirmar)*

- **Menu:** itens de funcionalidades futuras aparecem desabilitados com selo "em breve"
  (mostra o roteiro do produto sem links quebrados). Só "Painel" funciona.
- **Painel:** sem cards de totais na Fase 1 (seriam sempre R$ 0,00). Mostra boas-vindas e
  um estado vazio explicando o que vem a seguir. Os cards e o seletor de mês entram na
  Fase 2, calculados de verdade via Service/DAO.
- **Fora da Fase 1:** recuperação de senha e confirmação de e-mail (dependem do e-mail,
  Fase 4).

### 7.3 Ajustes técnicos definidos na revisão

- Pastas em PascalCase (`Core/`, `Action/`, `Form/`, `Service/`, `Dao/`, `Model/`,
  `Notificacao/`) para o autoload PSR-4 funcionar em servidor Linux (sensível a
  maiúsculas). Isso substitui os nomes minúsculos da seção 5.2.
- Rotas públicas marcadas **por método** da action, não por módulo (`/usuario` tem login
  e cadastro públicos, mas logout exige sessão).
- Forwards com `redirect="true"` apontam para uma rota (`?do=dashboard&action=index`),
  nunca para um `.tpl`.
- Migrations em `WEB-INF/migrations/`, seeds em `WEB-INF/seeds/`.
- URL base configurável no `.env`. Fuso horário `America/Sao_Paulo`.
- Smarty 5 via Composer; PHPUnit com um banco de testes separado
  (`financas_teste`), configurado no `.env`.

## 8. Deixado para quem for construir

- Paleta de cores, tipografia e ícones do tema (Bootstrap Icons sugerido), desde que fuja
  do visual padrão.
- Bibliotecas auxiliares menores (ex.: validação), desde que via Composer.
- Formato exato do `.htaccess` e detalhes do cache do ConfigLoader.
- Escolha do host gratuito e do provedor SMTP (Fase 4/5). Atenção: muitos hosts PHP
  gratuitos só oferecem MySQL; alternativas são PostgreSQL gratuito externo (Neon,
  Supabase) ou trocar o adaptador — PDO mantém isso viável.
