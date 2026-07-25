# Migração Bulma — Plano 2: Alternador de tema + tela de entrada do aluno Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar o alternador manual de tema claro/escuro (infraestrutura compartilhada, reusada por todos os planos seguintes) e migrar `public/index.php` (tela de entrada do aluno) pro Bulma vendorizado no Plano 1.

**Architecture:** O Bulma 1.0.4 já compila regras `[data-theme=dark]`/`[data-theme=light]` automaticamente (confirmado no Plano 1) — falta só um script pequeno que escreve `data-theme` no `<html>`, lembra a escolha em `localStorage`, e um botão reusável em cada página. `estilo.css` (aluno) encolhe: perde as regras que só a tela de entrada usava, ganha só um ajuste de alvo de toque (Bulma usa ~40px de altura padrão, o QuizSala exige 64px) e uma largura de container.

**Tech Stack:** Bulma vendorizado (`public/assets/vendor/bulma.css`, Plano 1), JS puro sem framework (padrão do projeto).

## Global Constraints

- Alvo de toque mínimo: 64px em todo elemento tocável pelo aluno (`DESIGN.md`).
- Zero sombra, zero radius decorativo — já garantido pelo Bulma customizado do Plano 1, não precisa repetir aqui.
- `.botao-principal` (classe de `estilo.css`) é reusada por `public/assets/aluno.js:159` pra gerar um botão fora do HTML estático desta task — **não remover essa regra do CSS**, só as classes que só a tela de entrada usa.
- `.rotulo` (classe de `estilo.css`) é usada só em `public/index.php` — pode remover.
- Cache-busting: todo `<link>`/`<script>` novo segue o padrão já existente (`?v=<?= filemtime(...) ?>`), igual ao resto do projeto.
- Todo `.md` do projeto em pt-BR; comentários em código também em pt-BR, seguindo o estilo já usado no repo (sem acento em comentário de código, olhando os arquivos existentes).

---

### Task 1: Alternador de tema (`tema.js`)

**Files:**
- Create: `public/assets/tema.js`

**Interfaces:**
- Produces: qualquer elemento com o atributo `data-alternar-tema` (ex.: `<button type="button" data-alternar-tema>`) vira um botão que alterna `document.documentElement.dataset.theme` entre `"light"` e `"dark"`, persistindo em `localStorage["quizsala_tema"]`. Ao carregar, aplica o tema salvo ou, na ausência de um, o tema do sistema (`prefers-color-scheme`). Páginas futuras (Planos 3-6) só precisam incluir `<script src="assets/tema.js" defer></script>` (ou `../assets/tema.js` no admin) e um botão com esse atributo.

- [ ] **Step 1: Criar `public/assets/tema.js` com este conteúdo exato**

```javascript
// QuizSala - alterna claro/escuro manualmente, por cima do automatico do
// Bulma (prefers-color-scheme, ja embutido no bulma.css vendorizado).
// So escreve o atributo data-theme que o CSS do Bulma ja sabe interpretar
// e lembra a escolha entre sessoes - sem isso, o aluno/professor fica
// preso no tema que o sistema operacional escolheu.
(function () {
    var CHAVE = 'quizsala_tema';

    function temaDoSistema() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    }

    function temaAtual() {
        return localStorage.getItem(CHAVE) || temaDoSistema();
    }

    function aplicar(tema) {
        document.documentElement.dataset.theme = tema;
    }

    aplicar(temaAtual());

    document.addEventListener('click', function (evento) {
        var botao = evento.target.closest('[data-alternar-tema]');
        if (!botao) {
            return;
        }
        var novo = temaAtual() === 'dark' ? 'light' : 'dark';
        localStorage.setItem(CHAVE, novo);
        aplicar(novo);
    });
})();
```

- [ ] **Step 2: Verificar sintaxe**

```bash
node --check public/assets/tema.js
```

Expected: nenhuma saída (sintaxe válida).

- [ ] **Step 3: Commit**

```bash
git add public/assets/tema.js
git commit -m "$(cat <<'EOF'
feat: alternador manual de tema claro/escuro (data-theme)

Bulma 1.x ja gera as regras [data-theme=dark]/[data-theme=light] no
CSS vendorizado (Plano 1 da migracao) - faltava so escrever o
atributo e lembrar a escolha do usuario. Infra compartilhada: paginas
futuras so incluem o script e um botao com data-alternar-tema.
EOF
)"
```

---

### Task 2: Migrar `public/index.php` (tela de entrada do aluno) pro Bulma

**Files:**
- Modify: `public/index.php` (arquivo inteiro, 61 linhas)
- Modify: `public/assets/estilo.css` — remover as regras `.rotulo`, `.tela-entrada`, `.tela-entrada .cartao`, `.titulo`, `.campo`, `.campo-codigo`, `.aviso` (linhas 26-34 e 43-81 e 93-96 da versão atual — confira o conteúdo real antes de editar, o arquivo pode ter mudado). **Não remover `.botao-principal`** (Task 1 explica por quê) nem nada abaixo do comentário `/* Tela da prova */` (usado por `prova.php`, fora do escopo deste plano).
- Test: manual (ver Step 5)

**Interfaces:**
- Consumes: `public/assets/vendor/bulma.css` (Plano 1), `public/assets/tema.js` (Task 1 deste plano).
- Produces: nenhuma interface nova pra outras tasks consumirem — é uma página-folha.

- [ ] **Step 1: Ler o `estilo.css` atual e confirmar as regras exatas a remover**

```bash
grep -n "^\.rotulo\|^\.tela-entrada\|^\.titulo\|^\.campo\|^\.aviso\|^/\* Tela da prova" public/assets/estilo.css
```

Anote os números de linha retornados — use-os pra remover exatamente esses blocos (cada um termina no primeiro `}` da própria regra), sem tocar em nada a partir do comentário `/* Tela da prova */` em diante.

- [ ] **Step 2: Reescrever `public/index.php` com este conteúdo exato**

```php
<?php

declare(strict_types=1);

$codigo = htmlspecialchars((string) ($_GET['s'] ?? ''));
$erro = (string) ($_GET['erro'] ?? '');

$mensagemErro = match ($erro) {
    'codigo' => 'Código não encontrado ou sessão encerrada. Confira com o professor.',
    'nome' => 'Essa sessão pede nome pra entrar.',
    default => '',
};
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala</title>
<link rel="stylesheet" href="assets/vendor/bulma.css?v=<?= filemtime(__DIR__ . '/assets/vendor/bulma.css') ?>">
<link rel="stylesheet" href="assets/estilo.css?v=<?= filemtime(__DIR__ . '/assets/estilo.css') ?>">
</head>
<body>
<section class="hero is-fullheight">
<div class="hero-body">
<div class="container quizsala-container-estreito">
<div class="box">
<div class="level mb-4">
<div class="level-left">
<h1 class="title is-4 mb-0">QuizSala</h1>
</div>
<div class="level-right">
<button type="button" class="button is-small is-light" data-alternar-tema aria-label="Alternar tema claro/escuro">
<span class="icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg></span>
</button>
</div>
</div>
<?php if ($mensagemErro !== ''): ?>
<p class="help is-danger"><?= htmlspecialchars($mensagemErro) ?></p>
<?php endif; ?>
<form method="post" action="api/entrar.php">
<div class="field">
<label class="label" for="codigo">Código da sala</label>
<div class="control">
<input
  class="input quizsala-campo-codigo"
  type="text"
  id="codigo"
  name="codigo"
  value="<?= $codigo ?>"
  required
  autocapitalize="characters"
  autocomplete="off"
  inputmode="text"
  enterkeyhint="next"
  maxlength="6"
>
</div>
</div>
<div class="field">
<label class="label" for="nome">Nome (se pedido pelo professor)</label>
<div class="control">
<input
  class="input"
  type="text"
  id="nome"
  name="nome"
  autocapitalize="words"
  autocomplete="off"
  enterkeyhint="go"
  maxlength="60"
>
</div>
</div>
<div class="control">
<button type="submit" class="button is-primary is-fullwidth">Entrar</button>
</div>
</form>
</div>
</div>
</div>
</section>
<script src="assets/tema.js?v=<?= filemtime(__DIR__ . '/assets/tema.js') ?>" defer></script>
</body>
</html>
```

Note: `id="codigo"`/`id="nome"` e os `name`s dos campos, a `action="api/entrar.php"`, e o `value="<?= $codigo ?>"` (já escapado na linha 5, não escapar de novo) são idênticos ao arquivo original — `api/entrar.php` lê por `name`, não por classe CSS, então não muda.

- [ ] **Step 3: No `public/assets/estilo.css`, adicionar estas duas regras logo após o bloco `:root { ... }`**

```css
/* Bulma usa ~2.5em (~40px) de altura padrao em input/button - abaixo dos
   64px que o QuizSala exige em todo alvo tocavel pelo aluno (DESIGN.md).
   :not(.is-small) deixa de fora botoes pequenos de proposito, como o
   alternador de tema. */
.input, .button:not(.is-small) { min-height: 64px; }

.quizsala-container-estreito { max-width: 22rem; }
```

- [ ] **Step 4: Remover do `estilo.css` as regras identificadas no Step 1** (`.rotulo`, `.tela-entrada`, `.tela-entrada .cartao`, `.titulo`, `.campo`, `.campo-codigo`, `.aviso`). Confirmar que `.botao-principal` e tudo a partir de `/* Tela da prova */` continuam intactos:

```bash
grep -c "botao-principal" public/assets/estilo.css
grep -c "Tela da prova" public/assets/estilo.css
```

Expected: ambos `>= 1`.

- [ ] **Step 5: Verificação manual (sem framework de teste de JS/HTML no projeto — mesmo padrão usado no resto do repo)**

```bash
php -l public/index.php
```

Expected: `No syntax errors detected`.

```bash
cd public && php -S 127.0.0.1:8099 -t . > /tmp/quizsala-plano2.log 2>&1 &
sleep 1
curl -s http://127.0.0.1:8099/index.php > /tmp/index-renderizado.html
grep -c "data-alternar-tema" /tmp/index-renderizado.html
grep -c "assets/vendor/bulma.css" /tmp/index-renderizado.html
grep -c "assets/tema.js" /tmp/index-renderizado.html
grep -c "tela-entrada\|campo-codigo\|botao-principal\">Entrar" /tmp/index-renderizado.html
kill %1
```

Expected: os 3 primeiros `grep -c` retornam `1`; o último retorna `0` (confirma que as classes antigas específicas da tela de entrada sumiram do HTML renderizado — `botao-principal` sozinho pode aparecer 0 vezes aqui porque o botão de entrar agora é `.button.is-primary`, não mais `.botao-principal`).

- [ ] **Step 6: Rodar a bateria de testes E2E do projeto pra confirmar que nada de comportamento quebrou**

```bash
bash bin/teste.sh 2>&1 | tail -5
```

Expected: `Falhou: 6` (as 6 falhas pré-existentes, não relacionadas — de troca de senha do admin; documentadas em conversa anterior, fora do escopo deste plano). Se o número de falhas for maior que 6, alguma coisa quebrou — investigar antes de prosseguir.

- [ ] **Step 7: Commit**

```bash
git add public/index.php public/assets/estilo.css
git commit -m "$(cat <<'EOF'
feat: migra tela de entrada do aluno (index.php) pro Bulma

Estrutura via hero/box/field do Bulma vendorizado (Plano 1) + botao de
alternar tema (Task 1 deste plano). estilo.css perde as regras
exclusivas da tela de entrada; .botao-principal fica porque
aluno.js:159 ainda gera um botao com essa classe na tela final da
prova, fora do escopo deste plano.
EOF
)"
```

---

## Self-Review

**Spec coverage:** a spec pedia migrar aluno/projetor/admin pro Bulma customizado; este plano cobre só a tela de entrada do aluno (`index.php`) + a infraestrutura de tema (decisão tomada em conversa: manter o dark mode automático do Bulma, com alternador manual). `prova.php` e `tela.php` (bolha de alternativa, cronômetro, distribuição — específicos do produto, não cobertos pelo Bulma) ficam pra planos seguintes, cada um pequeno o bastante pra revisar isolado.

**Placeholder scan:** nenhum "TBD"/"similar ao anterior" — todo HTML/CSS/JS está completo e literal nos steps.

**Type consistency:** `data-alternar-tema` (Task 1) é o mesmo atributo usado no botão criado na Task 2 — conferido.
