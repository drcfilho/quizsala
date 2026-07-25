# Migração Bulma — Plano 3: Tela da prova do aluno (prova.php + aluno.js) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Adicionar o Bulma vendorizado + alternador de tema na tela onde o aluno responde a prova, e migrar só as partes genéricas (botões da tela final, mensagens de estado) pro Bulma — mantendo intacto tudo que é a identidade visual específica do produto (bolha de alternativa, placar com número gigante monoespaçado, comprovante de impressão), que o Bulma não cobre e a spec já definiu como fora de escopo.

**Architecture:** `public/prova.php` é uma casca quase vazia — quase todo o conteúdo é montado dinamicamente por `public/assets/aluno.js` via `document.createElement`. Isso significa que a "migração de markup" aqui acontece em boa parte dentro do JS (trocar `className`), não só no HTML estático.

**Tech Stack:** Bulma vendorizado (`public/assets/vendor/bulma.css`, Plano 1), `tema.js` (Plano 2).

## Global Constraints

- **Fora de escopo, não tocar:** `.tela-prova`, `.cabecalho-prova`, `.marca-registro`, `.nome-aluno`, `.contador-questao` (cabeçalho com as marcas de registro ▪ — identidade visual travada), `.enunciado`, `.lista-alternativas`, `.alternativa`/`.bolha` e todos os seus estados (`.marcada`, `.correta`, `.escolhida-certa`, `.escolhida-errada`) — é o elemento de assinatura do produto (`DESIGN.md`, "A Regra do Registro Duplo"), `.placar-final`/`.numero-placar`/`.rotulo-placar` (número gigante monoespaçado — mesma regra), `#comprovante-impressao` e todo o bloco `@media print` (comprovante em PDF), `.aviso-flutuante` (toast).
- **Em escopo pra virar Bulma:** os dois botões da tela final (`.botao-principal`, `.botao-secundario-final` — genéricos, sem identidade visual específica) e as duas mensagens simples de texto (`.mensagem-estado`, `.mensagem-agradecimento`).
- Alvo de toque 64px já é garantido globalmente pela regra `.input, .button:not(.is-small) { min-height: 64px; }` adicionada em `estilo.css` no Plano 2 — os novos botões Bulma deste plano já herdam isso automaticamente, não precisa repetir.
- Cache-busting: todo `<link>`/`<script>` novo segue `?v=<?= filemtime(...) ?>`.
- `.botao-principal`, `.mensagem-estado`, `.acoes-final`, `.botao-secundario-final`, `.mensagem-agradecimento` são usadas **só** em `public/assets/aluno.js` (confirmado via grep no repo inteiro) — depois da Task 2 remover o uso delas no JS, as 5 regras ficam órfãs e devem ser removidas de `estilo.css`.

---

### Task 1: Adicionar Bulma + alternador de tema em `prova.php`

**Files:**
- Modify: `public/prova.php` (arquivo inteiro, 22 linhas)
- Modify: `public/assets/estilo.css` — adicionar `.quizsala-topo-prova` (nova regra, não remove nada)

**Interfaces:**
- Consumes: `public/assets/vendor/bulma.css` (Plano 1), `public/assets/tema.js` (Plano 2).

- [ ] **Step 1: Reescrever `public/prova.php` com este conteúdo exato**

```php
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
<main class="tela-prova">
<div class="quizsala-topo-prova">
<header class="cabecalho-prova">
<span class="marca-registro" aria-hidden="true">&#9642;</span>
<span class="nome-aluno" id="nome-aluno"></span>
<span class="contador-questao" id="contador-questao"></span>
<span class="marca-registro" aria-hidden="true">&#9642;</span>
</header>
<button type="button" class="button is-small is-light" data-alternar-tema aria-label="Alternar tema claro/escuro">
<span class="icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg></span>
</button>
</div>
<div id="conteudo-prova" role="group" aria-live="polite"></div>
</main>
<script src="assets/tema.js?v=<?= filemtime(__DIR__ . '/assets/tema.js') ?>" defer></script>
<script src="assets/aluno.js?v=<?= filemtime(__DIR__ . '/assets/aluno.js') ?>" defer></script>
</body>
</html>
```

Note: `<header class="cabecalho-prova">` e seus 4 `<span>` filhos são **idênticos** ao original — só ganharam um `<div class="quizsala-topo-prova">` por fora, ao lado do botão de tema. `id="nome-aluno"`, `id="contador-questao"`, `id="conteudo-prova"` continuam iguais (é por esses ids que `aluno.js` encontra os elementos — não mudar).

- [ ] **Step 2: Em `public/assets/estilo.css`, adicionar esta regra logo após o bloco `.quizsala-campo-codigo { ... }`**

```css
/* Cabecalho da prova (marcas de registro) ganhou um irmao (botao de
   tema) ao lado - flex:1 so no cabecalho dentro desse contexto, pra ele
   continuar ocupando o espaco que ocupava sozinho antes. Nao mexe em
   .cabecalho-prova em si (identidade visual travada, DESIGN.md). */
.quizsala-topo-prova {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.quizsala-topo-prova .cabecalho-prova { flex: 1; }
```

- [ ] **Step 3: Verificar**

```bash
php -l public/prova.php
grep -c "quizsala-topo-prova" public/assets/estilo.css
```

Expected: `No syntax errors detected`; grep retorna `2` (a regra e o seletor filho).

- [ ] **Step 4: Commit**

```bash
git add public/prova.php public/assets/estilo.css
git commit -m "$(cat <<'EOF'
feat: Bulma + alternador de tema em prova.php

Cabecalho com as marcas de registro (identidade visual travada,
DESIGN.md) continua intacto - so ganhou um botao de tema ao lado via
wrapper novo (.quizsala-topo-prova), sem tocar em .cabecalho-prova.
EOF
)"
```

---

### Task 2: Migrar botões e mensagens genéricas de `aluno.js` pro Bulma

**Files:**
- Modify: `public/assets/aluno.js:31` (mensagem de espera), `:157-169` (botões da tela final), `:178` (mensagem de agradecimento)
- Modify: `public/assets/estilo.css` — remover `.botao-principal`, `.mensagem-estado`, `.acoes-final`, `.botao-secundario-final`, `.mensagem-agradecimento` (órfãs depois desta task)

**Interfaces:**
- Nenhuma nova interface — `criarBotao(texto, classe, aoClicar)` (já existe em `aluno.js`) continua com a mesma assinatura, só muda o argumento `classe` que os dois call sites passam.

- [ ] **Step 1: Em `public/assets/aluno.js`, trocar a linha 31**

De:
```javascript
    p.className = 'mensagem-estado';
```
Para:
```javascript
    p.className = 'has-text-centered has-text-grey mt-6';
```

- [ ] **Step 2: Trocar o bloco `renderizarPlacar` (linhas 157-169) de**

```javascript
    var acoes = document.createElement('div');
    acoes.className = 'acoes-final';
    acoes.appendChild(criarBotao('Salvar comprovante em PDF', 'botao-principal', function () {
        limparTimeoutPlacar();
        prepararComprovante(dados);
        window.print();
        renderizarAgradecimento();
    }));
    acoes.appendChild(criarBotao('Concluir', 'botao-secundario-final', function () {
        limparTimeoutPlacar();
        renderizarAgradecimento();
    }));
    container.appendChild(acoes);
```

**Para:**

```javascript
    var acoes = document.createElement('div');
    acoes.className = 'mt-6';
    acoes.appendChild(criarBotao('Salvar comprovante em PDF', 'button is-primary is-fullwidth mb-3', function () {
        limparTimeoutPlacar();
        prepararComprovante(dados);
        window.print();
        renderizarAgradecimento();
    }));
    acoes.appendChild(criarBotao('Concluir', 'button is-fullwidth', function () {
        limparTimeoutPlacar();
        renderizarAgradecimento();
    }));
    container.appendChild(acoes);
```

- [ ] **Step 3: Trocar a linha 178**

De:
```javascript
    p.className = 'mensagem-agradecimento';
```
Para:
```javascript
    p.className = 'title is-4 has-text-centered mt-6';
```

- [ ] **Step 4: Verificar sintaxe do JS**

```bash
node --check public/assets/aluno.js
```

Expected: sem saída.

- [ ] **Step 5: No `public/assets/estilo.css`, confirmar que as 5 classes ficaram órfãs e removê-las**

```bash
grep -rn "botao-principal\|mensagem-estado\|acoes-final\|botao-secundario-final\|mensagem-agradecimento" public/*.php public/assets/*.js
```

Expected: nenhuma saída (as 5 classes não aparecem mais em nenhum `.php`/`.js` do projeto — só ainda existem como regras CSS órfãs em `estilo.css`, que devem ser removidas agora).

Remover de `estilo.css` os blocos `.botao-principal { ... }`, `.mensagem-estado { ... }`, `.acoes-final { ... }`, `.botao-secundario-final { ... }`, `.mensagem-agradecimento { ... }`. **Não remover nada mais** — `.tela-prova`, `.cabecalho-prova`, `.marca-registro`, `.nome-aluno`, `.enunciado`, `.lista-alternativas`, `.alternativa`/`.bolha` e seus estados, `.placar-final`/`.numero-placar`/`.rotulo-placar`, `#comprovante-impressao` e o `@media print`, e `.aviso-flutuante` continuam intactos.

- [ ] **Step 6: Confirmar que o essencial sobrou intacto**

```bash
grep -c "\.bolha {" public/assets/estilo.css
grep -c "numero-placar" public/assets/estilo.css
grep -c "comprovante-impressao" public/assets/estilo.css
grep -c "aviso-flutuante" public/assets/estilo.css
```

Expected: todos `>= 1`.

- [ ] **Step 7: Rodar a bateria de testes E2E**

```bash
bash bin/teste.sh 2>&1 | tail -5
```

Expected: `Falhou: 6` (as mesmas 6 falhas pré-existentes de troca de senha do admin, não relacionadas a este plano). Mais que 6 é regressão — investigar antes de commitar.

- [ ] **Step 8: Commit**

```bash
git add public/assets/aluno.js public/assets/estilo.css
git commit -m "$(cat <<'EOF'
feat: migra botoes/mensagens genericos da tela final do aluno pro Bulma

Bolha de alternativa, placar com numero gigante e comprovante de
impressao continuam com CSS proprio (identidade visual travada,
DESIGN.md) - so os dois botoes ("Salvar comprovante"/"Concluir") e as
duas mensagens de texto simples (espera/agradecimento), que nao tinham
nada de especifico, viraram classes do Bulma. As 5 regras antigas
ficaram sem uso em nenhum arquivo e foram removidas de estilo.css.
EOF
)"
```

---

## Self-Review

**Spec coverage:** a spec explicitly lista bolha/cronômetro/distribuição/QR como fora do alcance do Bulma, cobertos por CSS próprio — este plano aplica exatamente esse critério em `prova.php`/`aluno.js`, migrando só o que é genérico (2 botões, 2 mensagens) e documentando por quê o resto fica de fora.

**Placeholder scan:** nenhum "TBD" — todo trecho de código (HTML, CSS, JS) está completo e literal.

**Type consistency:** `criarBotao(texto, classe, aoClicar)` mantém a mesma assinatura em ambos os call sites da Task 2; `data-alternar-tema` (Task 1) é o mesmo atributo já usado em `index.php` (Plano 2).
