# Migração Bulma — Plano 1: Vendorizar Bulma customizado Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gerar um `bulma.css` compilado com a paleta e o raio de borda do QuizSala (vermelho `#d9342b`, zero radius), vendorizado como arquivo estático em `public/assets/vendor/`, pronto pra ser referenciado pelas telas nos próximos planos.

**Architecture:** Compilação Sass única, feita agora com `npm`/`npx sass` num diretório fora do repo (nada de `node_modules` commitado, nada de build step no app em produção). A saída é um `.css` estático de ~20 mil linhas, igual a qualquer outro asset em `public/assets/`. O `.scss` fonte (pequeno, só overrides) fica versionado como documentação de como recompilar.

**Tech Stack:** Bulma 1.0.4 (pinado), Dart Sass via `npx sass` (só usado nesta etapa de build, não é dependência do app).

## Global Constraints

- Sem internet no ambiente de uso do app (`CLAUDE.md`) — o CSS final tem que ser um arquivo estático servido localmente, sem `@import` de CDN.
- Sem build step no app (`CLAUDE.md`) — nada de `package.json`/`node_modules` commitado no projeto; a compilação é uma etapa manual, documentada, não automática.
- Cor primária: `#d9342b`. Zero `border-radius` em todo o sistema (`DESIGN.md`).
- Todo arquivo `.md` do projeto em pt-BR (`CLAUDE.md`).

---

### Task 1: Compilar o Bulma customizado e vendorizar

**Files:**
- Create: `public/assets/vendor/bulma.css` (arquivo compilado, servido pelo app)
- Create: `public/assets/vendor/bulma-quizsala.scss` (fonte da customização, documentação de como recompilar — não é executado pelo app)

**Interfaces:**
- Produces: `public/assets/vendor/bulma.css` — folha de estilo completa do Bulma 1.0.4, com `--bulma-primary-h: 3deg` (hue de `#d9342b`) e `--bulma-radius: 0` já aplicados via CSS custom properties. Planos seguintes referenciam esse arquivo com `<link rel="stylesheet" href="assets/vendor/bulma.css?v=<?= filemtime(...) ?>">` (mesmo padrão de cache-busting já usado no resto do projeto).

- [ ] **Step 1: Compilar num diretório temporário**

```bash
rm -rf /tmp/bulma-vendor-build && mkdir -p /tmp/bulma-vendor-build && cd /tmp/bulma-vendor-build
npm install bulma@1.0.4 --silent
```

Expected: `node_modules/bulma` criado, sem erro.

- [ ] **Step 2: Escrever o `.scss` de entrada com os overrides do QuizSala**

```bash
cat > /tmp/bulma-vendor-build/entrada.scss << 'EOF'
@use "node_modules/bulma/sass" with (
  $primary: #d9342b,
  $radius-small: 0,
  $radius: 0,
  $radius-medium: 0,
  $radius-large: 0,
  $radius-rounded: 0
);
EOF
```

- [ ] **Step 3: Compilar e verificar que a cor e o radius aplicaram**

```bash
cd /tmp/bulma-vendor-build && npx sass entrada.scss bulma.css --no-source-map
grep -c -- "--bulma-primary-h: 3deg" bulma.css
grep -c -- "--bulma-radius: 0" bulma.css
wc -l bulma.css
```

Expected: os dois `grep -c` retornam `1` ou mais (achado em pelo menos um seletor), `wc -l` mostra um arquivo grande (~20 mil linhas — é o Bulma inteiro, normal).

- [ ] **Step 4: Copiar pro repo e documentar a receita de recompilação**

```bash
mkdir -p "public/assets/vendor"
cp /tmp/bulma-vendor-build/bulma.css "public/assets/vendor/bulma.css"
```

Criar `public/assets/vendor/bulma-quizsala.scss` com este conteúdo exato:

```scss
// Fonte da customizacao do Bulma usada em public/assets/vendor/bulma.css.
// Este arquivo NAO e compilado pelo app (sem build step em producao,
// CLAUDE.md) - e so a receita documentada de como o bulma.css foi gerado,
// pra recompilar se um dia mudar uma variavel (cor, radius etc.).
//
// Como recompilar (uma vez, manualmente):
//   1. mkdir -p /tmp/bulma-vendor-build && cd /tmp/bulma-vendor-build
//   2. npm install bulma@1.0.4 --silent
//   3. cp <este arquivo> entrada.scss
//   4. npx sass entrada.scss bulma.css --no-source-map
//   5. cp bulma.css <raiz-do-projeto>/public/assets/vendor/bulma.css
//   6. rm -rf /tmp/bulma-vendor-build
//
// $primary: vermelho de leitura optica do QuizSala (DESIGN.md).
// $radius-*: zerados - sistema totalmente flat, sem cantos decorativos
// nem sombra (Regra do Papel Plano, DESIGN.md).
@use "node_modules/bulma/sass" with (
  $primary: #d9342b,
  $radius-small: 0,
  $radius: 0,
  $radius-medium: 0,
  $radius-large: 0,
  $radius-rounded: 0
);
```

- [ ] **Step 5: Limpar o diretório temporário**

```bash
rm -rf /tmp/bulma-vendor-build
```

- [ ] **Step 6: Verificar que o arquivo commitado bate com o compilado**

```bash
grep -c -- "--bulma-primary-h: 3deg" "public/assets/vendor/bulma.css"
grep -c -- "--bulma-radius: 0" "public/assets/vendor/bulma.css"
```

Expected: ambos `>= 1`, igual ao Step 3.

- [ ] **Step 7: Commit**

```bash
git add public/assets/vendor/bulma.css public/assets/vendor/bulma-quizsala.scss
git commit -m "$(cat <<'EOF'
feat: vendoriza Bulma 1.0.4 customizado (vermelho QuizSala, sem radius)

Compilado uma vez com npm/sass fora do repo - nao vira build step do
app (CLAUDE.md: sem internet no ambiente de uso, sem build step).
bulma-quizsala.scss documenta a receita de recompilacao caso alguma
variavel precise mudar no futuro.
EOF
)"
```

---

## Self-Review

**Spec coverage:** a spec (`docs/superpowers/specs/2026-07-24-migracao-bulma-design.md`) pede "compilar o Bulma customizado uma vez... gerar `public/assets/vendor/bulma.css`... guardar o `.scss` fonte pequeno" — os dois arquivos do Task 1 cobrem isso integralmente. Os demais itens da spec (migração de markup por tela, docs) ficam para os Planos 2-5, listados no plano-mestre.

**Placeholder scan:** nenhum "TBD"/"implementar depois" — todo comando foi rodado e verificado nesta sessão antes de escrever o plano.

**Type consistency:** N/A (sem código de aplicação nesta etapa, só geração de asset estático).
