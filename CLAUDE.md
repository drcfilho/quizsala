# QuizSala

## Design Context

Contexto estratégico e visual completo em [`PRODUCT.md`](PRODUCT.md) e [`DESIGN.md`](DESIGN.md) — leia antes de qualquer mudança de UI.

Resumo:
- **Registro:** product (ferramenta de sala de aula em todas as superfícies — tela do aluno, painel do projetor, admin do professor). Pode existir uma página de divulgação (`brand`) separada no futuro.
- **Plataforma:** web (PHP + SQLite, JS puro, sem build step, sem internet no ambiente de uso).
- **Públicos:** aluno (tela de prova, anônimo/nomeado, celular, sem instrução prévia) e professor (painel/admin, controla tudo pelo próprio celular andando pela sala).
- **Personalidade:** direta e sem frescura (utilitária, feita pra sala de aula real) + urgente e viva (energia de placar ao vivo, sobretudo no painel do projetor).
- **Norte criativo do visual:** "O Cartão-Resposta Vivo" — vocabulário de cartão-resposta óptico (bolhas, monoespaçada, marcas de registro) ganhando vida em tempo real.
- **Nunca:** parecer app de gamificação educacional (Kahoot/Duolingo) — sem confete, mascote ou ranking de tempo de resposta, decisão já travada em `arquitetura.md` §13.
- **Regra crítica de acessibilidade:** certo/errado na revelação de gabarito nunca depende só de cor (verde/vermelho) — sempre reforçado por borda também.
- **Sistema visual:** totalmente flat (zero sombra), uma única cor de destaque (vermelho de leitura óptica, `#d9342b`), alvos de toque de 64px.
