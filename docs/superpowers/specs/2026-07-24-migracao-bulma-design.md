# Migração do CSS pra Bulma (customizado)

## Contexto

O QuizSala hoje usa três folhas de estilo próprias, escritas do zero (`public/assets/estilo.css`, `tela.css`, `admin.css`), seguindo um sistema visual documentado em `DESIGN.md`/`arquitetura.md` §13: totalmente flat (zero sombra), uma única cor de destaque (`#d9342b`), tipografia monoespaçada nos rótulos, alvos de toque de 64px, e rejeição explícita a qualquer estética de framework genérico ou gamificação (Kahoot/Duolingo).

Decisão do usuário nesta conversa: adotar o [Bulma](https://bulma.io) (framework CSS baseado em Flexbox) como base pra **todas as telas** do produto — aluno, projetor e admin (mesa + controle ao vivo) — em vez de manter o CSS 100% próprio.

## Decisões já tomadas (perguntas respondidas nesta conversa)

1. **Tema:** Bulma customizado via variáveis Sass (`$primary: #d9342b`, `$radius: 0`, sombras zeradas), não o azul/cinza padrão. Compilado uma vez com `npm`/`sass` nesta sessão de desenvolvimento — vira um `.css` estático vendorizado, sem virar build step do app em produção (mantém a restrição "sem internet no ambiente de uso" do `CLAUDE.md`).
2. **Escopo:** o site inteiro — aluno (`index.php`, `prova.php`), projetor (`tela.php`), admin de mesa (`admin_layout.php` + as 7 páginas que o usam) e controle ao vivo (`admin/sessao.php`). Não fica nenhuma tela de fora.
3. **Componentes específicos do produto que o Bulma não cobre** (bolha de alternativa estilo cartão-resposta, barra de distribuição de respostas, cronômetro, layout do QR code) continuam em CSS próprio, agora como uma camada fina por cima do Bulma, não mais reinventando grid/botão/formulário/card do zero.

## Arquitetura

### Vendoring do Bulma

- Compilar localmente (nesta sessão): `npm install bulma` num diretório temporário → `_variaveis-quizsala.scss` customizando `$primary`, `$radius-small/$radius/$radius-large`, `$shadow`/box-shadow das variáveis relevantes (button, box, card, panel, notification) → `npx sass` gera o CSS final.
- Saída: `public/assets/vendor/bulma.css` (arquivo estático, committado no repo — o `node_modules`/toolchain usado pra gerar não fica no projeto).
- Fonte da customização (`_variaveis-quizsala.scss`, pequeno, só overrides) fica versionado em `public/assets/vendor/` também, pra permitir recompilar no futuro se alguém quiser ajustar uma variável — com uma nota no arquivo de como rodar de novo (`npm install bulma && npx sass ...`).

### Camadas de CSS por tela

Cada tela passa a carregar `vendor/bulma.css` + um CSS próprio bem mais enxuto (só o que o Bulma não cobre):

| Tela | Bulma cobre | CSS próprio continua cobrindo |
|---|---|---|
| `index.php`/`prova.php` (`estilo.css`) | formulário de entrada, cartão, botão | bolha de alternativa (Regra do Sinal Duplo), marca de registro monoespaçada |
| `tela.php` (`tela.css`) | tipografia base, layout | QR + código gigante, temporizador, barras de distribuição, animação de contador (T21) |
| `admin.css` | sidebar (`.menu`), lista de provas (`.panel`/`.box`), botões (`.buttons` + largura própria), tags de status, formulários (`.field`/`.control`), notificações de erro | zona de risco (T23), alvos de 64px do controle ao vivo, comprovante com sinal duplo |

### Migração de markup

Cada um dos 13 arquivos PHP de view (`public/index.php`, `prova.php`, `tela.php`, `src/admin_layout.php`, `src/auth.php`, e as 7 páginas de `public/admin/`) tem as classes CSS trocadas pras classes do Bulma equivalentes (`button`, `box`, `panel`/`panel-block`, `field`/`control`/`input`, `tag`, `notification`, `columns`/`column`, `menu`/`menu-list`), mantendo os `id`s e a estrutura que o JS (`admin.js`, `tela.js`, `aluno.js`) já depende via `document.getElementById`/`querySelector`.

**Risco principal:** o JS seleciona elementos por classe em vários pontos (ex.: `.botao-acao`, `.presenca-admin`, `.temporizador-painel`). Trocar a classe visual sem atualizar o seletor correspondente quebra a interatividade silenciosamente. Cada arquivo `.js` precisa ser revisado junto com o `.php`/`.css` que ele acompanha, não depois.

## Fora de escopo

- Não adiciona JavaScript do Bulma (ele não tem — é só CSS).
- Não muda a lógica de negócio, rotas, ou schema do banco — só a camada visual.
- Não migra pra um pipeline de build (Vite/Webpack) — o Bulma compilado vira um arquivo estático igual aos outros, servido do jeito que `public/assets/` já funciona hoje.

## Documentação a atualizar (depois da migração de código)

- `PRODUCT.md`/`DESIGN.md`: substituir a seção do sistema visual flat por uma descrição do sistema Bulma customizado (nova paleta de componentes, o que continua igual — cor, ausência de sombra/radius mesmo dentro do Bulma).
- `plan.md`: registrar a migração como item concluído/em andamento.
- `mapa-urls-teste.html`: qualquer descrição visual que dependa do CSS antigo.
- `arquitetura.md`: só se algo estrutural (não só visual) mudar — provavelmente não muda.

## Critérios de sucesso

- `bin/teste.sh` continua passando igual a antes da migração (é teste de comportamento/API, não deveria quebrar por causa de CSS — mas confirma que a migração não teve efeito colateral em markup que o teste dependa, como atributos usados pelos testes E2E via `curl`/`grep`).
- Visualmente, cada tela testada manualmente (aluno, projetor, admin de mesa, controle ao vivo) no navegador, sem elemento quebrado/sem estilo.
- Nenhum seletor JS órfão (checar `admin.js`, `tela.js`, `aluno.js` contra as classes novas).
