# QuizSala — Documento de Design

**Versão:** 1.0
**Status:** núcleo do fluxo do aluno implementado e testado
**Autor:** Daniel

---

## 1. Problema

Professor quer feedback imediato da turma durante a aula. Aplica uma questão de múltipla escolha, cada aluno responde pelo próprio celular, e a contagem de acertos e erros aparece na hora na tela projetada.

O ambiente é uma sala de aula **sem internet**: um roteador Wi-Fi aberto liga os celulares a um notebook que roda o servidor.

### O que o sistema é

Uma ferramenta de aula. Rápida de abrir, rápida de aplicar, descartável entre turmas.

### O que o sistema não é

Não é plataforma de avaliação formal. Não tenta impedir cola, não persiste histórico do aluno entre sessões, não emite nota. Qualquer requisito nessa direção muda o desenho inteiro.

---

## 2. Contexto de execução

```
   [celular]  [celular]  [celular]  ...
        \         |         /
         \        |        /
        ( roteador Wi-Fi aberto )        sem uplink de internet
                  |
             [ notebook ]
          PHP + SQLite + projetor
```

### Restrições que vêm do ambiente

| Restrição | Consequência de projeto |
|---|---|
| Sem internet | Zero CDN. Tailwind, Chart.js, fontes e ícones precisam estar no disco. |
| Notebook itinerante | Banco em arquivo único. Nada de serviço externo pra subir. |
| `php -S` é single-thread | Proibido long-polling. Toda requisição precisa terminar em milissegundos. |
| Celulares heterogêneos | Sem build step em produção. HTML/CSS/JS que roda em Android antigo — o CSS de base (Bulma, ver `DESIGN.md`) é vendorizado como arquivo estático, compilado uma vez em desenvolvimento, nunca no ambiente de uso. |
| Aula tem 50 minutos | Setup precisa ser: ligar roteador, rodar um comando, projetar QR Code. |

### A armadilha do "Wi-Fi sem internet"

Android e iOS testam conectividade ao entrar numa rede. Sem resposta, avisam "sem internet" e podem voltar pro 4G sozinhos — e aí o aluno perde o servidor.

**Mitigação v1:** instruir a turma a manter a conexão. O aviso aparece uma vez por aparelho.

**Mitigação v2 (captive portal):** responder aos endpoints de checagem (`/generate_204` no Android, `/hotspot-detect.html` na Apple) com redirect para a prova. Isso faz a tela de login abrir sozinha ao conectar — bom UX, mas exige controle de DNS/roteador. Fora do escopo inicial.

---

## 3. Decisões de arquitetura

### D1 — PHP + SQLite, sem framework

Stack de domínio do autor. SQLite em vez de MySQL porque o sistema roda num notebook que troca de sala: o banco vira um arquivo que cabe no pendrive, sem serviço pra subir.

`PRAGMA journal_mode = WAL` permite leitura concorrente durante escrita. As gravações de resposta duram milissegundos; não são gargalo.

`busy_timeout = 3000` cobre o pico do momento em que 30 alunos respondem quase juntos.

**Tradeoff aceito:** perdemos as ferramentas de administração do MySQL. Irrelevante para um banco de 6 tabelas.

### D2 — Short polling, nunca long polling

WebSocket seria mais natural, mas fugiria da stack.

A regra dura: **`php -S` processa uma requisição por vez.** Uma conexão que fica pendurada esperando evento trava a sala inteira. Portanto short polling de 2 em 2 segundos, cada requisição respondendo imediatamente.

Custo com 30 alunos: ~15 req/s. Com resposta em <10ms, sobra folga.

**Ponto de migração:** acima de ~40 alunos simultâneos, servir por Apache (Laragon/XAMPP), que é multi-thread. Nenhuma linha de código muda.

### D3 — Contador de versão na sessão

O problema do polling ingênuo é trafegar o estado inteiro a cada 2 segundos sem nada ter mudado.

Solução: `sessoes.versao` incrementa a cada mudança de estado comandada pelo professor. O cliente manda a versão que conhece:

```
GET /api/estado.php?token=abc&v=7

  versão ainda é 7  →  {"v":7}                    7 bytes   (medido)
  versão virou 8    →  {"v":8, "fase":..., ...}   ~400 bytes
```

Na prática 99% dos polls custam 7 bytes e uma consulta indexada.

**Limite conhecido:** contadores ao vivo (quantos já responderam) mudam *sem* incrementar a versão, porque quem muda é o aluno, não o professor. Por isso a tela do projetor usa endpoint próprio, sem gate de versão. É uma tela só — o custo é irrelevante.

### D4 — Token opaco por participante

Cada entrada gera um token de 32 hex (`random_bytes(16)`), guardado no `localStorage` do celular.

Resolve o bug que mais estraga contagem de presença: **aluno que recarrega a página vira participante novo.** Com token persistido, o F5 devolve a mesma identidade.

O token trafega pelo **fragmento da URL** (`prova.php#t=...`) no redirect de entrada. Fragmento não vai para o log do servidor nem para o header `Referer`. O JS lê, salva e limpa a URL com `history.replaceState`.

### D5 — Identificação decidida por sessão

`sessoes.identificacao` é `anonimo` ou `nome`, definido ao abrir a sala — não é configuração global.

- **Aula normal → anônimo.** Ninguém tem medo de errar, e o feedback fica honesto.
- **Avaliação → nome.** O professor precisa saber quem é quem.

No modo anônimo o servidor gera apelido sequencial (`Aluno 01`) para o painel ter o que exibir.

### D6 — Presença por heartbeat, nunca como trava

Todo poll atualiza `participantes.last_seen`. Online = visto nos últimos 6 segundos (3× o intervalo, tolera um poll perdido).

```
todos_responderam = respostas(questão atual) >= participantes_online
```

**Isso é conveniência, não regra de avanço.** Celular bloqueado, aluno no banheiro, bateria acabando — a sala não pode ficar refém de um fantasma. O comportamento:

- projetor mostra o contador ao vivo ("18 de 24 responderam");
- ao bater 100%, avança ou destaca o botão;
- o professor **sempre** tem "Revelar agora" no celular.

### D7 — Gabarito só sai do servidor após a revelação

`api/estado.php` inclui o campo `correta` **exclusivamente** quando `fase = revelado`. Antes disso a resposta certa nunca trafega — nem escondida no HTML, nem em atributo `data-`.

Não é antifraude séria (nada é, no navegador do aluno). É higiene: evita que a resposta apareça com um F12 de dez segundos.

### D8 — Modo síncrono primeiro

Os dois modos foram pedidos, mas não são um toggle:

| | Síncrono | Assíncrono |
|---|---|---|
| Estado da questão | na sessão | por participante (`indice_atual`) |
| Projetor mostra | questão + contador | progresso da turma |
| Revelação | coletiva, comandada | imediata, individual |

O schema já comporta os dois (`sessoes.modo`, `participantes.indice_atual`). **A v1 implementa só o síncrono**, que é o que entrega o "feedback na hora". O assíncrono entra depois sem refazer o modelo de dados.

---

## 4. Modelo de dados

```
provas ─┬─ questoes ── alternativas
        │
        └─ sessoes ─┬─ participantes ─┐
                    │                 ├─ respostas
                    └─────────────────┘
```

```sql
provas        (id, titulo, criada_em)
questoes      (id, prova_id, enunciado, ordem)
alternativas  (id, questao_id, texto, correta, ordem)

sessoes       (id, prova_id, codigo, modo, identificacao,
               questao_atual, fase, versao, criada_em)

participantes (id, sessao_id, token, nome, indice_atual, last_seen)
respostas     (id, sessao_id, participante_id, questao_id,
               alternativa_id, criada_em)
```

### Separação prova × sessão

`prova` é o conteúdo, reutilizável. `sessao` é uma aplicação numa turma, com código, ritmo e participantes próprios. Aplicar a mesma prova em três turmas cria três sessões e não duplica uma questão sequer.

### Constraints que carregam regra de negócio

| Constraint | Regra que protege |
|---|---|
| `UNIQUE (participante_id, questao_id)` em `respostas` | Uma resposta por questão. Duplo toque e reenvio por rede lenta são absorvidos pelo banco, não por lógica de aplicação. |
| `UNIQUE (codigo)` em `sessoes` | Código de sala não colide. |
| `UNIQUE (token)` em `participantes` | Identidade estável. |
| `ON DELETE CASCADE` | Apagar sessão leva participantes e respostas junto. Limpeza de fim de aula em um comando. |

### Índices

```sql
idx_questoes_prova       (prova_id, ordem)        -- carregar questão atual
idx_alternativas_questao (questao_id, ordem)      -- carregar alternativas
idx_participantes_sessao (sessao_id, last_seen)   -- contar online
idx_respostas_questao    (sessao_id, questao_id)  -- contar respostas ao vivo
```

Os dois últimos existem porque são varridos a cada 2 segundos pela tela do projetor. Os dois primeiros, pelo caminho quente do polling.

---

## 5. Máquina de estados da sessão

```
                    ┌──────────────┐
        criada ───► │  aguardando  │  sala de espera, alunos entrando
                    └──────┬───────┘
                           │ professor inicia
                           ▼
                    ┌──────────────┐
              ┌───► │ respondendo  │  questão no ar, aceita resposta
              │     └──────┬───────┘
              │            │ professor revela (ou todos responderam)
              │            ▼
              │     ┌──────────────┐
              │     │  revelado    │  gabarito liberado, respostas fechadas
              │     └──────┬───────┘
              │            │
    próxima ──┘            │ última questão
     questão               ▼
                    ┌──────────────┐
                    │  encerrada   │
                    └──────────────┘
```

**Toda transição incrementa `versao`.** É isso que faz os celulares descobrirem a mudança no poll seguinte, em até 2 segundos.

`api/responder.php` só aceita gravação em `fase = respondendo`. Fora disso devolve `409 {"erro":"fechada"}` — comportamento verificado em teste.

---

## 6. Contrato da API

Quatro endpoints. Nenhum precisa de sessão PHP: o token carrega a identidade.

### `POST /api/entrar.php`

Formulário HTML. Cria participante e redireciona.

```
codigo=AULA01&nome=Maria     (nome só quando identificacao='nome')
→ 302 Location: ../prova.php#t=<token>
```

Erro de código ou sessão encerrada volta para `index.php` com o código na query — a tela reexibe o motivo.

### `GET /api/estado.php?token=&v=`

Coração do polling.

```json
// nada mudou
{"v":1}

// mudou
{
  "v": 3,
  "fase": "respondendo",
  "nome": "Aluno 01",
  "questao": {
    "ordem": 2, "total": 3,
    "enunciado": "Em qual camada do modelo OSI atua um switch tradicional?",
    "alternativas": [
      {"id": 6, "letra": "A", "texto": "Física"},
      {"id": 7, "letra": "B", "texto": "Enlace"}
    ]
  },
  "escolhida": null,
  "correta": 7        // presente APENAS quando fase='revelado'
}
```

Token inválido → `404 {"erro":"token"}`. O cliente limpa o `localStorage` e volta à entrada. É assim que um aluno se recupera de um banco recriado entre aulas.

Efeito colateral intencional: todo poll grava `last_seen`. Presença sai de graça.

### `POST /api/responder.php`

```json
{"token": "...", "alternativa_id": 7}
→ {"ok": true, "gravou": true, "escolhida": 7}
```

Validações, em ordem:

1. token existe → senão `404`
2. `fase = respondendo` → senão `409 {"erro":"fechada"}`
3. alternativa pertence à questão que está no ar → senão `422 {"erro":"alternativa"}`

O passo 3 impede que um cliente adulterado responda a uma questão futura. Verificado em teste.

**Decisão revisada (pós-v1 inicial):** o aluno pode trocar de resposta na mesma questão enquanto ela seguir em `fase = respondendo` — não é mais só a primeira gravação que vale. `INSERT ... ON CONFLICT (participante_id, questao_id) DO UPDATE` substitui o `INSERT OR IGNORE` original; o `UNIQUE` continua garantindo uma linha só por questão, só que agora ela se atualiza em vez de travar. Quem trava a resposta definitivamente é a transição de fase pra `revelado` (passo 2 acima), não mais a primeira escolha.

### `GET /api/painel.php` *(a implementar)*

Alimenta o projetor. Sem gate de versão, porque os contadores mudam por ação dos alunos:

```json
{
  "fase": "respondendo",
  "questao": {"ordem": 1, "total": 3, "enunciado": "..."},
  "online": 24,
  "responderam": 18,
  "distribuicao": [{"letra":"A","n":3}, {"letra":"B","n":11}],
  "acertos": 11, "erros": 7
}
```

`distribuicao` só é exibida após a revelação — mostrar antes enviesa quem ainda não respondeu.

---

## 7. Telas

### Aluno — `index.php` → `prova.php`

Um caminho, sem menu. Entra pelo QR Code, responde, espera.

```
  ┌────────────────────────┐
  │ ▪                    ▪ │   marcas de registro
  │ ALUNO 07        1 / 3  │
  │ ────────────────────── │
  │                        │
  │ Qual protocolo traduz  │
  │ nomes de domínio em    │
  │ endereços IP?          │
  │                        │
  │ ╭───╮                  │
  │ │ A │  A) SMTP         │   alvo de toque: 64px
  │ ╰───╯                  │
  │ ╭───╮                  │
  │ │ ● │  B) DNS          │   marcada: bolha preenchida
  │ ╰───╯                  │
  │ ...                    │
  └────────────────────────┘
```

**Marcação otimista:** o toque preenche a bolha na hora, antes da resposta da rede. Se o servidor recusar, desfaz e explica. Numa rede de sala de aula a latência é baixa mas não é zero, e nada frustra mais que um botão que parece não ter funcionado.

### Projetor — `tela.php` *(a implementar)*

Feito para ser lido do fundo da sala. Tipografia grande, alto contraste, nada de detalhe fino.

Durante a resposta mostra questão + **"18 de 24 responderam"**. Na revelação, barras de acerto/erro por alternativa, com a correta destacada.

### Admin — `admin/` *(a implementar)*

Mobile-first por decisão explícita: o professor controla do próprio celular enquanto anda pela sala.

- CRUD de provas, questões e alternativas
- abrir sessão (escolhendo modo e identificação)
- controle ao vivo: **Revelar** · **Próxima** · **Encerrar**

---

## 8. Design visual

Direção: **cartão-resposta de prova**. O vocabulário visual vem do universo do próprio conteúdo — gabarito, bolhas para preencher, marcas de registro de leitura óptica.

### Tokens

```css
--papel:   #fbfaf7    fundo
--cartao:  #ffffff    superfície da folha
--tinta:   #14171c    texto e marcação
--grafite: #767c86    rótulos e texto secundário
--pauta:   #dcd9d2    bordas e divisórias
--marca:   #d9342b    foco, alertas, marca de leitura óptica
```

Uma cor de destaque só. Ela aparece no anel de foco, no aviso e nos pulsos de espera — nunca como decoração.

### Tipografia

Sem internet, sem webfont. Duas famílias de sistema com papéis distintos:

- **`system-ui`** — enunciados e alternativas. Legibilidade em qualquer aparelho.
- **`ui-monospace`** — letras das bolhas, código da sala, contador de questão, rótulos. Monoespaçada é a letra do formulário oficial; é ela que dá o registro de "documento de prova".

Rótulos em caixa alta com `letter-spacing: .16em` reforçam a mesma leitura.

### Elemento de assinatura

**A bolha que preenche.** Ao tocar, um círculo escuro cresce de dentro da bolha com `cubic-bezier(.34, 1.4, .64, 1)` — a curva dá uma leve ultrapassagem, como quem sombreia a lápis. É a única animação de interface do sistema.

`prefers-reduced-motion: reduce` desliga toda animação.

### Piso de qualidade

- alvos de toque de 64px de altura
- foco de teclado visível (`outline` de 3px em `--marca`)
- `aria-pressed` nas alternativas, `role="group"` na lista
- `enterkeyhint`, `autocapitalize`, `inputmode` nos campos
- texto inserido via `textContent`, nunca `innerHTML` — enunciado vindo do banco não injeta HTML

---

## 9. Segurança

O modelo de ameaça é honesto: **aluno determinado burla qualquer coisa no navegador dele.** O sistema protege o que dá para proteger sem transformar a aula em auditoria.

| Vetor | Tratamento |
|---|---|
| SQL injection | PDO preparado em 100% das consultas |
| XSS | `htmlspecialchars` no servidor, `textContent` no cliente |
| Resposta certa vazando | `correta` só trafega em `fase = revelado` |
| Voto duplo | `UNIQUE (participante_id, questao_id)` |
| Responder questão futura | alternativa validada contra a questão no ar |
| Token em log | trafega por fragmento de URL |
| Aluno chamar `comando.php` direto | `token_professor` opaco, checado com `hash_equals` |
| Aluno acessar `admin/provas.php` etc. direto | senha única (`exigirAdmin()`), sessão PHP |
| CSRF nos formulários de admin | token por sessão (`tokenCsrf()`/`exigirCsrf()`) |

**Decisão revisada (pós-v1 inicial):** `codigo` da sala é público — todo aluno o digita pra entrar. Sem um segredo à parte, qualquer aluno que soubesse o `codigo` conseguia chamar `api/comando.php` direto e revelar o gabarito, pular questão ou encerrar a prova, derrubando a própria proteção do D7 (achado por revisão automática de segurança). A correção segue o mesmo padrão do D4: um token opaco (`token_professor`, `bin2hex(random_bytes(16))`), gerado por sessão, nunca exposto ao aluno. Trafega por query string (`?pt=...`, não fragmento — contexto de risco menor que o token do aluno, já que só o professor abre esse link) e persiste em `localStorage` pra sobreviver a um F5. Não é login (rede local fechada, sem cadastro de usuário) — é uma capacidade separada do `codigo` público.

**Decisão revisada (Bloco C, admin de provas):** `admin/provas.php`, `questoes.php` e `questao.php` (T09-T14) nasceram sem nenhuma checagem — diferente de `comando.php`, que já tinha o `codigo` da sala como barreira mínima, essas páginas eram acessíveis por qualquer aluno que digitasse a URL, sem precisar de token nenhum (achado por revisão automática de segurança). Diferente do `token_professor` (capacidade por sessão), aqui a proteção é por senha única do admin (`src/auth.php`, `exigirAdmin()`): gerada uma vez em `bin/init-db.php`, guardada fora do git (`db/admin.senha`), validada por sessão PHP nativa (`$_SESSION['admin_ok']`). Não é sistema de contas — continua sem cadastro de usuário, só uma senha compartilhada que o professor digita uma vez por aparelho. Como a proteção agora usa sessão PHP (cookie enviado automaticamente pelo navegador), os formulários de mutação (criar/editar/excluir/reordenar/duplicar) também ganharam token CSRF por sessão (`tokenCsrf()`/`exigirCsrf()`) — sem isso, uma página maliciosa aberta no mesmo navegador do professor autenticado ainda conseguiria disparar as ações via POST.

**Decisão nova (T09b-T09d, pedidos do usuário):** prova nasce como rascunho (`publicada=0`) — a única forma de um aluno chegar numa prova é via sessão, e `nova-sessao.php` só lista provas publicadas. Isso vale igual pra prova criada manualmente, duplicada ou importada de CSV. Excluir prova exige dupla confirmação (`confirm()` + digitar "excluir" num `prompt()`, checado nos dois lados: JS pra experiência, servidor pra garantir que um POST direto sem passar pelo formulário não apaga nada). Importação de CSV reusa a mesma validação do editor manual (`validarQuestao()`) e é tudo-ou-nada numa transação — uma linha ruim não deixa a prova pela metade.

Despublicar não fica só na criação de sessão nova: `api/painel.php` e `api/estado.php` mostram `fase=aguardando` pro projetor e pro aluno quando a prova da sessão ativa está despublicada, mesmo com a sessão de verdade em `respondendo`/`revelado` — é o kill-switch que o professor pediu pra tirar a prova do ar na hora. `admin/sessao.php` manda `?admin=1` nesse mesmo endpoint e continua vendo o estado real (o professor precisa saber o que está acontecendo de fato). Mas **despublicar é bloqueado se já existe sessão em `respondendo`/`revelado`** (`provaTemSessaoIniciada()`) — puxar uma prova do ar no meio da aplicação seria pior que deixar terminar; o kill-switch acima cobre o caso em que isso aconteça por alguma outra via (edição direta do banco, etc.), mas o fluxo normal do admin não permite chegar nesse estado.

**Deliberadamente fora do escopo:** bloquear múltiplas abas, fingerprint de aparelho, detectar troca de rede. Custam código, geram falso positivo com aluno honesto e não param o desonesto.

**Sem HTTPS.** Rede local, dados não sensíveis, e certificado autoassinado geraria aviso de segurança assustador em 30 celulares ao mesmo tempo.

---

## 10. Estrutura de arquivos

```
quizsala/
├── bin/
│   ├── init-db.php        cria o banco + prova de exemplo
│   └── teste.sh           bateria ponta a ponta
├── db/
│   ├── schema.sql
│   └── quizsala.sqlite    gerado; fora do versionamento
├── src/
│   ├── db.php             PDO singleton, WAL
│   └── util.php           JSON, escape, consultas de estado
└── public/                ← raiz do servidor web
    ├── index.php          entrada do aluno
    ├── prova.php          casca da prova
    ├── api/
    │   ├── entrar.php
    │   ├── estado.php
    │   └── responder.php
    └── assets/
        ├── estilo.css
        └── aluno.js
```

`src/` e `db/` ficam **fora** de `public/`. Ninguém baixa o arquivo do banco pela URL.

### Nota de portabilidade

`mb_substr` foi removido do código: mbstring não é garantida em toda instalação PHP. O helper `cortar()` usa `mb_substr` quando existe e cai para `preg_match` com modificador `/u` quando não. Descoberto por falha real em teste, não por precaução hipotética.

O mesmo princípio vale para o resto: nada de dependência que o notebook possa não ter na hora da aula.

---

## 11. Estado atual

### Implementado e testado

- schema completo com constraints e índices
- inicialização do banco com prova de exemplo (3 questões de redes)
- entrada anônima e nomeada
- polling por versão — **7 bytes** medidos quando nada muda
- registro de resposta com deduplicação e validação
- revelação de gabarito com acerto/erro individual
- tela do aluno completa em viewport de celular

### Bateria de testes (`bin/teste.sh`)

Todos passando, zero warning no log do PHP:

```
3 alunos entram                          → Aluno 01, 02, 03
estado inicial v=0                       → payload completo
poll sem mudança v=1                     → {"v":1}, 7 bytes
resposta certa e errada                  → gravadas
resposta duplicada                       → gravou:false, sem duplicata
contagem acerto/erro                     → 1 acerto, 1 erro
presença                                 → 3 online
revelação                                → correta liberada, escolhida correta
responder com questão fechada            → 409 fechada
avanço de questão                        → versão 3, questão 2
alternativa de outra questão             → 422 alternativa
```

### Pendente

| # | Item | Verificação |
|---|---|---|
| 5 | `api/painel.php` + `tela.php` | números do projetor batem com o banco |
| 6 | Admin: CRUD de provas | criar prova e aplicá-la ponta a ponta |
| 7 | Admin: controle ao vivo | avançar pelo celular reflete em <2s |
| 8 | QR Code + IP fixo | celular real entra pelo QR |

---

## 12. Riscos operacionais

Erros que só aparecem na frente da turma:

| Risco | Prevenção |
|---|---|
| Celular cai pro 4G ("sem internet") | Instrução na entrada; captive portal na v2 |
| Aluno atrasado entra na questão 3 | Entrada permitida a qualquer momento; conta só o que respondeu |
| Alguém recarrega a página | Token no `localStorage` preserva a identidade |
| Notebook hiberna no meio | Desativar suspensão antes da aula — item do checklist |
| Banco recriado entre aulas | Token órfão → `404` → cliente limpa e reentra |
| Contador travado esperando ausente | "Revelar agora" sempre disponível |
| Alguém digitou o código errado | Código curto e projetado; QR Code é o caminho principal |

### Checklist de aula

```
[ ] roteador ligado, notebook em IP fixo
[ ] suspensão automática desativada
[ ] php bin/init-db.php  (ou sessão nova sobre prova existente)
[ ] servidor no ar
[ ] QR Code projetado
[ ] testar entrada com o próprio celular antes dos alunos
```

---

## 13. Fora do escopo (e por quê)

| Item | Motivo |
|---|---|
| Ranking por tempo de resposta | Vira competição, e o objetivo é diagnóstico honesto |
| Bloqueio de múltiplas abas | Não para quem quer burlar; irrita quem não quer |
| Histórico do aluno entre aulas | Exigiria cadastro, e o valor está no anonimato |
| Login de professor | Rede local fechada; senha atrapalha mais que protege |
| Questões com imagem | Encarece o admin; adicionar quando houver demanda real |
| Exportar resultado | Provável v2 — CSV simples resolve |
