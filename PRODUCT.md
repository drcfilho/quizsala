# Product

## Register

product

## Platform

web

## Users

Dois públicos, dois papéis opostos na mesma sessão.

O **aluno** usa só a tela de prova: chega pelo QR Code ou link, no próprio celular, sem instrução prévia e sem conta — precisa entender sozinho em segundos, respondendo entre uma explicação e outra do professor. Pode ser anônimo (aula normal) ou nomeado (avaliação), decisão que pertence à sessão, não ao aluno.

O **professor** comanda tudo pelo próprio celular, andando pela sala — nunca volta ao notebook depois de abrir a sessão. Controla o ritmo (revelar, próxima questão, encerrar), monta o conteúdo das provas, e olha o painel projetado para decidir quando revelar.

## Product Purpose

Professor quer feedback imediato da turma durante a aula, numa sala **sem internet**. Aplica uma questão de múltipla escolha, cada aluno responde pelo próprio celular, e a contagem de acertos e erros aparece na hora na tela projetada — sem esperar corrigir provas em casa pra saber se a turma entendeu.

Sucesso: o professor chega na sala, liga o roteador, roda um comando, projeta o QR Code, e aplica uma prova de 5 questões para 25 alunos em 10 minutos — vendo a contagem de acertos e erros a cada questão — sem tocar no teclado do notebook depois de começar.

## Positioning

Quatro prioridades de peso igual, nenhuma secundária às outras:

- a tela do aluno responde em segundos, sem fricção nem hesitação;
- o painel do projetor é legível a 8 metros, com a luz da sala acesa — é "o número na parede" que faz o sistema valer a pena;
- o professor controla a sessão inteira por toque no próprio celular, sem nunca voltar ao notebook;
- tudo isso parece um instrumento oficial de avaliação — um cartão-resposta de verdade — nunca um app casual.

## Brand Personality

Duas forças que convivem na mesma interface: **direta e sem frescura**, feita pra sala de aula real (Wi-Fi que cai, celular antigo, professor sem tempo — utilitária antes de bonita) e **urgente e viva**, com a energia de um placar de jogo ao vivo, sobretudo no painel do projetor, onde o número que muda em tempo real é o próprio produto.

As duas forças moram dentro do vocabulário já travado no `arquitetura.md`: cartão-resposta de prova (bolhas de leitura óptica, tipografia monoespaçada pro registro oficial, marcas de registro). Essa referência é suficiente por si só — sem inspiração externa adicional.

## Anti-references

Nunca deveria parecer um app de gamificação educacional (Kahoot, Duolingo): sem confete, sem mascote, sem sensação de jogo casual. O `arquitetura.md` já rejeita ranking por tempo de resposta e mecanismo anti-cola pelo mesmo motivo (§13): o objetivo é diagnóstico honesto da turma, não competição — gamificar contamina exatamente o que o produto existe para medir.

## Design Principles

- **Diagnóstico honesto acima de competição.** Nunca ranking por tempo de resposta, nunca elemento que recompense velocidade sobre acerto — isso distorce o que o professor está tentando medir.
- **Robusto antes de bonito.** A rede da sala cai, o celular é antigo, o professor está andando. Toda interação otimista precisa saber se desfazer e avisar com clareza quando a rede falha.
- **Legível a 8 metros vence qualquer refinamento.** O painel do projetor é o coração do produto — contraste alto e tipografia grande têm prioridade sobre qualquer sutileza visual.
- **Controle nunca volta ao notebook.** Toda decisão do professor durante a aula cabe num toque no próprio celular.
- **Parece documento oficial, não aplicativo casual.** A estética de cartão-resposta (bolhas, monoespaçada, marcas de registro) é deliberada e consistente em toda tela — do aluno ao painel do professor.

## Accessibility & Inclusion

WCAG AA. Alvos de toque de 64px (já implementado, acima do mínimo de 44px — celular na mão andando ou nervoso durante a prova pede mais folga). Foco de teclado visível com `outline` de 3px na cor de destaque. Certo/errado na revelação de gabarito nunca depende só de verde/vermelho — sempre reforçado por borda, ícone ou posição, para não depender da percepção de cor do aluno. `prefers-reduced-motion: reduce` desliga a animação da bolha (única animação de interface do sistema).
