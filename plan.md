# QuizSala — Plano de Execução

**Versão:** 1.0
**Complemento de:** `arquitetura.md` (arquitetura e decisões)
**Escopo deste documento:** o que fazer, em que ordem, e como saber que ficou pronto

---

## 1. Critério de pronto da v1

A v1 está entregue quando esta frase for verdadeira:

> O professor chega na sala, liga o roteador, roda um comando, projeta o QR Code, e aplica uma prova de 5 questões para 25 alunos em 10 minutos — vendo a contagem de acertos e erros na tela a cada questão — sem tocar no teclado do notebook depois de começar.

Cada cláusula dessa frase é um teste. Se qualquer uma falhar num ensaio real, a v1 não está pronta.

### Fora do critério

Aplicar prova em modo assíncrono, exportar resultados, cadastrar alunos, funcionar com 100 pessoas. Tudo isso é v2 e está no backlog (§8).

---

## 2. Ponto de partida

Já implementado e testado (detalhe em `arquitetura.md` §11):

```
✓ schema + índices + constraints
✓ inicialização do banco com prova de exemplo
✓ entrada anônima e nomeada
✓ polling por versão (7 bytes quando nada muda)
✓ registro de resposta com dedupe e validação
✓ revelação de gabarito individual
✓ tela do aluno completa
✓ bateria de testes bin/teste.sh — 11 casos, todos passando
```

Restam quatro fases. O caminho crítico passa pela F2 (painel), porque é ela que entrega o valor central: **o número na parede**.

---

## 3. Fases

### F1 — Fechar a base *(concluída)*

Fluxo do aluno ponta a ponta. Já está no repositório.

**Verificação:** `bash bin/teste.sh` → 11 casos, zero warning no log do PHP.

---

### F2 — Painel do projetor *(código completo; validação física pendente)* · ~5h

O coração do sistema. Sem isso não existe "feedback na hora".

| # | Tarefa | Verificação |
|---|---|---|
| 2.1 | ~~`api/painel.php` — contadores sem gate de versão~~ **feito** | curl retorna `online`, `responderam`, `distribuicao`, `acertos`, `erros` — testado, bate com o banco |
| 2.2 | ~~`tela.php` — questão + contador ao vivo~~ **feito** | contador sobe em ≤2s — testado no navegador com aluno real fazendo poll contínuo (não só 3 abas simultâneas) |
| 2.3 | ~~Estado de revelação com barras por alternativa~~ **feito** | números batem com `SELECT` manual no banco — testado (2 acertos, 1 erro) |
| 2.4 | Tipografia de projeção | CSS implementado (`clamp`, alto contraste); **legibilidade a 8 metros com projetor real ainda não testada** — só validado em navegador/monitor |
| 2.5 | ~~Aviso "todos responderam"~~ **feito** | contador bate 100% → inverte pra fundo escuro/texto branco (sem cor nova), sem avançar sozinho — testado |

**Decisão embutida em 2.3:** a distribuição por alternativa só aparece *depois* da revelação. Mostrar antes enviesa quem ainda não respondeu — o aluno vê a barra grande e segue a manada.

**Verificação da fase:** testado com fluxo completo (aluno real no navegador + simulação via curl para volume), mas **não com 3 celulares físicos reais nem projetor de verdade** — isso fica para o ensaio (M3/T20).

---

### F3 — Controle ao vivo *(3.1-3.4 feitos; falta 3.5)* · ~4h

O professor precisa comandar a sessão sem voltar ao notebook.

| # | Tarefa | Verificação |
|---|---|---|
| 3.1 | ~~`admin/sessao.php` — três botões grandes: Revelar · Próxima · Encerrar~~ **feito** | testado via API (curl) e navegador; toque físico num celular real ainda não |
| 3.2 | ~~Incremento de `versao` em toda transição~~ **feito** | testado — poll do painel detecta e redesenha em ≤2s |
| 3.3 | ~~Painel de presença ("24 online, 18 responderam")~~ **feito** | número bate com o do projetor (mesma fonte, `api/painel.php`) |
| 3.4 | ~~Proteção contra toque duplo~~ **feito** | testado com 2 requisições paralelas de verdade — só uma aplicou |
| 3.5 | Abrir sessão: escolher prova, modo e identificação | pendente (T08 do `tasks.md`) |

**3.4 não é paranoia:** professor com celular na mão, andando pela sala, toca duas vezes. A guarda é comparar a versão esperada — o segundo toque vira no-op em vez de pular uma questão na frente da turma.

**Verificação da fase:** aplicar uma prova de 3 questões inteira usando **só** o celular do professor — feito via simulação (curl + navegador), **não com um celular físico real ainda**.

---

### F4 — Admin de provas · ~7h

A parte mais chata e a de maior volume de código. Vem depois de propósito: sem ela dá para aplicar prova (seed no banco), sem F2 e F3 não dá.

| # | Tarefa | Verificação |
|---|---|---|
| 4.1 | Listar e criar provas | prova aparece na lista |
| 4.2 | Editor de questão: enunciado + 5 alternativas + marcar a correta | salva e recarrega íntegra |
| 4.3 | Validação: exatamente uma correta, mínimo 2 alternativas | tentar salvar inválida → erro claro no campo |
| 4.4 | Reordenar e excluir questões | `ordem` fica contígua após exclusão |
| 4.5 | Duplicar prova | cópia independente; editar a cópia não afeta a original |
| 4.6 | Layout mobile-first | criar uma prova inteira pelo celular sem zoom |

**4.4 tem uma armadilha:** excluir a questão 2 de 4 deixa `ordem` como 1, 3, 4. O avanço da sessão percorre por `ordem`, então precisa renumerar na exclusão — senão a sessão "pula" uma questão que não existe e a tela fica em branco na frente da turma.

**Verificação da fase:** criar uma prova do zero pelo celular e aplicá-la ponta a ponta, sem tocar no banco.

---

### F5 — Operação em sala · ~4h

O que transforma código em ferramenta usável.

| # | Tarefa | Verificação |
|---|---|---|
| 5.1 | ~~QR Code do endereço, gerado offline~~ **feito** | celular real entra pelo QR, sem digitar nada — testado via decodificação real do PNG (curl + OpenCV); celular físico ainda não |
| 5.2 | ~~Tela de espera com QR grande + código~~ **feito** | legível do fundo da sala — testado no navegador (contador ao vivo, QR estável sem recarregar); projetor real ainda não |
| 5.3 | Script de start (`iniciar.bat` / `iniciar.sh`) | duplo clique sobe o servidor e abre o projetor |
| 5.4 | Documento de setup do roteador e IP fixo | outra pessoa consegue montar seguindo o passo a passo |
| 5.5 | Encerrar sessão e limpar participantes | `CASCADE` remove tudo; prova permanece |

**Sobre 5.1:** gerador de QR precisa ser **local**. API de QR na internet é exatamente o tipo de dependência que quebra na hora da aula. Uma biblioteca PHP de arquivo único resolve.

---

## 4. Sequenciamento

```
F1 ✓ ──► F2 (painel) ──► F3 (controle) ──► F5 (operação)
                              │
                              └──► F4 (admin de provas)
```

F4 é paralela a F5 e **não bloqueia o piloto** — dá para aplicar prova com conteúdo inserido via seed. Por isso ela vem depois no cronograma mesmo tendo o maior volume.

A ordem é deliberada: cada fase deixa o sistema **utilizável em algum cenário real**, em vez de deixar tudo pela metade até o final.

| Após | O sistema já serve para |
|---|---|
| F2 | aplicar uma questão avulsa, controlando pelo notebook |
| F3 | aplicar uma prova inteira, comandando pelo celular |
| F4 | criar conteúdo sem tocar no banco |
| F5 | qualquer professor usar, não só quem construiu |

---

## 5. Estimativa

| Fase | Horas | Acumulado |
|---|---|---|
| F1 base | ✓ | — |
| F2 painel | 5 | 5 |
| F3 controle ao vivo | 4 | 9 |
| F4 admin de provas | 7 | 16 |
| F5 operação em sala | 4 | 20 |
| Ensaio + correções | 3 | **23** |

**~23 horas** até a v1 utilizável. Estimativa de desenvolvedor único, sem interrupção, em stack conhecida.

A linha "ensaio + correções" não é gordura: é a fase em que aparecem os problemas que nenhum teste automatizado pega — projetor com cor lavada, celular velho que não renderiza, Wi-Fi caindo. Cortar essa linha é o jeito mais rápido de estrear com uma falha na frente da turma.

---

## 6. Marcos

### M1 — Primeira questão na parede *(fim da F2)*

Três celulares respondem, o projetor mostra a contagem correta.

É aqui que dá para julgar se a ideia funciona. Se o feedback na tela não gerar reação na sala, o problema é de conceito e vale repensar antes de investir nas outras 15 horas.

### M2 — Prova inteira pelo celular *(fim da F3)*

Aplicar 3 questões sem encostar no notebook.

### M3 — Ensaio com turma real *(após F5)*

Uma turma pequena, prova curta, sem aviso prévio de que é teste. Métricas a observar:

- quantos alunos entraram sem ajuda?
- quantos caíram pro 4G?
- quanto tempo do início da aula até a primeira questão no ar?
- alguém ficou sem responder por problema técnico?

Meta: **setup em menos de 3 minutos** e **nenhum aluno excluído por falha técnica**.

### M4 — Uso em aula normal

Aplicação real, sem o autor monitorando. Se sobreviver a isso, a v1 acabou.

---

## 7. Decisões pendentes

Bloqueiam fases específicas. Vale resolver antes de começar cada uma.

| # | Decisão | Bloqueia | Nota |
|---|---|---|---|
| P1 | Roteador dedicado ou hotspot do notebook? | F5 | Roteador é mais confiável; hotspot do Windows limita a 8 aparelhos |
| P2 | Quantos alunos no pior caso? | F2 | Acima de ~40, migrar de `php -S` para Apache |
| P3 | O professor projeta ou usa a TV da sala? | F2.4 | Muda o tamanho da tipografia e o contraste |
| P4 | Aluno atrasado entra no meio da prova? | F3 | Design atual permite; confirmar se é o desejado |
| P5 | Precisa exportar resultado da aula? | backlog | Se sim, sobe da v2 para a v1 — muda o encerramento da sessão |

**P4 merece atenção:** hoje o aluno que entra na questão 3 só responde da 3 em diante, e o denominador da turma muda no meio. Isso é aceitável para diagnóstico, mas não para avaliação com nota. Depende do uso pretendido.

---

## 8. Backlog pós-v1

Priorizado por valor sobre custo, não por ordem de ideia.

| Prioridade | Item | Custo | Motivo |
|---|---|---|---|
| Alta | Exportar CSV da sessão | 2h | Professor quer guardar o diagnóstico |
| Alta | Modo assíncrono | 6h | Já pedido; schema pronto, falta o fluxo |
| Média | Captive portal | 5h | Resolve o "sem internet" de vez, mas exige controle do roteador |
| Média | Questão com imagem | 4h | Necessário para geografia, biologia, gráficos |
| Média | Importar prova de texto/CSV | 3h | Aproveita banco de questões existente |
| Média | Log de acesso à prova, com hash de identificação do aparelho | 3h | Pedido do usuário. Sistema de captura de stats: cada acesso à página da prova vira uma linha de log, com um hash que identifica aquele celular/PC/tablet especificamente |
| Baixa | Tempo de resposta por aluno | 2h | Vira competição; contraria o objetivo de diagnóstico honesto |
| Baixa | Múltiplas salas simultâneas | 4h | Só faz sentido com mais de um professor no mesmo servidor |

O "tempo de resposta" está registrado como baixa prioridade **de propósito**. É a primeira coisa que se pensa em adicionar e a que mais distorce o que o sistema mede: aluno com medo de errar rápido responde qualquer coisa.

**Sobre o log de hash de aparelho:** encaixa bem no diagnóstico técnico do piloto (T20 já mede manualmente "quantos aparelhos caíram pro 4G" e "quantos alunos entraram sem ajuda" — um log automatizado cobre isso sem precisar de observação humana durante a aula). Mas esbarra numa decisão de design já tomada: o valor da v1 está em parte no anonimato (§10 — "Histórico entre aulas: exigiria cadastro de aluno, e o valor está no anonimato"). Pra não contradizer isso, o hash tem que identificar o **aparelho**, não a **pessoa** — não pode ser cruzável com nome/token do aluno nem persistir entre aulas diferentes de forma rastreável. Como gerar esse hash (IP+User-Agent com sal por sessão? algo no `localStorage` do navegador, que o aluno pode limpar?) e onde gravar o log (arquivo separado do banco principal, pra não misturar dado operacional com dado de diagnóstico) ficam em aberto — resolver antes de implementar, não no meio.

---

## 9. Riscos de cronograma

| Risco | Impacto | Mitigação |
|---|---|---|
| Admin de provas cresce sem controle (F4) | +5h fácil | Escopo travado: sem rich text, sem upload, sem preview. Texto puro. |
| Ajuste de tipografia do projetor vira loop | +3h | Testar com o projetor **real** na primeira tentativa, não iterar no monitor |
| `php -S` engasga acima do previsto | +2h | Plano B pronto: Apache do Laragon, zero mudança de código |
| Celular antigo não renderiza | +3h | Testar num Android de 5 anos antes da F5, não depois |
| Piloto revela problema de conceito | refazer | É para isso que o M1 existe cedo |

---

## 10. Fora do plano

Explicitamente não construir na v1, e o motivo:

| Item | Motivo |
|---|---|
| Login de professor | Rede local fechada; senha atrapalha mais do que protege |
| Histórico entre aulas | Exigiria cadastro de aluno, e o valor está no anonimato |
| Anti-cola | Não para quem quer burlar; irrita quem não quer |
| App nativo | O navegador resolve; instalar app numa sala inteira não |
| Multi-tenant | É ferramenta de um professor num notebook, não SaaS |

O último merece nota: a tentação de transformar isso num SaaS multi-tenant existe e é real. Mas a v1 tem valor justamente por ser um arquivo SQLite num pendrive. Fazer a arquitetura multi-tenant agora custa caro e resolve um problema que ainda não apareceu — **se aparecer**, o schema já isola por `sessao_id`, e a migração é evolutiva, não uma reescrita.
