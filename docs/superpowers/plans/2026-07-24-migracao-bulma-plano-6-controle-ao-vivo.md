# Migração Bulma — Plano 6: Controle ao vivo (admin/sessao.php) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Adicionar o Bulma vendorizado + alternador de tema no controle ao vivo do professor (`admin/sessao.php`) — a tela mobile-first que o professor usa andando pela sala.

**Architecture:** Igual ao Plano 4 (`tela.php`) — `admin/sessao.php` é uma casca quase vazia, todo o conteúdo é redesenhado do zero por `public/assets/admin.js` a cada poll (2s). `admin.js` reusa classes já usadas em outras páginas admin (`.cartao-admin`, `.botao-acao` etc., estratégia aditiva dos Planos 5-6, nada removido) — não é tocado neste plano. Só a casca estática ganha Bulma + tema.

**Tech Stack:** Bulma vendorizado (`public/assets/vendor/bulma.css`, Plano 1), `tema.js` (Plano 2).

## Global Constraints

- `#conteudo-admin` é o id que `admin.js` usa (`document.getElementById`) e redesenha do zero a cada poll — o botão de tema tem que ficar **fora** dele, senão some no próximo ciclo (mesmo motivo do Plano 4).
- `admin/sessao.php` NÃO usa `src/admin_layout.php` (comentário no código: "isolada, mobile-first... sem usar este arquivo") — continua assim, fora de escopo mudar isso.
- Aditivo — nada em `public/assets/admin.css` é removido.
- Cache-busting em todo `<link>`/`<script>` novo.

---

### Task 1: Bulma + alternador de tema em `admin/sessao.php`

**Files:**
- Modify: `public/admin/sessao.php` (arquivo inteiro, 15 linhas)
- Modify: `public/assets/admin.css` — adicionar `.quizsala-alternar-tema-sessao` (nova regra, não remove nada)

**Interfaces:**
- Consumes: `public/assets/vendor/bulma.css` (Plano 1), `public/assets/tema.js` (Plano 2).

- [ ] **Step 1: Reescrever `public/admin/sessao.php` com este conteúdo exato**

```php
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala — Controle</title>
<link rel="stylesheet" href="../assets/vendor/bulma.css?v=<?= filemtime(__DIR__ . '/../assets/vendor/bulma.css') ?>">
<link rel="stylesheet" href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>">
</head>
<body>
<button type="button" class="button is-small is-light quizsala-alternar-tema-sessao" data-alternar-tema aria-label="Alternar tema claro/escuro">
<span class="icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg></span>
</button>
<main class="tela-admin">
<div id="conteudo-admin">Carregando...</div>
</main>
<script src="../assets/tema.js?v=<?= filemtime(__DIR__ . '/../assets/tema.js') ?>" defer></script>
<script src="../assets/admin.js?v=<?= filemtime(__DIR__ . '/../assets/admin.js') ?>" defer></script>
</body>
</html>
```

- [ ] **Step 2: Em `public/assets/admin.css`, adicionar esta regra logo após o bloco `.tela-admin { ... }`**

```css
.quizsala-alternar-tema-sessao {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 10;
}
```

- [ ] **Step 3: Verificar**

```bash
php -l public/admin/sessao.php
grep -c "quizsala-alternar-tema-sessao" public/assets/admin.css
```

Expected: `No syntax errors detected`; grep retorna `2`.

- [ ] **Step 4: Rodar a bateria de testes E2E**

```bash
bash bin/teste.sh 2>&1 | tail -5
```

Expected: `Falhou: 6` (mesmas falhas pré-existentes).

- [ ] **Step 5: Commit**

```bash
git add public/admin/sessao.php public/assets/admin.css
git commit -m "$(cat <<'EOF'
feat: Bulma + alternador de tema no controle ao vivo (admin/sessao.php)

Mesmo padrao do Plano 4 (tela.php) - casca quase vazia, admin.js
redesenha #conteudo-admin do zero a cada poll, entao o botao de tema
fica fora dele. Aditivo: nada em admin.css foi removido, admin.js
continua reusando .cartao-admin/.botao-acao etc. sem mudanca.
EOF
)"
```

---

## Self-Review

**Spec coverage:** cobre o controle ao vivo, a última tela sem Bulma. Junto com os Planos 1-5, todas as 6 telas do produto (aluno, projetor, admin de mesa x7, controle ao vivo) passam a carregar o Bulma vendorizado e têm o alternador de tema.

**Placeholder scan:** nenhum "TBD".

**Type consistency:** `data-alternar-tema` é o mesmo atributo de todos os planos anteriores; `#conteudo-admin` preservado exatamente como `admin.js` espera.
