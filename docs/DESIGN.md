---
name: QuizSala
description: Prova de múltipla escolha ao vivo pra sala de aula sem internet — feedback na hora, pelo projetor
colors:
  vermelho-leitura-optica: "#d9342b"
  acerto: "#1e7a34"
  papel: "#fbfaf7"
  cartao: "#ffffff"
  tinta: "#14171c"
  grafite: "#767c86"
  pauta: "#dcd9d2"
typography:
  titulo:
    fontFamily: "ui-monospace, 'SFMono-Regular', Consolas, monospace"
    fontSize: "1.3rem"
    fontWeight: 400
    letterSpacing: "0.05em"
  rotulo:
    fontFamily: "ui-monospace, 'SFMono-Regular', Consolas, monospace"
    fontSize: "0.75rem"
    fontWeight: 400
    letterSpacing: "0.16em"
  enunciado:
    fontFamily: "system-ui, -apple-system, 'Segoe UI', sans-serif"
    fontSize: "clamp(1.1rem, 4vw, 1.4rem)"
    fontWeight: 400
    lineHeight: 1.4
  corpo:
    fontFamily: "system-ui, -apple-system, 'Segoe UI', sans-serif"
    fontSize: "1rem"
    fontWeight: 400
rounded:
  bulma: "0"
  md: "0.5rem"
  bolha: "50%"
components:
  botao-primario:
    backgroundColor: "{colors.vermelho-leitura-optica}"
    textColor: "#ffffff"
    rounded: "{rounded.bulma}"
    nota: "classe .button.is-primary do Bulma customizado, nao mais escrita a mao"
  campo:
    backgroundColor: "{colors.papel}"
    textColor: "{colors.tinta}"
    rounded: "{rounded.bulma}"
    nota: "classe .input do Bulma customizado"
  alternativa:
    backgroundColor: "{colors.cartao}"
    textColor: "{colors.tinta}"
    rounded: "{rounded.md}"
    nota: "CSS proprio, fora do Bulma - identidade visual especifica do produto"
---

# Design System: QuizSala

## 1. Overview

**Creative North Star: "O Cartão-Resposta Vivo"**

O QuizSala pega o vocabulário de um instrumento de avaliação de verdade — o cartão-resposta de leitura óptica, com suas bolhas pra preencher e marcas de registro — e faz esse documento ganhar vida porque o resultado muda ao vivo, na frente da turma. Duas forças convivem na mesma interface: **direta e sem frescura**, feita pra sala de aula real (Wi-Fi que cai, celular antigo, professor sem tempo — utilitária antes de bonita), e **urgente e viva**, com a energia de um placar de jogo, sobretudo no painel do projetor, onde o número que muda é o próprio produto.

O sistema rejeita explicitamente o vocabulário de gamificação educacional (Kahoot, Duolingo): sem confete, sem mascote, sem ranking de tempo de resposta. Isso não é estética — é consequência direta de uma decisão de produto já travada (`arquitetura.md` §13): o objetivo é diagnóstico honesto da turma, e qualquer elemento de jogo contamina exatamente o que o produto existe pra medir.

**Implementação:** o sistema é construído sobre o [Bulma](https://bulma.io) (framework CSS, `public/assets/vendor/bulma.css`), compilado uma vez em desenvolvimento com variáveis próprias (`$primary: #d9342b`, `$radius: 0` e as demais variáveis de raio zeradas) — as regras deste documento (zero sombra, vermelho único, sem cara de framework genérico) continuam valendo integralmente, só a base de grid/formulário/botão deixou de ser escrita do zero. Elementos sem equivalente no Bulma (a bolha, o placar do projetor, o cronômetro, as barras de distribuição, o QR code, o comprovante de impressão) continuam em CSS próprio, por cima do Bulma — são a identidade visual específica do produto, não algo que um framework genérico cobre. O Bulma também trouxe um efeito colateral bem-vindo: suporte a tema claro/escuro (`data-theme`, alternado manualmente por `public/assets/tema.js`, com um botão em cada tela), lembrado entre sessões via `localStorage`.

**Key Characteristics:**
- Duas famílias com papéis fixos: monoespaçada pro que precisa parecer registro oficial (título, rótulos, contador, letra da bolha), a fonte do sistema pro que é conteúdo de leitura corrida (enunciado, alternativas).
- Zero sombra em todo o sistema — hierarquia vem de borda e peso tipográfico, nunca de profundidade artificial.
- Uma cor de destaque só (vermelho de leitura óptica), nunca decorativa — reservada a foco, aviso e à marca de leitura óptica do cabeçalho.
- Certo/errado na revelação nunca depende só de cor — sempre reforçado por borda e preenchimento junto.

## 2. Colors

Paleta quase monocromática (tinta sobre papel, como um documento impresso) com uma única cor de destaque e um verde reservado exclusivamente ao acerto.

### Primary
- **Vermelho de Leitura Óptica** (`#d9342b`): a única cor de destaque do sistema. Aparece no anel de foco de teclado, no aviso flutuante de erro/reconexão, e na marca de leitura óptica decorativa do cabeçalho da prova. Também usada (borda + preenchimento) na alternativa escolhida quando ela sai errada na revelação.

### Semantic
- **Acerto** (`#1e7a34`): preenche a bolha da alternativa correta na revelação — a única cor do sistema reservada a um significado positivo fixo.

### Neutral
- **Papel** (`#fbfaf7`): fundo geral das telas.
- **Cartão** (`#ffffff`): superfície de campo, alternativa e cartão de entrada — o "papel" de cima.
- **Tinta** (`#14171c`): texto principal, marcação da bolha em estado neutro (antes da revelação), fundo dos botões primários.
- **Grafite** (`#767c86`): rótulos e texto secundário (nome do aluno, contador de questão).
- **Pauta** (`#dcd9d2`): bordas e divisórias — a linha do formulário oficial.

### Named Rules
**A Regra da Cor Única.** O vermelho de leitura óptica nunca decora — só foco, aviso e a marca de registro do cabeçalho. Se uma tela nova "precisar" de mais destaque, a resposta é peso tipográfico ou borda, não uma segunda cor.

**A Regra do Sinal Duplo.** Certo/errado nunca depende só de verde/vermelho — a alternativa correta também ganha borda própria, a errada também mantém a marcação visível ao lado do texto. Um aluno com dificuldade de perceber cor ainda lê o resultado.

## 3. Typography

**Título/Rótulo:** `ui-monospace, "SFMono-Regular", Consolas, monospace`
**Enunciado/Corpo:** `system-ui, -apple-system, "Segoe UI", sans-serif`

**Character:** a monoespaçada é a letra do formulário oficial — é ela que dá o registro de "documento de prova" ao título, ao código da sala e às letras das bolhas. A fonte do sistema carrega tudo que é leitura corrida, sem cerimônia, legível em qualquer aparelho.

### Hierarquia
- **Título** (400, 1.3rem, letter-spacing 0.05em, monoespaçada): nome do produto na tela de entrada.
- **Rótulo** (400, 0.75rem, uppercase, letter-spacing 0.16em, monoespaçada): nome do aluno e contador de questão no cabeçalho da prova, rótulos de campo de formulário.
- **Enunciado** (400, `clamp(1.1rem, 4vw, 1.4rem)`, line-height 1.4, fonte do sistema): a pergunta da questão — cresce em telas maiores sem estourar o cartão.
- **Corpo** (400, 1rem, fonte do sistema): texto de alternativa, botões, campos.

### Named Rules
**A Regra do Registro Duplo.** Monoespaçada é reservada ao que precisa parecer documento oficial (título, rótulos, contador, letra da bolha). A fonte do sistema é reservada a conteúdo de leitura corrida (enunciado, alternativas). Nunca inverter os papéis — um enunciado em monoespaçada leria como código, não como pergunta de prova.

## 4. Elevation

Sistema totalmente flat — zero `box-shadow` em todo o código. Hierarquia e separação vêm de borda de 1px (cor pauta) e peso tipográfico, nunca de profundidade artificial. É deliberado: reforça a leitura de "documento impresso" (cartão-resposta) em vez de "app com camadas flutuantes" — um cartão-resposta de papel não tem sombra.

### Named Rules
**A Regra do Papel Plano.** Nenhum elemento ganha sombra. Se algo precisa se destacar, ganha borda mais forte ou preenchimento de cor — nunca uma elevação artificial.

## 5. Components

### Buttons
- **Shape:** cantos retos (`border-radius: 0` — variável do Bulma zerada, Regra do Papel Plano vale também pros componentes do framework).
- **Primary (`.button.is-primary`, classe do Bulma):** fundo vermelho de leitura óptica, texto branco, `min-height: 64px` (override próprio — o padrão do Bulma é menor), largura total — o botão "Entrar" da tela de entrada, "Salvar comprovante" na tela final.
- **Focus:** `outline: 3px solid` vermelho de leitura óptica em `:focus-visible`, sempre visível, nunca removido sem substituto.

### A bolha (elemento de assinatura)
Círculo de 2.5rem, borda 2px tinta, com um segundo círculo interno (`::after`) que cresce de `scale(0)` a `scale(1)` em `cubic-bezier(.34, 1.4, .64, 1)` — a curva dá uma leve ultrapassagem, como quem sombreia a lápis rápido demais. É a única animação de interface do sistema; `prefers-reduced-motion: reduce` desliga a transição.

Estados: vazia (default, sem preenchimento) → marcada (preenche tinta, antes da revelação) → correta (preenche acerto) → escolhida-certa (preenche acerto) → escolhida-errada (borda e preenchimento vermelho de leitura óptica).

### Alternativa (cartão de escolha)
- **Corner Style:** `0.5rem`.
- **Background:** cartão (branco) sobre o papel da tela.
- **Border:** `1px solid` pauta em repouso; muda para tinta (correta) ou vermelho de leitura óptica (escolhida-errada) na revelação — ver Elevation, a separação é só borda, nunca sombra.
- **Internal Padding:** `0.5rem 1rem`, `min-height: 64px` — alvo de toque generoso pro celular na mão, possivelmente andando ou nervoso durante a prova.

### Inputs / Fields
- **Style:** borda `1px solid` pauta, fundo papel, `border-radius: 0.5rem`, `min-height: 64px`.
- **Campo de código da sala:** mesma base, mais monoespaçada, `text-transform: uppercase` e letter-spacing — reforça que é um código curto de digitar rápido, não um texto livre.
- **Focus:** mesmo `outline` de 3px do botão primário.

### Aviso flutuante
Toast fixo na base da tela: fundo tinta, texto branco, cantos suaves, some sozinho em 2.5s. Único mecanismo de feedback de erro de rede/recusa do servidor — sempre texto explicando o que aconteceu, nunca só um ícone.

## 6. Do's and Don'ts

### Do:
- **Do** manter o sistema inteiramente flat — zero sombra, hierarquia por borda e peso tipográfico (A Regra do Papel Plano).
- **Do** reservar a monoespaçada para título/rótulo/contador/bolha, e a fonte do sistema para enunciado/alternativas/corpo (A Regra do Registro Duplo).
- **Do** reforçar certo/errado com borda e preenchimento junto, nunca só cor isolada (A Regra do Sinal Duplo).
- **Do** manter alvos de toque de 64px em todo elemento interativo — celular na mão, possivelmente andando ou nervoso.
- **Do** desfazer a marcação otimista da bolha com um aviso claro no aviso flutuante quando o servidor recusar a resposta.
- **Do** usar `textContent` para todo texto vindo do banco (enunciado, alternativa, nome) — nunca `innerHTML`.

### Don't:
- **Don't** usar confete, mascote, ranking de tempo de resposta ou qualquer elemento de gamificação tipo Kahoot/Duolingo — contamina o diagnóstico honesto que é o objetivo do produto (anti-referência do `PRODUCT.md`).
- **Don't** introduzir uma segunda cor de destaque além do vermelho de leitura óptica.
- **Don't** adicionar sombra decorativa em card, botão ou item de lista.
- **Don't** usar `border-left`/`border-right` colorido como destaque decorativo.
- **Don't** animar nada sem uma alternativa em `prefers-reduced-motion: reduce`.
- **Don't** escrever enunciado ou alternativa em monoespaçada — essa fonte é só pro registro oficial (título, rótulo, bolha), nunca pro conteúdo da prova.
