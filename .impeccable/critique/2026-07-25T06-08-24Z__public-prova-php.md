---
target: public/prova.php
total_score: 28
p0_count: 1
p1_count: 1
timestamp: 2026-07-25T06-08-24Z
slug: public-prova-php
---
Method: dual-agent (A: general-purpose design-review agent · B: general-purpose detector/browser-evidence agent)

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 1/4 | Live-confirmed P0: a brand-new session renders a completely blank screen — see Priority Issues |
| 2 | Match Between System and Real World | 4/4 | Plain Portuguese throughout, no jargon |
| 3 | User Control and Freedom | 2/4 | Optimistic mark is reversible pre-reveal, but no distinction between "sending" and "sent" |
| 4 | Consistency and Standards | 4/4 | Bubble/alternativa/header pattern identical across every question; print mirrors on-screen dual-signal logic |
| 5 | Error Prevention | 2/4 | Good rollback on mis-tap, but zero confirmation before `window.print()` on "Salvar comprovante" |
| 6 | Recognition Rather Than Recall | 4/4 | Nothing to recall — question, options, header state all visible at once |
| 7 | Flexibility and Efficiency of Use | 3/4 | Single obvious flow, appropriate for a zero-instruction persona |
| 8 | Aesthetic and Minimalist Design | 4/4 | One accent color, no ornamentation |
| 9 | Error Recovery | 2/4 | `mostrarAviso()` messages are plain-language and non-blocking, but the blank-screen bug has zero error messaging at all |
| 10 | Help and Documentation | 2/4 | Fine for the happy path, but zero self-explanation for the blank/waiting gap |
| **Total** | | **28/40** | **Good** — pulled down almost entirely by the P0 defect; fixing it alone likely pushes this into the low 30s |

## Anti-Patterns Verdict

**Does this look AI-generated? No.**

**LLM assessment**: Deliberate, non-generic. No hero-metric template, no gradient text, no eyebrow labels, no card-grid sameness, no side-stripe borders, no glassmorphism, no confetti/mascot. The monospace/sans dual register and the answer-sheet bubble motif are a genuine point of view.

**Deterministic scan**: CLI scan of `public/prova.php` alone came back clean (exit 0, zero findings). The live browser injection on the rendered page found one finding: `flat-type-hierarchy` — sizes 12px/13.6px/16px cluster within a 1.3:1 ratio. This surfaced only via the live DOM scan, not the static markup scan, and the DOM sample at the time was very sparse (the blank waiting state — only the header/toggle rendered, per the P0 bug below), so this finding should be re-checked once the exam screen is verified in an actual question-rendered state; it may be an artifact of scanning a near-empty page rather than a real hierarchy problem.

**Verified independently** (not just agent-reported): I re-checked the P0 finding myself by reading `public/assets/aluno.js:4` (`var versaoConhecida = 0;`), `public/assets/aluno.js:315` (`if (dados.v !== versaoConhecida)`), `db/schema.sql:60` (`versao INTEGER NOT NULL DEFAULT 0`), and `public/api/estado.php:29` (`if ($vCliente === $versaoAtual) { jsonResponder(['v' => $versaoAtual]); }`). The bug is real and even more precisely located than reported: the server has a short-circuit optimization that returns only `{v: ...}` (no render data) when the client's known version matches the server's — which happens on the very first poll of every brand-new session, since both sides start at `0`.

**No false positives to report** — CLI scan was empty, the one browser finding is flagged above as needing re-verification in the right state rather than dismissed outright.

## Overall Impression

The craft on this screen is genuinely strong where it's been exercised — the optimistic bubble interaction, the dual-signal reveal, the restraint on ornamentation. But there's one defect that overrides all of that: **every single student who opens the link before the professor's first action sees a blank page**, with zero indication anything is happening. Since "QR code goes up, then professor taps Iniciar prova" is the *normal* sequence, this isn't an edge case — it's the default first impression for the persona this screen is built for (zero prior instruction, own phone, mid-class). Everything else on this screen is a refinement; this is the one thing that has to be fixed first.

## What's Working

1. **The optimistic bubble interaction is genuinely excellent.** Instant visual commit on tap, with a graceful rollback if the server disagrees (`aluno.js`, `responder()`) — exactly the "instant and reversible" interaction the brief demanded.
2. **The dual-signal rule is actually implemented, not just documented.** Wrong answers get both a distinct border color *and* fill; correct gets a separate border treatment independent of fill color.
3. **Restraint.** Zero shadows, one accent color used only where the system says it should be — the flat "cartão-resposta" register is followed with real discipline.

## Priority Issues

**[P0] Fresh sessions render a completely blank screen — no message, no header content, nothing**
Why it matters: live-confirmed, not a code-read guess — reproduced directly against a freshly-seeded `aguardando` session. `versaoConhecida` starts at `0` in `aluno.js:4`; every new session is created with `versao = 0` (`db/schema.sql:60`); the server short-circuits to a version-only response when client and server versions match (`api/estado.php:29`) — which is true on the very first poll of every new session. `renderizarEstado()` never runs, not even to show "Aguardando o professor iniciar...". This is the exact scenario PRODUCT.md names as highest-stakes: a student on their own phone, zero instruction, mid-class, professor watching. Every student who opens the link before "Iniciar prova" is tapped (the normal sequence) sees this.
Fix: initialize `versaoConhecida = -1` in `aluno.js` (any sentinel that never legitimately matches a server `v`), so the first poll response always triggers a render regardless of the session's actual version.
Suggested command: `/impeccable audit` (correctness bug, not aesthetic)

**[P1] No on-screen confirmation between "I tapped it" and "the professor revealed it"**
Why it matters: once a student marks a bubble, there's no distinction between "optimistically marked, not yet confirmed" and "server has this on record." DESIGN.md commits to "toda interação otimista precisa saber se desfazer e avisar com clareza quando a rede falha" — the rollback-on-failure exists, but there's no positive confirmation on success, so a silently stalled request looks identical to a confirmed one until the reveal shows the student as unanswered — too late to fix.
Fix: a brief, low-key state change on the bubble once `responder()` resolves successfully, distinguishing "sent" from "sending."
Suggested command: `/impeccable harden`

**[P2] Reveal state has no on-screen textual/iconic right-or-wrong signal — only color + border**
Why it matters: the "✓ Acertou / ✕ Errou" text exists only in the printed comprovante (`aluno.js:224`), not on screen. Border color is present (technically clears DESIGN.md's "borda, ícone ou posição" bar), but it's the weakest form of it — a student with red-green color vision deficiency has to infer meaning from a subtle border-darkness difference, with no textual anchor at all on the screen where it matters most, at the moment it matters most.
Fix: reuse the "✓ Acertou / ✕ Errou" pattern from the print comprovante directly in the on-screen alternativa, on the student's own marked row, once revealed.
Suggested command: `/impeccable clarify`

**[P2] Theme toggle button is a 30×30px touch target — under the product's own 64px rule and WCAG's 44px baseline**
Why it matters: measured live at `{width: 30, height: 30}`. `estilo.css`'s `min-height: 64px` rule explicitly excludes `.is-small` buttons, which the toggle opts into — but PRODUCT.md states the 64px rule with no stated exception ("celular na mão andando ou nervoso durante a prova pede mais folga"). Code and product doc disagree silently.
Fix: bump to at least 44×44px (ideally 64px for consistency), or explicitly document the exception in DESIGN.md as a deliberate, scoped deviation for non-primary controls.
Suggested command: `/impeccable audit`

**[P3] "Salvar comprovante em PDF" triggers `window.print()` with zero warning, mid-flow**
Why it matters: pops a system share/print sheet immediately after the student sees their score, with no explanation of what will happen. Minor — not destructive — but a jarring OS-level interruption at an emotionally loaded moment.
Fix: a one-line caption under the button ("Abre a tela de impressão do celular") rather than a confirmation dialog, to avoid adding friction against "direta e sem frescura."
Suggested command: `/impeccable clarify`

## Persona Red Flags

**Jordan (Confused First-Timer)**: this is the persona the screen is built for, and where it fails hardest. Opens the link before the professor has started anything → blank screen, zero context clues. Very likely concludes the link is broken and either re-scans the QR (possible duplicate participant) or flags the professor down in front of the class — exactly the failure mode PRODUCT.md exists to prevent. Once in the answering state, Jordan does fine — the interaction is self-explanatory.

**Sam (Accessibility-Dependent User)**: reveal state relies on red-vs-green hue as the primary differentiator (P2 above) — real risk for red-green color vision deficiency despite the technical border difference. Positives: `aria-pressed` correctly toggled, `:focus-visible` outline present and never suppressed, `textContent` used throughout (no `innerHTML` risk, good for screen readers). The 30×30px theme toggle is a real target-size miss for anyone with motor impairment.

**"Bia, 14, respondendo no recreio" (QuizSala-specific)**: anonymous participant, aging Android phone, joined via QR code, never used QuizSala before, holding the phone one-handed while half-listening to the teacher. Expects *something* to happen immediately on tapping the link — the blank-screen bug is a direct hit against that expectation, and the missing "sent" confirmation means she has no idea her answer registered before she looks back at the teacher.

## Minor Observations

- `mensagemEstado()` renders waiting text with Bulma's `has-text-grey` utility rather than the system's own `--grafite` token — small departure from the single-source color system, likely visually close enough not to matter.
- "Obrigado por participar!" uses Bulma's `.title.is-4`, pulling in Bulma's default font stack rather than the system-font stack `estilo.css` declares explicitly elsewhere — worth a quick visual check that it doesn't clash.
- The comprovante's dual-signal-on-paper is thoughtfully done (explicit reasoning about b/w printers in a code comment) — makes the missing on-screen equivalent (P2 above) more conspicuous by contrast.
- Dev/test fixture codes (`DESAT01`, `TESTE24`) are 7 characters while `gerarCodigoSala()` always produces 6 — not a production bug, just a note in case a fixture code is ever hand-typed in a demo.

## Questions to Consider

1. If the blank-screen bug went unnoticed until now, was this screen always tested against sessions with an already-nonzero `versao` (e.g., resumed sessions) — which would explain why "Aguardando o professor iniciar..." was seen working in some contexts but not the literal first-ever state?
2. Is the total absence of positive confirmation after marking an answer ("mark and pray") intentional minimalism, or an oversight — given real thought already went into the *negative* case (network failure rollback + toast)?
3. Given "diagnóstico honesto acima de competição," should the on-screen reveal get the same explicit "Acertou/Errou" text the printed comprovante already has, or is color+border-only on the live screen a deliberate choice to keep it quieter than the take-home artifact?
