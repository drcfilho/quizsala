# Migração Bulma — Plano 4: Tela do projetor (tela.php) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Adicionar o Bulma vendorizado + alternador de tema no painel do projetor.

**Architecture:** `tela.css` foi lido por completo — é 100% tipografia gigante/específica do projetor (QR, código, contador, temporizador, barras de distribuição, resumo final), sem nenhum botão, formulário ou card genérico. Não existe nada nessa tela que o Bulma cubra melhor do que o CSS já existente. O único trabalho real é a infraestrutura de tema (igual aos Planos 2 e 3) — o botão fica fixo num canto, fora de `#conteudo-painel` (que `tela.js` redesenha do zero a cada poll), pra sobreviver aos redesenhos.

**Tech Stack:** Bulma vendorizado (`public/assets/vendor/bulma.css`, Plano 1), `tema.js` (Plano 2).

## Global Constraints

- **Nada em `tela.css` muda** — confirmado por leitura completa do arquivo, é inteiramente identidade visual do "placar ao vivo" (`DESIGN.md`), sem componente genérico pra migrar.
- `#painel` e `#conteudo-painel` são os ids que `tela.js` usa (`document.getElementById`) — não podem mudar.
- Cache-busting: todo `<link>`/`<script>` novo segue `?v=<?= filemtime(...) ?>`.

---

### Task 1: Bulma + alternador de tema em `tela.php`

**Files:**
- Modify: `public/tela.php` (arquivo inteiro, 16 linhas)
- Modify: `public/assets/tela.css` — adicionar `.quizsala-alternar-tema-projetor` (nova regra, não remove nada)

**Interfaces:**
- Consumes: `public/assets/vendor/bulma.css` (Plano 1), `public/assets/tema.js` (Plano 2).

- [ ] **Step 1: Reescrever `public/tela.php` com este conteúdo exato**

```php
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala</title>
<link rel="stylesheet" href="assets/vendor/bulma.css?v=<?= filemtime(__DIR__ . '/assets/vendor/bulma.css') ?>">
<link rel="stylesheet" href="assets/tela.css?v=<?= filemtime(__DIR__ . '/assets/tela.css') ?>">
</head>
<body>
<button type="button" class="button is-small is-light quizsala-alternar-tema-projetor" data-alternar-tema aria-label="Alternar tema claro/escuro">
<span class="icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg></span>
</button>
<main class="painel" id="painel" data-fase="carregando">
<div id="conteudo-painel"></div>
</main>
<script src="assets/tema.js?v=<?= filemtime(__DIR__ . '/assets/tema.js') ?>" defer></script>
<script src="assets/tela.js?v=<?= filemtime(__DIR__ . '/assets/tela.js') ?>" defer></script>
</body>
</html>
```

O botão fica **fora** de `<main id="painel">` de propósito — `tela.js` limpa e redesenha `#conteudo-painel` a cada poll (2s), então qualquer coisa dentro dele que não venha do próprio `tela.js` seria apagada no próximo ciclo.

- [ ] **Step 2: Em `public/assets/tela.css`, adicionar esta regra logo após o bloco `:root { ... }`**

```css
.quizsala-alternar-tema-projetor {
    position: fixed;
    top: 1.5vh;
    right: 1.5vw;
    z-index: 10;
}
```

- [ ] **Step 3: Verificar**

```bash
php -l public/tela.php
grep -c "quizsala-alternar-tema-projetor" public/assets/tela.css
```

Expected: `No syntax errors detected`; grep retorna `2`.

- [ ] **Step 4: Rodar a bateria de testes E2E**

```bash
bash bin/teste.sh 2>&1 | tail -5
```

Expected: `Falhou: 6` (as mesmas 6 falhas pré-existentes, não relacionadas).

- [ ] **Step 5: Commit**

```bash
git add public/tela.php public/assets/tela.css
git commit -m "$(cat <<'EOF'
feat: Bulma + alternador de tema no painel do projetor

tela.css e inteiramente identidade visual do placar ao vivo (QR,
contador gigante, temporizador, barras de distribuicao) - nada nela
muda, o Bulma so entra como reset/base + o botao de tema. Botao fica
fora de #conteudo-painel de proposito: tela.js redesenha esse container
do zero a cada poll (2s).
EOF
)"
```

---

## Self-Review

**Spec coverage:** a spec previa que o projetor teria pouco ou nenhum componente Bulma-substituível — confirmado na prática. Este plano cobre a única coisa em escopo real: tema.

**Placeholder scan:** nenhum "TBD" — conteúdo completo e literal.

**Type consistency:** `data-alternar-tema` é o mesmo atributo dos Planos 2 e 3.
