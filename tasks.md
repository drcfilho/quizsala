# QuizSala — Tarefas

**Complemento de:** `arquitetura.md` (arquitetura) e `plan.md` (fases e marcos)

Cada tarefa é uma **fatia vertical**: termina com algo que você abre no navegador e usa. Nenhuma tarefa deixa o sistema quebrado ou pela metade — se você parar depois de qualquer uma delas, o que existe funciona.

Ordem sugerida, mas T09–T14 (admin de provas) podem ser antecipadas se você quiser montar conteúdo real antes.

---

## Ferramentas de apoio

Comandos usados nas verificações. Vale colar num arquivo à mão.

```bash
# subir o servidor (a partir da raiz do projeto)
cd public && php -S 0.0.0.0:8080

# recriar o banco com a prova de exemplo
php bin/init-db.php

# rodar a bateria de testes
bash bin/teste.sh

# consultar o banco sem cliente SQLite instalado
php -r '$p=new PDO("sqlite:db/quizsala.sqlite");
foreach($p->query($argv[1]) as $r) echo implode(" | ",array_slice($r,0,count($r)/2)),PHP_EOL;' \
  "SELECT * FROM sessoes"
```

Guarde esse último como `bin/sql.sh` — você vai usar em quase toda verificação.

---

# Bloco A — Painel do projetor

O objetivo do bloco é chegar rápido no momento em que **o número aparece na parede**.

---

## T01 · Projetar a questão atual *(concluída)*

**Entrega:** uma tela em tela cheia mostrando a questão que está no ar.

**Arquivos**
- `public/api/painel.php` *(novo)*
- `public/tela.php` *(novo)*
- `public/assets/tela.css` *(novo)*
- `public/assets/tela.js` *(novo)*

**Passos**
1. `painel.php` recebe `?codigo=AULA01` e devolve `fase`, `questao.ordem`, `questao.total`, `questao.enunciado`, `questao.alternativas`. Reaproveite `questao_por_ordem()` de `src/util.php`.
2. **Não inclua `correta`** neste endpoint ainda — entra na T03, junto com a revelação.
3. `tela.php` faz poll a cada 2s e redesenha. Sem gate de versão: o painel é uma tela só, e a partir da T02 os contadores mudam por ação do aluno.
4. Estados: `aguardando` → "Aguardando o professor"; `respondendo` → questão; `encerrada` → "Prova encerrada".

**Como testar**
```bash
php bin/init-db.php
cd public && php -S 0.0.0.0:8080
```
Abrir `http://localhost:8080/tela.php?codigo=AULA01`.

**Pronto quando**
- a questão 1 aparece na tela;
- mudar a questão pelo banco reflete na tela em ≤2s:
```bash
php -r '$p=new PDO("sqlite:db/quizsala.sqlite");
$p->exec("UPDATE sessoes SET questao_atual=2 WHERE codigo=\"AULA01\"");'
```
- `curl -s "http://localhost:8080/api/painel.php?codigo=AULA01" | grep correta` não retorna nada.

---

## T02 · Contador "18 de 24 responderam" *(concluída)*

**Entrega:** a tela mostra, ao vivo, quantos já responderam.

**Arquivos:** `public/api/painel.php`, `public/tela.php`, `public/assets/tela.js`

**Passos**
1. Adicionar ao payload:
```sql
-- online
SELECT COUNT(*) FROM participantes
 WHERE sessao_id = ? AND last_seen >= strftime('%s','now') - 6;

-- responderam
SELECT COUNT(*) FROM respostas
 WHERE sessao_id = ? AND questao_id = ?;
```
2. Exibir grande, abaixo do enunciado.
3. Quando `responderam >= online`, destacar visualmente. **Não avançar sozinho** — só sinalizar.

**Como testar**
1. Abrir `tela.php` numa janela.
2. Abrir três abas anônimas em `index.php?s=AULA01`, entrar em cada uma.
3. Conferir que o contador mostra `0 de 3`.
4. Responder numa aba → vira `1 de 3` em ≤2s.
5. Fechar uma aba, esperar 8s → o denominador cai para 2.

**Pronto quando** o contador acompanha as respostas em tempo real e a presença expira sozinha.

---

## T03 · Revelação com acertos e erros *(concluída)*

**Entrega:** o resultado da questão na parede. É o núcleo do produto.

**Arquivos:** `public/api/painel.php`, `public/assets/tela.js`, `public/assets/tela.css`

**Passos**
1. Quando `fase = revelado`, incluir no payload:
```json
{
  "acertos": 11, "erros": 7,
  "distribuicao": [
    {"letra":"A","n":3,"correta":false},
    {"letra":"B","n":11,"correta":true}
  ]
}
```
2. Desenhar barras horizontais por alternativa, com a correta destacada.
3. **A distribuição só aparece na fase `revelado`.** Mostrar antes enviesa quem ainda não respondeu.
4. Nada de Chart.js aqui — cinco `div` com `width` percentual resolvem, sem dependência.

**Como testar**
1. Com três alunos, responder: dois na certa, um na errada.
2. Revelar pelo banco:
```bash
php -r '$p=new PDO("sqlite:db/quizsala.sqlite");
$p->exec("UPDATE sessoes SET fase=\"revelado\", versao=versao+1 WHERE codigo=\"AULA01\"");'
```
3. Conferir o número na tela contra o banco:
```bash
php -r '$p=new PDO("sqlite:db/quizsala.sqlite");
foreach($p->query("SELECT SUM(a.correta) acertos, COUNT(*)-SUM(a.correta) erros
  FROM respostas r JOIN alternativas a ON a.id=r.alternativa_id
  WHERE r.questao_id=1") as $r) print_r($r);'
```

**Pronto quando** os números da tela batem exatamente com o `SELECT`, e o celular do aluno também mostra o resultado individual (isso já funciona desde a T00).

**Revisão pós-v1:** o payload original desta tarefa não incluía o texto da alternativa nem quantos não responderam — a tela só mostrava letra + barra, então ninguém via de fato *qual* era a resposta certa. `distribuicao[].texto` e `naoResponderam` (= online − responderam, no momento da revelação) foram adicionados a `painel.php`/`tela.js`/`tela.css` a pedido do usuário, mantendo a Regra do Sinal Duplo (linha correta também com texto em verde/negrito, não só a barra).

---

## T04 · Legibilidade de projeção *(CSS implementado; validação no projetor real pendente)*

**Entrega:** a tela lida do fundo da sala.

**Arquivos:** `public/assets/tela.css`

**Passos**
1. ~~Enunciado com no mínimo `clamp(32px, 4vw, 56px)`.~~ Feito: `clamp(2rem, 4vw, 3.5rem)` (32px–56px).
2. ~~Contador ainda maior~~ Feito: `clamp(5rem, 14vw, 13rem)` (80px–208px), maior que o enunciado.
3. ~~Contraste alto~~ Feito: papel/tinta (quase preto sobre quase branco), sem tema escuro.
4. ~~Sem `overflow` escondido~~ Feito: `text-wrap: balance`, `max-width: 60ch`, sem `overflow: hidden` em lugar nenhum.

**Como testar** No projetor real da sala, com a luz acesa, de pé no fundo. Não vale validar no monitor — e de fato não foi validado assim ainda; só testado em navegador/monitor até aqui.

**Pronto quando** você lê o enunciado e o contador a 8 metros, com a luz da sala ligada. **Ainda não verificado nessas condições reais** — depende de acesso a um projetor de verdade.

---

## T04b · Tela final do aluno: placar, comprovante em PDF e agradecimento *(concluída)*

**Não estava no plano original — pedido do usuário depois do T07.**

**Entrega:** ao encerrar a prova, o aluno vê quantas acertou, pode salvar um comprovante em PDF com todas as questões (resposta dele vs a certa) e depois uma tela de agradecimento.

**Arquivos:** `src/util.php` (`resultadoParticipante()`), `public/api/estado.php`, `public/assets/aluno.js`, `public/assets/estilo.css`

**Passos**
1. `estado.php` inclui `resultado: {acertos, total, questoes: [...]}` no payload quando `fase = encerrada`.
2. `aluno.js` mostra um placar (`X / Y respostas certas`) com dois botões: "Salvar comprovante em PDF" e "Concluir".
3. **PDF sem biblioteca nenhuma** — o botão monta um bloco `#comprovante-impressao` (escondido na tela, só visível em `@media print`) e chama `window.print()`; "salvar como PDF" já é nativo em qualquer navegador, funciona sem internet, e evita vendorizar uma lib JS de PDF que contrariaria o D1 (zero framework).
4. Depois de imprimir (ou ao clicar "Concluir" direto), mostra "Obrigado por participar!".

**Como testar** `bash bin/teste.sh` (Caso 15) confere `resultado.acertos`/`resultado.total`/questão sem resposta pelo `api/estado.php`. Testado também no navegador: placar "1 / 3", `#comprovante-impressao` com as 3 questões (certa, errada com o gabarito, e "não respondeu"), e a tela de agradecimento após "Concluir".

---

# Bloco B — Controle pelo celular

---

## T05 · Botões Revelar · Próxima · Encerrar *(concluída)*

**Entrega:** aplicar uma prova inteira sem tocar no notebook.

**Arquivos**
- `public/api/comando.php` *(novo)*
- `public/admin/sessao.php` *(novo)*
- `public/assets/admin.css` *(novo)*

**Passos**
1. ~~`comando.php` recebe `POST {codigo, acao, versao_esperada}` com `acao` em `revelar | proxima | encerrar`.~~ Feito, **mais uma ação não prevista aqui**: `iniciar` (`aguardando → respondendo`). Sem ela a sessão nunca sairia do estado inicial — lacuna entre `arquitetura.md` §5 (que já previa essa transição) e este documento, que não a listava.
2. ~~Transições, sempre incrementando `versao`~~ Feito, incluindo `encerrar` como escape sempre disponível de qualquer fase.
3. ~~`admin/sessao.php`: três botões de altura mínima 64px~~ Feito — mas os botões mostrados mudam conforme a fase (só as ações válidas aparecem), em vez dos três sempre visíveis.
4. ~~Rejeitar transição inválida com `409`~~ Feito.

**Como testar** Percorri as 3 questões via curl direto na API (não fisicamente pelo celular — ainda não testado num aparelho real, só simulado) e confirmei visualmente no navegador que o botão muda de "Revelar" pra "Próxima questão" ao clicar.

**Pronto quando** você aplica a prova de exemplo inteira — confirmado via API; o projetor acompanhando em tempo real ainda não visto lado a lado com um celular físico de verdade.

---

## T06 · Guarda de toque duplo *(concluída)*

**Entrega:** o professor andando pela sala não pula uma questão por acidente.

**Arquivos:** `public/api/comando.php`, `public/assets/admin.js`

**Passos**
1. O cliente envia a `versao` que conhece.
2. O servidor só aplica se bater com a atual; senão devolve `409 {"erro":"versao"}` **sem alterar nada**.
3. No cliente: desabilitar o botão durante o envio e reabilitar na resposta.

**Como testar**
```bash
# duas requisições iguais com a mesma versão, em paralelo
curl -s -X POST -H 'Content-Type: application/json' \
  -d '{"codigo":"AULA01","acao":"proxima","versao_esperada":2}' \
  http://localhost:8080/api/comando.php &
curl -s -X POST -H 'Content-Type: application/json' \
  -d '{"codigo":"AULA01","acao":"proxima","versao_esperada":2}' \
  http://localhost:8080/api/comando.php &
wait
```
Depois conferir `questao_atual` no banco.

**Pronto quando** as duas requisições rodam mas `questao_atual` avança **uma** posição só, e a segunda responde `409`. **Testado** com duas requisições `curl` em paralelo de verdade: versão avançou de 1 pra 2 uma vez só; a perdedora respondeu 409 (via checagem de fase, que nesse timing específico venceu a checagem de versão — mesmo resultado prático, nenhum dado corrompido).

---

## T07 · Presença no painel do professor *(concluída)*

**Entrega:** decidir se revela agora, olhando só para o celular.

**Arquivos:** `public/admin/sessao.php`, `public/assets/admin.js`

**Passos**
1. ~~Reaproveitar `api/painel.php` — não duplicar consulta.~~ Feito (endpoint ganhou o campo `versao` a mais, que só o admin usa).
2. ~~Exibir "24 online · 18 responderam" acima dos botões.~~ Feito.
3. ~~Ao bater 100%, destacar o botão Revelar (sem disparar sozinho).~~ Feito — **quase saiu incompleto**: na primeira versão só destaquei a linha de presença, não o botão em si (que é o que este item pede). Corrigido depois de reler o critério.

**Como testar** Com um aluno real respondendo pelo navegador (poll contínuo, não só simulação por `curl`), confirmei que "1 online · 1 responderam" inverte pra fundo escuro **e** o botão Revelar ganha borda vermelha — nada dispara sozinho.

**Pronto quando** os dois painéis mostram o mesmo número ao mesmo tempo — confirmado (mesma fonte de dado, `api/painel.php`, então é garantido por construção, mas também verifiquei visualmente lado a lado).

---

## T08 · Abrir uma sessão nova *(concluída)*

**Entrega:** aplicar a mesma prova em outra turma sem mexer no banco.

**Arquivos:** `public/admin/nova-sessao.php` *(novo)*, `public/admin/index.php` *(novo)*

**Passos**
1. Formulário: prova, modo (`sincrono` fixo por ora), identificação (`anonimo` / `nome`).
2. Gerar código de 6 caracteres **sem ambiguidade visual** — remova `0 O 1 I 5 S`. Alguém vai ler isso projetado do fundo da sala.
3. Verificar colisão contra o `UNIQUE (codigo)` e regerar.
4. Redirecionar para `admin/sessao.php?codigo=...`.
5. `admin/index.php` lista sessões ativas.

**Como testar**
1. Criar sessão da mesma prova, com identificação `nome`.
2. Entrar como aluno pelo novo código → o campo de nome aparece.
3. Conferir que as respostas da sessão nova não se misturam com as da AULA01:
```bash
php -r '$p=new PDO("sqlite:db/quizsala.sqlite");
foreach($p->query("SELECT sessao_id, COUNT(*) FROM respostas GROUP BY sessao_id") as $r) print_r($r);'
```

**Pronto quando** duas sessões da mesma prova rodam com contagens independentes. **Testado**: Caso 21 (`bin/teste.sh`) gera 100 códigos e confirma que nenhum usa `0 O 1 I 5 S`; Casos 22-23 criam sessão via `nova-sessao.php`, iniciam, respondem e confirmam que as respostas ficam em `sessao_id` distintos. Também confirmado visualmente no navegador (lista de sessões ativas com contagem de participantes, formulário de criação, redirecionamento pro painel do professor já com `token_professor`). `admin/index.php`/`nova-sessao.php` protegidos por `exigirAdmin()` + CSRF, igual ao Bloco C.

---

# Bloco C — Admin de provas

Até aqui o conteúdo vinha do seed. Este bloco tira o banco do caminho.

---

## T09 · Listar e criar provas *(concluída)*

**Revisão de segurança pós-implementação:** `provas.php`/`questoes.php`/`questao.php` foram ao ar sem checagem nenhuma — qualquer aluno digitando a URL criava/editava/apagava questão. Corrigido com `exigirAdmin()` (senha única, gerada por `bin/init-db.php` em `db/admin.senha`, fora do git) + token CSRF por sessão (`tokenCsrf()`/`exigirCsrf()`) em todos os formulários de mutação. Ver `arquitetura.md` §9. Casos 15-19 em `bin/teste.sh` cobrem isso.

**Arquivos:** `public/admin/provas.php` *(novo)*

**Passos** Lista com título, número de questões e botão de nova prova (só o título).

**Como testar** Criar "Aula 2 — Segurança", ver aparecer com "0 questões".

**Pronto quando** a prova nova aparece na lista e no seletor da T08. **Testado** via curl (POST direto) e visualmente no navegador — lista mostra título + contagem de questões, form de criação com `INSERT` real.

---

## T10 · Editor de questão *(concluída)*

**Entrega:** montar conteúdo real, pelo celular.

**Arquivos:** `public/admin/questao.php` *(novo)*

**Passos**
1. Um enunciado (`textarea`) + 5 campos de alternativa.
2. Radio marca qual é a correta.
3. Salvar cria/atualiza `questoes` e `alternativas` **numa transação** — questão sem alternativa é pior que questão nenhuma.
4. `ordem` = maior existente + 1.

**Como testar** Criar 3 questões na prova nova pelo celular, abrir uma sessão dela e aplicar ponta a ponta.

**Pronto quando** você aplica uma prova criada 100% pela interface, sem `INSERT` manual. **Testado** via curl (criar/editar questão, conferi enunciado + 3 alternativas + `correta` gravados certos no banco) e visualmente no navegador (editor carrega a questão existente com a alternativa certa pré-marcada).

---

## T11 · Validação do editor *(concluída)*

**Passos**
1. Enunciado não vazio.
2. No mínimo 2 alternativas preenchidas.
3. Exatamente uma marcada como correta.
4. Erro **no campo**, não num alerta genérico. Preservar o que já foi digitado.

**Como testar** Tentar salvar sem correta, sem enunciado, e com uma alternativa só.

**Pronto quando** cada erro diz o que fazer, e nada do que foi digitado se perde. **Testado** via curl: POST sem `correta` → 200 (não redireciona, fica na tela com erro); POST sem `enunciado` → mensagem "Escreva o enunciado." O que já foi digitado volta pro formulário porque os campos são re-renderizados a partir do próprio `$_POST`, não recarregados do banco.

---

## T12 · Reordenar e excluir questões *(concluída)*

**Entrega:** corrigir a prova sem recriar tudo.

**Passos**
1. Subir/descer trocando `ordem` entre vizinhas.
2. **Excluir renumera as seguintes.** Sem isso, `ordem` vira 1, 3, 4 e a sessão pula uma questão inexistente — tela em branco na frente da turma.
3. Confirmar exclusão: `CASCADE` leva alternativas e respostas junto.

**Como testar**
```bash
# depois de excluir a questão 2 de 4
php -r '$p=new PDO("sqlite:db/quizsala.sqlite");
foreach($p->query("SELECT ordem, enunciado FROM questoes WHERE prova_id=2 ORDER BY ordem") as $r)
  echo $r["ordem"]," | ",substr($r["enunciado"],0,40),PHP_EOL;'
```

**Pronto quando** `ordem` fica contígua (1, 2, 3) e a sessão percorre todas sem tela em branco. **Testado** via curl: subir a questão 2 troca a `ordem` com a 1; excluir a do meio deixa 1, 2 contíguos (era 1, 3 antes da renumeração). Também confirmado visualmente (setas desabilitadas nos extremos, confirmação antes de excluir).

---

## T13 · Duplicar prova *(concluída)*

**Passos** Copiar prova, questões e alternativas com novos IDs. Título recebe sufixo "(cópia)".

**Como testar** Duplicar, editar a cópia, conferir que a original não mudou.

**Pronto quando** as duas são independentes. **Testado** via curl: duplicar "Aula 2 — Segurança" gerou "Aula 2 — Segurança (cópia)" com as mesmas questões em nova `prova_id`, IDs de questão/alternativa novos, título original preservado.

---

## T14 · Revisão mobile do admin *(CSS mobile-first desde a T09; sessão real num aparelho físico ainda não feita)*

**Passos** Percorrer todo o admin num celular real: alvos de 44px+, sem zoom horizontal, `textarea` que não some atrás do teclado.

**Como testar** Criar uma prova de 5 questões inteira pelo celular, em pé.

**Pronto quando** dá para fazer isso sem irritação. Todos os alvos de toque de T09-T13 já saíram com 44px+ (botões pequenos: subir/descer/duplicar) ou 64px (ações primárias), sem zoom horizontal, `textarea` com `resize: vertical`. **Ainda não testado num celular físico de verdade** — só em viewport reduzido no navegador.

**Achados no teste real do T14 (pedidos do usuário testando pelo celular), todos concluídos:**
- `provas.php` não tinha link de volta pra `index.php` — corrigido.
- Faltava um jeito de renomear a prova, testar ela na hora e fechar a edição — `questoes.php` ganhou título editável (`acao=renomear`), botão "Testar prova" (cria uma sessão na hora e já cai no painel do professor) e "Salvar prova e voltar".

---

## T09b · Importar prova de um CSV *(concluída)*

**Não estava no plano original — pedido do usuário.**

**Entrega:** montar uma prova inteira colando conteúdo de uma planilha, sem digitar questão por questão no editor.

**Arquivos:** `public/admin/importar-csv.php` *(novo)*, `public/exemplos/exemplo-prova.csv` *(novo, modelo pra baixar)*, `src/util.php` (`importarProvaCsv()`, `validarQuestao()`, `salvarQuestao()`)

**Formato:** uma linha por questão — `enunciado, alternativa_a..e, correta (letra A-E), explicacao`. `alternativa_e` e `explicacao` são opcionais. O título da prova vem de um campo separado no formulário (não do CSV), pra não repetir a mesma string em toda linha.

**Validação:** cada linha passa pela mesma `validarQuestao()` do editor manual (T11) — enunciado obrigatório, mínimo 2 alternativas, `correta` tem que apontar pra uma alternativa preenchida. **Um CSV com qualquer linha inválida não cria nada** (tudo dentro de uma transação) — erro lista o número da linha. Prova importada nasce como rascunho (`publicada=0`), igual à criação manual.

**Como testar** `bash bin/teste.sh` Casos 29-30: importa `exemplo-prova.csv` (3 questões, todas com explicação) e confirma que fica em rascunho; depois testa um CSV com uma linha sem enunciado e uma com `correta` inválida — confirma que nenhuma prova é criada.

**Pronto quando** um CSV bem formado vira uma prova completa, e um CSV ruim não cria nada pela metade.

---

## T09c · Explicação da resposta certa (campo oculto no editor) *(concluída)*

**Não estava no plano original — pedido do usuário.**

**Entrega:** o professor pode registrar por que a alternativa correta está certa, sem isso poluir o editor por padrão.

**Arquivos:** `db/schema.sql` (`questoes.explicacao`), `public/admin/questao.php`, `src/util.php`

**Como:** `<details>`/`<summary>` nativo do HTML — zero JavaScript. Fica fechado por padrão; abre sozinho se a questão já tem explicação salva. Campo é opcional em todos os fluxos (editor manual e CSV).

**Como testar** `bash bin/teste.sh` Caso 28: salva uma explicação, confirma no banco, confirma que o `<details>` volta aberto ao recarregar o editor.

**Pronto quando** o campo não aparece por padrão, mas está lá pra quem clicar.

---

## T09d · Publicar/despublicar, editar e excluir prova *(concluída)*

**Não estava no plano original — pedido do usuário.**

**Entrega:** ciclo de vida da prova além de criar/duplicar — controlar quando ela fica disponível pra virar sessão, e apagar o que não serve mais.

**Arquivos:** `db/schema.sql` (`provas.publicada`), `public/admin/provas.php`, `public/admin/nova-sessao.php`

**Como:**
- Prova nasce como rascunho (`publicada=0`) — criada manualmente, duplicada ou importada de CSV, não importa a origem.
- Botão "Publicar"/"Despublicar" em `provas.php` alterna o campo. `nova-sessao.php` só lista provas publicadas — é assim que uma prova "aparece pro aluno, pro projetor e pro professor": sem sessão não existe tela nenhuma dessas, e sem publicar não dá pra abrir sessão pelo fluxo normal. ("Testar prova" em `questoes.php` continua funcionando em rascunho — é o canal do professor pra conferir antes de publicar.)
- "Editar" é só um atalho visível pro que clicar no título já fazia (vai pra `questoes.php`).
- "Excluir" pede dupla confirmação: um `confirm()` e depois digitar a palavra "excluir" num `prompt()`. O servidor confere de novo (`$_POST['confirmacao'] === 'excluir'`) — um POST direto sem passar pelo `onsubmit` não apaga nada.
- **Despublicar afeta sessão já em andamento, não só a criação de sessão nova.** Achado em teste real: o professor precisa de um jeito de tirar a prova do ar na hora, não só travar sessões futuras. `api/painel.php` (sem `?admin=1`, ou seja pro projetor) e `api/estado.php` (aluno) mostram `fase=aguardando` quando a prova da sessão está despublicada, mesmo que a sessão de verdade esteja em `respondendo`/`revelado`. `admin/sessao.php` manda `?admin=1` e continua vendo o estado real — o professor precisa saber o que está de fato acontecendo pra decidir. Publicar/despublicar incrementa `sessoes.versao` pra isso chegar no próximo poll (≤2s), sem esperar a próxima ação real do professor.
- **Mas não deixa despublicar prova já iniciada** (`provaTemSessaoIniciada()`) — despublicar só funciona se nenhuma sessão dessa prova estiver em `respondendo`/`revelado`. Puxar o tapete no meio da aplicação seria pior que deixar rodar até encerrar. `aguardando` (sessão criada, não iniciada) e `encerrada` não travam.

**Como testar** `bash bin/teste.sh` Casos 26-27: publica/despublica e confirma que `nova-sessao.php` reage; exclui sem confirmação (prova continua) e com confirmação certa (prova some, cascade leva questões/sessões). Casos 31-32: despublicar é bloqueado com sessão em `respondendo` (painel do projetor não muda) e funciona normalmente sem sessão iniciada.

**Pronto quando** só provas publicadas viram sessão, despublicar reflete na tela ativa sem quebrar uma aplicação em andamento, e excluir exige duas confirmações antes de apagar de verdade.

---

## T09e · Trocar a senha do admin *(concluída)*

**Não estava no plano original — pedido do usuário.**

**Entrega:** o professor troca a senha do admin por uma que ele escolhe, em vez de ficar preso à senha aleatória gerada uma vez por `bin/init-db.php`.

**Arquivos:** `public/admin/senha.php` *(novo)*, `public/admin/index.php`, `public/assets/admin.css`

**Decisão de escopo (perguntei antes de mexer):** o pedido original era "cadastro de usuário e senha", mas isso reverteria uma decisão já registrada de propósito (`arquitetura.md` §9 e `plan.md` §10 — "Login de professor: rede local fechada, senha atrapalha mais que protege", um professor só, sem conceito de conta). Confirmado com o usuário: continua **1 senha única, sem usuário** — só trocável pelo próprio professor em vez de fixa em `db/admin.senha`.

**Como:**
- `senha.php` pede senha atual + nova senha + confirmação, tudo validado no servidor (`exigirAdmin()` + CSRF, igual ao resto do admin).
- Mínimo de 6 caracteres pra nova senha. `exigirAdmin()` não tem limite de tentativas — a senha aleatória original (16 hex, 64 bits) resistia a isso só pelo tamanho; uma senha curta escolhida a mão precisa de um piso mínimo pra não virar adivinhação fácil na mesma rede.
- Sessão já autenticada continua valendo depois da troca (a troca não desloga quem já estava logado) — só o próximo login exige a senha nova.

**Como testar** `bash bin/teste.sh` Caso 34: senha atual errada não troca nada; nova senha curta demais é rejeitada; confirmação que não bate é rejeitada; troca válida atualiza `db/admin.senha`, derruba a senha antiga (login com ela vira 401) e a nova funciona. O teste restaura a senha original no final — `bin/teste.sh` mexe direto no arquivo real do projeto, não numa cópia.

**Pronto quando** o professor consegue trocar a senha sem editar `db/admin.senha` na mão, e a troca é refletida imediatamente (sem precisar recriar o banco).

---

# Bloco D — Operação em sala

---

## T15 · QR Code gerado offline *(concluída)*

**Entrega:** ninguém digita endereço IP.

**Arquivos:** `src/qrcode.php` *(novo — biblioteca de arquivo único)*, `src/config.php` *(novo)*, `public/api/qr.php` *(novo)*

**Passos**
1. ~~Biblioteca **local**.~~ Feito — sem API de QR na internet, exatamente a dependência que quebraria na hora da aula. Não existe porte PHP oficial da lib de referência (Nayuki QR-Code-generator, MIT); portei o algoritmo Python pra PHP em `src/qrcode.php`, restrito ao modo Byte (qualquer URL tem letra minúscula, então nunca cabe no modo alfanumérico do padrão QR mesmo — os modos Numérico/Kanji/ECI do original ficaram de fora de propósito, não teriam uso aqui).
2. ~~`qr.php?codigo=AULA01` devolve PNG~~ Feito.
3. **Divergência do passo original:** `$_SERVER['SERVER_ADDR']` não é preenchido pelo `php -S` (servidor embutido, o único usado neste projeto) — fica vazio. Troquei para `$_SERVER['HTTP_HOST']`, que sempre existe nesse servidor e reflete exatamente o endereço que o navegador usou pra abrir a página que pediu o QR (a tela é aberta pelo IP da rede, então o `<img>` nela herda esse mesmo IP). `IP_FIXO` em `src/config.php` continua sendo a válvula de escape manual.

**Como testar** `bash bin/teste.sh` Caso 33: confere `200`, `Content-Type: image/png`, assinatura PNG (`89 50 4E 47`) e — quando há `python3`+`opencv` disponíveis (não faz parte do ambiente da sala, então o check é pulado sem eles) — decodifica o QR de verdade e confere que a URL bate com IP:porta+código. Também gerei QRs com strings de tamanhos variados (cruzando a fronteira de versão 9→10 do padrão QR) e decodifiquei todos com OpenCV pra validar a biblioteca antes de integrar no endpoint.

**Pronto quando** entrar exige zero digitação. **Testado via decodificação real do PNG gerado** (curl + OpenCV); apontar a câmera de um celular físico ainda não foi feito — depende de estar na mesma rede Wi-Fi, fora do escopo de teste automatizado.

---

## T16 · Tela de espera com QR grande *(concluída)*

**Arquivos:** `public/assets/tela.js`, `public/assets/tela.css`, `public/api/painel.php`

**Passos** ~~Na fase `aguardando`: QR ocupando ~40% da altura, código em monoespaçada gigante como alternativa, e contador de quantos já entraram.~~ Feito. `painel.php` ganhou o campo `online` também na fase `aguardando` (antes só existia em `respondendo`/`revelado` — mas `entrar.php` não bloqueia por fase, então já tem gente em `participantes` antes do professor iniciar).

**Bug achado testando de verdade (não estava no plano):** `tela.js` redesenha tudo a cada poll (2s) por decisão original do design (D3 — custo irrelevante numa tela só). Isso é inofensivo pra texto, mas recriar o `<img>` do QR a cada poll faz ele *recarregar* — e piscar na tela — a cada 2 segundos, sem necessidade (o QR não muda enquanto a fase continua `aguardando`). Corrigido: `renderizar()` agora, quando a fase já era `aguardando` no poll anterior e continua sendo, só atualiza o texto do contador (`.contador-entrada`) e não toca no `<img>`. Confirmado via `javascript_tool` no navegador: o `src` do QR é o mesmo objeto de imagem antes e depois do poll (`mesmaImg: true`), e o contador vai de "0 pessoas entraram" pra "1 pessoa entrou" no poll seguinte a alguém entrar.

**Como testar** Testado no navegador (Chrome via automação): tela `aguardando` mostra QR (740×740px, escala do T15) + código `AULA01` gigante + contador; entrar um aluno via `api/entrar.php` reflete no contador em ≤2s sem recarregar a imagem. Do fundo da sala, com projetor de verdade — ainda não testado (mesma pendência do T04).

**Pronto quando** ambos funcionam a 8 metros. **Escanear e ler o código já foi validado** (T15, decodificação real via OpenCV); a distância de 8 metros com projetor físico continua pendente.

---

## T16b · Projetor descobre a sessão ativa sozinho *(concluída)*

**Não estava no plano original — achado pelo usuário testando de verdade com `iniciar.bat`.**

**Entrega:** o script de partida sempre abria `tela.php?codigo=AULA01` fixo. Isso quebra assim que essa sessão semente é limpa (T18) ou nunca existiu — a tela travava em "Código de sala não encontrado", um beco sem saída que exigia editar a URL na mão pra apontar pro código de verdade (gerado na hora, aleatório, T08).

**Arquivos:** `public/api/sessao-ativa.php` *(novo)*, `public/assets/tela.js`, `iniciar.ps1`, `iniciar.sh`

**Como:**
- `api/sessao-ativa.php`: devolve o `codigo` da sessão não-encerrada mais recente (`ORDER BY id DESC LIMIT 1`), ou `null`. Sem autenticação (mesmo padrão de `api/painel.php`) — só devolve o código, que é público por natureza; nunca o `token_professor`.
- `tela.js`: sem `?codigo=` na URL, entra em "modo descoberta" — poll em `api/sessao-ativa.php` até achar algo, mostrando uma tela com o mesmo pulso "ao vivo" do T21 ("Procurando uma sessão ativa..."). Ao achar, atualiza a URL via `history.replaceState` (sem recarregar a página) e passa a pollar `api/painel.php` normalmente. **Se a sessão que estava mostrando sumir** (ex.: professor faz "Encerrar e limpar" e abre outra depois), volta sozinho pro modo descoberta — nunca mais precisa editar a URL na mão.
- **Um link com `?codigo=` explícito continua estrito** — erro de verdade se não existir. Decisão deliberada: os links de teste manual em `mapa-urls-teste.html` (e qualquer QA que precise apontar pra uma sessão específica com mais de uma ativa) dependem desse comportamento previsível.
- `iniciar.ps1`/`iniciar.sh` param de abrir um código fixo — abrem só `tela.php`, a descoberta faz o resto.

**Como testar** `bash bin/teste.sh` Caso 36: `api/sessao-ativa.php` sempre devolve ou um código que existe de verdade e não está encerrado, ou `null` quando o banco não tem nenhuma sessão não-encerrada (checagem robusta ao estado exato do banco, não a um valor fixo). Testado ponta a ponta no navegador: `tela.php` sem código descobre `AULA01` sozinho e atualiza a URL; ao apagar essa sessão (`comando.php acao=limpar`) a tela cai pra "Procurando uma sessão ativa..." sozinha; ao criar uma sessão nova, ela é descoberta e mostrada automaticamente, sem nenhuma ação manual. Link com código inválido explícito continua mostrando o erro estrito.

**Pronto quando** o professor nunca mais precisa editar a URL do projetor na mão, em nenhum cenário — sessão nova, sessão limpa, ou primeira vez usando o sistema. **Confirmado.**

---

## T17 · Script de partida e de parada *(concluída)*

**Arquivos:** `iniciar.bat`, `iniciar.ps1` — pedido do usuário trocou `iniciar.sh` por `iniciar.ps1` (ambiente é Windows; o `.bat` só chama o `.ps1`, pra não duplicar a lógica de detecção de IP/PHP em dois dialetos de shell).

**Passos** ~~Sobe o servidor, abre `tela.php` no navegador, imprime o IP no console.~~ Feito, e mais: testa se o PHP está instalado e na versão certa (8.2+) antes de tentar rodar qualquer coisa, com mensagem de erro clara e link de download se não achar. Cria o banco na primeira vez, se ainda não existir (sem sobrescrever numa segunda execução).

**Como testar** Rodei via `Start-Job` (sem travar o terminal no `php -S` final) e confirmei no log: detectou PHP 8.3.32, criou o banco, detectou o IP de rede correto (`192.168.0.2`, não a interface virtual do Hyper-V que também aparece na máquina), abriu o navegador de verdade (as requisições de `tela.php`/`tela.css`/`tela.js`/`painel.php` aparecem no log do servidor). Processo `php.exe` encerrado limpo depois.

**Pronto quando** você não precisa lembrar de nenhum comando.

---

**Ampliação pós-v1 (pedido do usuário):** dois problemas de operação em sala apareceram depois desta tarefa "concluída" — nenhum dos dois estava no escopo original do T17.

1. **Parar o servidor com segurança antes de desligar o notebook.** `iniciar.ps1` fica bloqueado no `php -S` final — fechar a janela (ou desligar a máquina sem fechar) mata o processo sem aviso. O SQLite em modo WAL já é resistente a um kill abrupto (não corrompe por si só), mas um `php.exe` pendurado em segundo plano pode segurar a porta 8080 e os arquivos `-wal`/`-shm`. `parar.bat`/`parar.ps1` *(novos)* acham o processo escutando na porta 8080 (`Get-NetTCPConnection`), confirmam que é mesmo um `php` antes de mexer, e encerram. **Testado de verdade**: subi um servidor, rodei `parar.ps1`, confirmei via `curl` que a porta parou de responder; rodei de novo sem servidor no ar e confirmou "nada a fazer" sem erro.
2. **O projeto roda num PC com Linux?** Sim — é PHP puro + SQLite + JS sem build step, nada específico de Windows no código (`bin/init-db.php` já trata `chmod` como no-op inofensivo fora do Linux/Mac; `bin/teste.sh` já roda em bash puro e só usa `taskkill` como fallback opcional). Só faltavam os scripts de conveniência. `iniciar.sh`/`parar.sh` *(novos)* espelham a mesma lógica do lado Windows: checam PHP 8.2+, e diferente do `.ps1` também checam as extensões `pdo_sqlite`/`gd` (em várias distros o `php-cli` vem sem elas por padrão — pacotes separados; no Windows isso já tinha me pegado uma vez com o `php.ini` do winget). Detecção de IP via `hostname -I`, abre o navegador com `xdg-open` se existir. **Não testado num Linux de verdade** (ambiente de desenvolvimento é Windows) — só validado sintaxe (`bash -n`) e o caminho "nada rodando" do `parar.sh`, que não depende de nenhuma ferramenta específica de Linux.

---

## T18 · Encerrar e limpar a sessão *(concluída)*

**Entrega:** próxima turma começa limpa, prova preservada.

**Arquivos:** `public/api/comando.php`, `public/assets/admin.js`

**Passos**
1. ~~Em `admin/sessao.php`, botão "Encerrar e limpar" com confirmação.~~ Feito — mas só aparece na tela de "Prova encerrada", não junto dos outros botões. **Decisão não prevista no texto original:** "limpar" apaga de vez as respostas dos alunos (dado que `plan.md` §8 lista "Exportar CSV da sessão" como prioridade **alta** no backlog, ainda não implementado) — deixar essa ação disponível a qualquer momento, como escape hatch (igual "encerrar"), arriscaria apagar sem querer uma prova em andamento. `comando.php` rejeita `acao=limpar` com `409 {"erro":"fase"}` se a sessão não estiver `encerrada`.
2. ~~`DELETE FROM sessoes WHERE id = ?` — o `CASCADE` leva participantes e respostas.~~ Feito, dentro de `comando.php` (não um endpoint novo — reaproveita a autenticação por `token_professor` já existente).
3. ~~**A prova permanece.**~~ Feito — `DELETE` só na tabela `sessoes`; `provas`/`questoes`/`alternativas` não são tocadas.
4. **Confirmação dupla**, igual ao "excluir prova" do T09d (`confirm()` + digitar "limpar" num `prompt()`) — mais forte que o `confirm()` único de "encerrar", porque apagar respostas de aluno é mais definitivo que só travar a prova.

**Como testar** `bash bin/teste.sh` Caso 35: sem `token_professor` certo não apaga (403); tentar `limpar` antes de `encerrar` dá 409; depois de encerrada, `limpar` remove a sessão e seus participantes (cascade) sem afetar participantes de **outra** sessão nem a prova. Testado também via curl ponta a ponta fora do script (aluno entra, responde, encerra, limpa, contagens batem, segunda tentativa de limpar dá 404 porque a sessão já sumiu).

**Pronto quando** participantes e respostas zeram e a contagem de provas não muda. **Confirmado** — testado com curl e pela bateria automatizada, não pelo botão físico na tela (mesma pendência de sempre: celular/navegador real, não simulação).

---

## T19 · Documento de setup *(concluída)*

**Arquivos:** `SETUP.md`

**Conteúdo**
1. ~~Configurar o roteador: SSID aberto, DHCP, sem senha.~~ Feito.
2. ~~IP fixo no notebook (ex.: `192.168.0.10`).~~ Feito.
3. ~~Liberar a porta 8080 no firewall~~ Feito, com o comando `New-NetFirewallRule` pronto pra copiar/colar.
4. ~~Desativar suspensão automática.~~ Feito.
5. ~~Checklist de aula.~~ Feito, incluindo conferir que a prova está **publicada** (T09d) antes de começar.

Também ganhou uma seção "O que precisa estar instalado" (PHP 8.2+, onde baixar, como confirmar) que não estava no escopo original — sem isso o documento pressupõe que a pessoa já sabe instalar PHP, o que não é dado.

**Como testar** Ainda não testado por outra pessoa de verdade (esse teste exige alguém sem contexto do projeto). Revisão própria: os passos batem com o que os scripts (`iniciar.bat`/`.ps1`) realmente fazem, e o comando de firewall foi copiado exatamente da sintaxe do PowerShell (não testado em execução real — exige administrador, fora do escopo de teste automatizado).

**Pronto quando** ela consegue. **Não verificado com uma pessoa real ainda.**

---

## T20 · Ensaio com turma real

Não é código. É a tarefa que decide se a v1 acabou.

**Passos** Turma pequena, prova de 3 questões, sem avisar que é teste.

**Medir**
- tempo do início da aula até a primeira questão no ar (meta: **< 3 min**)
- alunos que entraram sem ajuda
- aparelhos que caíram para o 4G
- alunos excluídos por falha técnica (meta: **zero**)

**Pronto quando** as duas metas batem. Se não baterem, o que falhou vira tarefa nova — e é normal.

---

# Bloco E — Polimento

**Não estava no plano original — pedido do usuário, depois de pesquisar Kahoot/Mentimeter/Slido em busca de ideias.**

**Fora de escopo de propósito, confirmado com o usuário antes de planejar:** nada de estética de gamificação (mascote, confete, cores cartunescas, pódio, ranking por velocidade de resposta) — isso já está travado em `arquitetura.md` §13 e no `CLAUDE.md` do projeto. O que entra aqui são só **técnicas** de polish (animação, hierarquia, layout) que cabem dentro do vocabulário já estabelecido ("Cartão-Resposta Vivo": flat, monoespaçada, vermelho único, zero sombra).

---

## T21 · Microinterações — mais "placar ao vivo", sem virar jogo *(concluída)*

**Entrega:** os números e resultados na tela reagem de um jeito que reforça a sensação de "ao vivo" — igual ao que já existe na bolha do aluno (`estilo.css`, pop com bounce ao marcar) — mas hoje só existe ali. O resto (contadores, barras) muda de estado seco, sem transição.

**Arquivos:** `public/assets/tela.js`, `public/assets/tela.css`, `public/assets/admin.js`, `public/assets/admin.css`

**Passos**
1. ~~Count-up nos contadores~~ Feito, mas exigiu mais que só CSS: `tela.js`/`admin.js` redesenhavam o DOM inteiro a cada poll (2s) — um elemento recriado do zero não tem "valor anterior" pra animar a partir dele. `renderizar()` nos dois arquivos ganhou uma chave de contexto (fase+ordem da questão); enquanto ela não muda, só atualiza o número existente no lugar (mesmo padrão que o T16 já tinha aberto pro QR da tela de espera), com um `requestAnimationFrame` interpolando do valor antigo (guardado em `data-*`) até o novo em ~450ms.
2. ~~Revelação em cascata~~ Feito — e corrigido um bug real no caminho: a largura da barra era setada *antes* de inserir o elemento no DOM, então a transição de `width` do CSS nunca tinha um "antes" pra animar (a barra só aparecia já no tamanho final, apesar do `transition: width 0.4s` já existir). Agora a largura nasce em `0%`, e um duplo `requestAnimationFrame` (garante que o navegador já pintou o 0% antes de mudar) empurra pro valor real. `.linha-barra` ganhou `animation-delay` por `nth-child` (0 a 0.28s). Como a fase `revelado` agora só redesenha uma vez (item 1), a animação toca uma vez só, não a cada poll.
3. ~~Indicador de "ao vivo"~~ Feito — `.pulso-ao-vivo`, um ponto de 0.5em ao lado de "Aguardando o professor", só opacidade (0.3↔1, 2s), `aria-hidden`.
4. ~~Feedback de toque~~ Feito em `admin.css` — `:active { transform: scale(0.97) }` em todos os botões, escopado dentro de `@media (prefers-reduced-motion: no-preference)`.
5. ~~`prefers-reduced-motion`~~ Feito em tudo (JS: `animarContador`/`renderizarResultado` checam `matchMedia` antes de animar; CSS: `.linha-barra`/`.pulso-ao-vivo`/`:active` desligados no bloco `reduce` já existente).

**Como testar** `bash bin/teste.sh` continua 72/72 (nada de servidor mudou). No navegador, via `javascript_tool` (o `resize_window`/captura de frame da automação não respondeu bem neste ambiente, que roda com `prefers-reduced-motion: reduce` por padrão — o que também serviu pra confirmar que a regra de acessibilidade funciona: `getComputedStyle` do pulso mostrou `animationName: none` até eu sobrescrever `matchMedia` pra simular `no-preference`): contador de "responderam" foi de `2 de 2` pra `2 de 3` corretamente; nó DOM das barras e da presença do admin confirmado como o **mesmo elemento** antes/depois de 2 polls (`mesmoNo: true`) — prova de que parou de redesenhar à toa; zero erros no console em toda a bateria de testes manuais.

**Pronto quando** os cinco pontos acima estão implementados, `prefers-reduced-motion` desliga tudo (confirmado empiricamente, não só por código), e nenhuma cor nova foi introduzida (Regra do Sinal Duplo e paleta de `DESIGN.md` continuam intactas).

---

## T22 · Admin de conteúdo desktop-first, com fluxo guiado *(concluída)*

**Entrega:** quem cria uma prova faz isso numa tela pensada pra mouse+teclado, com menu fixo e sabendo exatamente qual é o próximo passo — sem perder a possibilidade de usar do celular quando precisar.

**Escopo confirmado com o usuário:** só as telas de **conteúdo** — `provas.php`, `questoes.php`, `questao.php`, `importar-csv.php`, `senha.php`, `nova-sessao.php`, `index.php`. **`sessao.php` (controle ao vivo) fica fora — continua mobile-first/touch, decisão travada em `arquitetura.md` §7 (professor comanda andando pela sala).**

**Arquivos:** `public/admin/*.php` (todas as 7 páginas de conteúdo), `src/admin_layout.php` *(novo — `abrirLayoutAdmin()`/`fecharLayoutAdmin()`/`fluxoProva()`, sem framework nenhum, só `require` de um parcial PHP)*, `public/assets/admin.css` *(revisão grande)*

**Passos**
1. ~~Shell com menu lateral~~ Feito — `<nav>` fixa (Sessões · Provas · Nova sessão · Importar CSV · Trocar senha) com a página atual marcada (`.ativo`), layout de duas colunas em telas largas.
2. ~~Responsivo de verdade~~ Feito — breakpoint em 768px, menu vira um toggle recolhido no topo (truque de `<input type="checkbox">` + `~`, sem JS, mesma família do `<details>` já usado no editor de questão). Validado forçando as regras do media query via `javascript_tool` no navegador (o `resize_window` da automação não respondeu neste ambiente) — menu abre/fecha e mostra os 5 itens com "Provas" destacado.
3. ~~Fluxo guiado~~ Feito, com uma diferença do que foi escrito aqui: o stepper (`fluxoProva()`) só aparece em `questoes.php`, `questao.php` e `nova-sessao.php` (quando já existe prova publicada) — não em `provas.php`, que é o hub de **todas** as provas, sem um "passo atual" único pra mostrar. Também virou acionável, não só informativo: `questoes.php` ganhou os botões "Publicar prova (passo 3)" e "Abrir sessão (passo 4)" direto na tela, sem precisar voltar pra `provas.php`.
4. ~~Revisão de todos os botões~~ Feito — `.conteudo-admin .botao-acao`/`.botao-secundario` caem pra `min-height: 44px` (só dentro do novo shell — `sessao.php` continua com os 64px originais, regra escopada por seletor, não alterada globalmente). `:hover`/`:focus-visible` novos em todos os botões do admin (incluindo os de `sessao.php`, ganho de graça e inofensivo lá).
5. ~~Sem mexer em `sessao.php`~~ Confirmado — zero linha tocada nesse arquivo; ele nem usa `admin_layout.php`.

**Como testar** `bash bin/teste.sh` — 72/72, nenhuma lógica de servidor mudou. Visual no navegador (1440px): sidebar, stepper reagindo ao estado real da prova (`0 questões` → step 2 ativo; publicada → step 4 ativo com CTA "Abrir sessão"), editor de questão limpo. Mobile (forçando as regras do breakpoint): menu recolhe pra um toggle, abre a lista completa ao clicar, "Provas" continua destacado.

**Pronto quando** as sete páginas de conteúdo usam o mesmo shell, o fluxo guiado está visível nelas, e `bin/teste.sh` continua passando 100%. **Confirmado.**

---

## T23 · Ideias de layout do graphify + telas/ (sem cor, layout só) *(concluída)*

**Não estava no plano original — pedido do usuário depois de rodar `/graphify` no projeto e pedir pra extrair ideias de layout (não cor) das imagens em `telas/` (capturas do QuizLive/Kahoot-like, salvas como referência externa).**

**Entrega:** três padrões de layout adaptados — nunca copiados — das referências, mantendo a paleta e o vocabulário visual do QuizSala intactos. Ideias descartadas nessa varredura por contrariar decisão já travada (ranking individual visível pro aluno, pódio com alturas por posição): registradas na conversa, não implementadas.

**Arquivos:** `public/admin/questoes.php`, `public/assets/admin.css`, `public/assets/admin.js`, `public/assets/aluno.js`, `public/assets/estilo.css`

**Passos**
1. **Linha de questão mais densa em `questoes.php`** — cada item da lista agora mostra as alternativas como tags inline (`alternativasDaQuestao()`, já existia, só não era usada aqui), a certa marcada com borda/negrito **e** um "✓" no texto (Regra do Sinal Duplo, nunca só cor). Antes era preciso abrir cada questão pra conferir o que tinha nela; agora dá pra revisar a prova inteira só na lista.
2. **"Zona de risco" isolada em `admin/sessao.php`** — `criarZonaRisco()` (novo em `admin.js`) separa fisicamente Encerrar/Limpar do resto dos botões, com borda tracejada + rótulo em caixa alta "ZONA DE RISCO", em vez de só mudar a cor do botão na mesma pilha. Reforço estrutural, não só visual, do que a Regra do Sinal Duplo já pedia.
3. **Comprovante do aluno com sinal duplo de verdade no papel** — o comprovante impresso (T04b) só diferenciava certo/errado por cor (`color: #1e7a34` / `#d9342b`), que some numa impressora P&B. Agora cada questão tem "✓ Acertou"/"✕ Errou" em texto, a resposta errada riscada (`text-decoration: line-through`), e a certa em negrito com borda à esquerda — lê certo mesmo sem tinta colorida.

**Como testar** `bash bin/teste.sh` — 73/73, nenhuma lógica de servidor mudou (é tudo camada de exibição). Visual no navegador: lista de questões mostrando as 4 alternativas com a certa marcada; `admin/sessao.php` com "Zona de risco" visualmente separada do botão Revelar/Próxima; comprovante testado forçando o CSS de impressão visível (a automação não tem preview de impressão nativo) — "Acertou"/"Errou" em texto, resposta errada riscada, certa em negrito com borda, tudo legível sem depender de cor.

**Pronto quando** as três ideias estão implementadas usando só o vocabulário visual já existente do QuizSala (nenhuma cor nova, nenhum elemento de gamificação) e `bin/teste.sh` continua passando 100%. **Confirmado.**

---

## Resumo

| Bloco | Tarefas | Status | Você consegue, ao terminar |
|---|---|---|---|
| A — Projetor | T01–T04, T04b | **Completo** (T04 falta validar em projetor físico real) | Aplicar uma questão avulsa, comandando pelo notebook; aluno vê placar/comprovante/agradecimento ao final |
| B — Controle | T05–T08 | **Completo** | Aplicar prova inteira pelo celular, abrindo sessões novas pra turmas diferentes |
| C — Admin | T09–T14, T09b–T09e | **Completo** (T14 falta validar em celular físico real) | Criar conteúdo sem tocar no banco — manual, por CSV, ou duplicando; publicar/despublicar/excluir com trava de segurança; trocar a própria senha |
| D — Operação | T15–T20 | **Parcial** — feito: T15 (QR Code), T16 (tela de espera), T17 (scripts de partida/parada), T18 (limpar sessão), T19 (`SETUP.md`). Falta: T20 (ensaio com turma real) | Entregar para outro professor usar |
| E — Polimento | T21–T23 | **Completo** | Admin de conteúdo usável num desktop de verdade; telas com mais energia de placar ao vivo; layout adaptado de referências externas sem virar gamificação |

**Além do plano original**, a pedido do usuário: T04b (placar/comprovante/agradecimento do aluno), T09b (importar CSV), T09c (explicação da resposta certa), T09d (publicar/despublicar/editar/excluir prova, com trava contra despublicar prova já iniciada), T09e (trocar a própria senha do admin), Bloco E inteiro (T21-T23, polimento visual, admin desktop-first e ideias de layout do graphify sobre `telas/`). Documentado em cada tarefa e em `arquitetura.md` §9.

**Também pendente, fora da numeração T01–T20:**
- Timer configurável por questão (duração definida no editor, botão "Iniciar tempo", bloqueia resposta até iniciar, não revela sozinho ao esgotar, marca "não respondeu" no banco) — pedido do usuário antes do Bloco C, adiado explicitamente pra depois do editor de questões existir. Ainda não implementado.
- Log de acesso à página da prova com hash de identificação do aparelho (celular/PC/tablet) — pedido do usuário, registrado no backlog (`plan.md` §8) com a ressalva de design que precisa resolver antes de implementar: o hash tem que identificar o aparelho sem identificar a pessoa, pra não contradizer a decisão de anonimato já tomada pro projeto (`plan.md` §10). Ainda não implementado.

O ponto de decisão real já passou: **T03** (revelação com acertos/erros) está no ar desde cedo — é o teste com a turma real (**T20**) que falta pra confirmar se a ideia funciona fora do ambiente de desenvolvimento.
