# Migração Bulma — Plano 5: Shell do admin de mesa + tela de login Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Colocar o Bulma vendorizado + alternador de tema no shell compartilhado do admin de mesa (`src/admin_layout.php`, usado por 7 páginas) e na tela de login (`src/auth.php`). Migrar a tela de login inteira pro Bulma (ela é auto-contida, mesmo padrão já usado em `index.php`, Plano 2).

**Architecture:** `admin.css` tem 635 linhas com muitas classes reusadas entre páginas E pelo `admin.js` (renderização dinâmica do controle ao vivo). Estratégia **aditiva**: este plano só acrescenta classes/regras novas, nunca remove uma regra existente de `admin.css` — a limpeza de CSS órfão fica pra um plano final, depois que todas as páginas tiverem migrado e for possível confirmar por grep que uma classe não é mais usada em lugar nenhum (mesmo cuidado que os Planos 2-3 já tiveram com `.botao-principal`/`.cartao-admin` etc., só que em escala maior aqui).

**Tech Stack:** Bulma vendorizado (`public/assets/vendor/bulma.css`, Plano 1), `tema.js` (Plano 2).

## Global Constraints

- **Aditivo, não remover nada de `admin.css` neste plano.** `.cartao-admin`, `.botao-acao`, `.tela-admin`, `.campo-admin`, `.rotulo`, `.erro-campo` são usadas em várias outras páginas admin (`provas.php`, `questao.php`, etc.) e em `public/assets/admin.js` (`cartao.className = 'cartao-admin'`) — nenhuma dessas é exclusiva de `auth.php`/`admin_layout.php`, remover qualquer uma agora quebraria página fora do escopo deste plano.
- `.lista-menu-admin`/`.link-menu-admin`/`.menu-admin-toggle*` (mecanismo de menu recolhível no celular via checkbox, sem JS) já funcionam bem e não têm equivalente direto no Bulma — não tocar, só acrescentar o botão de tema ao lado.
- Cache-busting em todo `<link>`/`<script>` novo.
- `src/auth.php` é renderizado a partir de páginas em `public/admin/*.php` (um nível de diretório abaixo de `public/`) — por isso o link original usa caminho absoluto `/assets/admin.css` (com barra inicial), não relativo. Manter esse padrão pros novos links/scripts.

---

### Task 1: Bulma + alternador de tema no shell (`src/admin_layout.php`)

**Files:**
- Modify: `src/admin_layout.php` — só a função `abrirLayoutAdmin()` (linhas 18-43)

**Interfaces:**
- Nenhuma mudança de interface — `abrirLayoutAdmin(string $titulo, string $paginaAtiva)` e `fecharLayoutAdmin()` continuam com a mesma assinatura, chamadas pelas 7 páginas que já as usam.

- [ ] **Step 1: Em `src/admin_layout.php`, trocar a função `abrirLayoutAdmin` de**

```php
function abrirLayoutAdmin(string $titulo, string $paginaAtiva): void
{
    ?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala — <?= htmlspecialchars($titulo) ?></title>
<link rel="stylesheet" href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../public/assets/admin.css') ?>">
</head>
<body>
<div class="shell-admin">
<nav class="menu-admin">
<p class="marca-admin">QuizSala</p>
<input type="checkbox" id="menu-admin-toggle" class="menu-admin-toggle-input">
<label for="menu-admin-toggle" class="menu-admin-toggle">Menu</label>
<ul class="lista-menu-admin">
<?php foreach (ADMIN_NAV as $chave => $item): ?>
<li><a class="link-menu-admin<?= $chave === $paginaAtiva ? ' ativo' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a></li>
<?php endforeach; ?>
</ul>
</nav>
<main class="conteudo-admin">
    <?php
}
```

**Para:**

```php
function abrirLayoutAdmin(string $titulo, string $paginaAtiva): void
{
    ?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala — <?= htmlspecialchars($titulo) ?></title>
<link rel="stylesheet" href="../assets/vendor/bulma.css?v=<?= filemtime(__DIR__ . '/../public/assets/vendor/bulma.css') ?>">
<link rel="stylesheet" href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../public/assets/admin.css') ?>">
</head>
<body>
<div class="shell-admin">
<nav class="menu-admin">
<div class="quizsala-topo-menu-admin">
<p class="marca-admin">QuizSala</p>
<button type="button" class="button is-small is-light" data-alternar-tema aria-label="Alternar tema claro/escuro">
<span class="icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg></span>
</button>
</div>
<input type="checkbox" id="menu-admin-toggle" class="menu-admin-toggle-input">
<label for="menu-admin-toggle" class="menu-admin-toggle">Menu</label>
<ul class="lista-menu-admin">
<?php foreach (ADMIN_NAV as $chave => $item): ?>
<li><a class="link-menu-admin<?= $chave === $paginaAtiva ? ' ativo' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a></li>
<?php endforeach; ?>
</ul>
</nav>
<main class="conteudo-admin">
    <?php
}
```

Note: `fecharLayoutAdmin()`, `PASSOS_FLUXO_PROVA` e `fluxoProva()` (resto do arquivo) não mudam.

- [ ] **Step 2: Em `public/assets/admin.css`, adicionar esta regra logo após o bloco `.marca-admin { ... }`**

```css
.quizsala-topo-menu-admin {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
}
.quizsala-topo-menu-admin .marca-admin { margin-bottom: 0; }
```

- [ ] **Step 3: Verificar**

```bash
php -l src/admin_layout.php
grep -c "quizsala-topo-menu-admin" public/assets/admin.css
```

Expected: `No syntax errors detected`; grep retorna `2`.

- [ ] **Step 4: Rodar a bateria de testes E2E** (toca em toda página admin de mesa, é o teste de regressão mais relevante aqui)

```bash
bash bin/teste.sh 2>&1 | tail -5
```

Expected: `Falhou: 6` (as mesmas 6 falhas pré-existentes).

- [ ] **Step 5: Commit**

```bash
git add src/admin_layout.php public/assets/admin.css
git commit -m "$(cat <<'EOF'
feat: Bulma + alternador de tema no shell do admin de mesa

Aditivo - .shell-admin, .menu-admin, .lista-menu-admin/.link-menu-admin
(mecanismo de recolher no celular via checkbox, sem equivalente direto
no Bulma) continuam intactos. So acrescenta o link do bulma.css e o
botao de tema ao lado da marca "QuizSala". Limpeza de CSS que ficar
orfao fica pro plano final, depois de todas as paginas migradas.
EOF
)"
```

---

### Task 2: Migrar a tela de login (`src/auth.php`) pro Bulma

**Files:**
- Modify: `src/auth.php` — só o bloco HTML de `exigirAdmin()` (linhas 39-64)

**Interfaces:**
- Nenhuma mudança de interface — `exigirAdmin()`, `tokenCsrf()`, `exigirCsrf()` continuam iguais. O `<form method="post">` sem `action` continua submetendo pra própria URL, e o campo `name="senha_admin"` continua sendo o que `exigirAdmin()` lê em `$_POST['senha_admin']` — não mudar esse nome.

- [ ] **Step 1: No `src/auth.php`, trocar o bloco HTML (dentro de `exigirAdmin()`) de**

```php
    http_response_code(401);
    ?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala — Entrar</title>
<link rel="stylesheet" href="/assets/admin.css?v=<?= filemtime(__DIR__ . '/../public/assets/admin.css') ?>">
</head>
<body>
<main class="tela-admin">
<div class="cartao-admin">
<p class="cabecalho-admin">Área do professor</p>
<?php if ($erro !== null): ?>
<p class="erro-campo"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>
<form method="post">
<label class="rotulo" for="senha_admin">Senha</label>
<input class="campo-admin" type="password" id="senha_admin" name="senha_admin" autofocus>
<button type="submit" class="botao-acao">Entrar</button>
</form>
</div>
</main>
</body>
</html>
    <?php
    exit;
```

**Para:**

```php
    http_response_code(401);
    ?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala — Entrar</title>
<link rel="stylesheet" href="/assets/vendor/bulma.css?v=<?= filemtime(__DIR__ . '/../public/assets/vendor/bulma.css') ?>">
<link rel="stylesheet" href="/assets/admin.css?v=<?= filemtime(__DIR__ . '/../public/assets/admin.css') ?>">
</head>
<body>
<section class="hero is-fullheight">
<div class="hero-body">
<div class="container quizsala-container-estreito-admin">
<div class="box">
<div class="level mb-4">
<div class="level-left">
<p class="title is-5 mb-0">Área do professor</p>
</div>
<div class="level-right">
<button type="button" class="button is-small is-light" data-alternar-tema aria-label="Alternar tema claro/escuro">
<span class="icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg></span>
</button>
</div>
</div>
<?php if ($erro !== null): ?>
<p class="help is-danger"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>
<form method="post">
<div class="field">
<label class="label" for="senha_admin">Senha</label>
<div class="control">
<input class="input" type="password" id="senha_admin" name="senha_admin" autofocus>
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
<script src="/assets/tema.js?v=<?= filemtime(__DIR__ . '/../public/assets/tema.js') ?>" defer></script>
</body>
</html>
    <?php
    exit;
```

Note: `name="senha_admin"` continua idêntico — `exigirAdmin()` lê `$_POST['senha_admin']` (linha 25 do arquivo original, não mostrada aqui, não muda).

- [ ] **Step 2: Em `public/assets/admin.css`, adicionar esta regra logo após `.quizsala-topo-menu-admin .marca-admin { margin-bottom: 0; }` (adicionada na Task 1)**

```css
.quizsala-container-estreito-admin { max-width: 24rem; }
```

- [ ] **Step 3: Verificar**

```bash
php -l src/auth.php
grep -c "quizsala-container-estreito-admin" public/assets/admin.css
```

Expected: `No syntax errors detected`; grep retorna `1`.

- [ ] **Step 4: Rodar a bateria de testes E2E** (Caso 16-18 de `bin/teste.sh` testam login direto)

```bash
bash bin/teste.sh 2>&1 | tail -5
```

Expected: `Falhou: 6` (mesmas falhas pré-existentes — nenhuma delas é dos Casos 16-18 de login, essas continuam passando).

- [ ] **Step 5: Commit**

```bash
git add src/auth.php public/assets/admin.css
git commit -m "$(cat <<'EOF'
feat: migra tela de login do admin (auth.php) pro Bulma

Mesmo padrao do index.php (Plano 2) - hero/box/field. name="senha_admin"
continua identico (e o que exigirAdmin() le em \$_POST). Nao removeu
nenhuma regra velha de admin.css (.tela-admin/.cartao-admin/.botao-acao
etc. continuam usadas por outras paginas admin, fora do escopo deste
plano).
EOF
)"
```

---

## Self-Review

**Spec coverage:** cobre o shell compartilhado (usado por 7 páginas) e a tela de login — as duas peças de infraestrutura visual que todo o resto do admin de mesa herda. A migração dos formulários/listas específicos de cada página (`provas.php`, `questoes.php` etc.) fica pros próximos planos, deliberadamente, pra manter cada plano revisável isoladamente.

**Placeholder scan:** nenhum "TBD" — todo trecho está completo e literal.

**Type consistency:** `data-alternar-tema` é o mesmo atributo dos Planos 2-4. `name="senha_admin"` preservado exatamente como `exigirAdmin()` espera.
