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

---

# Bloco D — Operação em sala

---

## T15 · QR Code gerado offline

**Entrega:** ninguém digita endereço IP.

**Arquivos:** `src/qrcode.php` *(biblioteca de arquivo único)*, `public/api/qr.php` *(novo)*

**Passos**
1. Biblioteca **local**. API de QR na internet é exatamente a dependência que quebra na hora da aula.
2. `qr.php?codigo=AULA01` devolve PNG de `http://<ip-do-servidor>:8080/index.php?s=AULA01`.
3. Detectar o IP com `$_SERVER['SERVER_ADDR']`, com opção de fixar em config — em máquina com várias interfaces a detecção erra.

**Como testar** Apontar a câmera de um celular que **nunca** usou o sistema e chegar direto na tela de entrada.

**Pronto quando** entrar exige zero digitação.

---

## T16 · Tela de espera com QR grande

**Arquivos:** `public/tela.php`

**Passos** Na fase `aguardando`: QR ocupando ~40% da altura, código em monoespaçada gigante como alternativa, e contador de quantos já entraram.

**Como testar** Do fundo da sala: dá para escanear e dá para ler o código.

**Pronto quando** ambos funcionam a 8 metros.

---

## T17 · Script de partida

**Arquivos:** `iniciar.bat`, `iniciar.sh`

**Passos** Sobe o servidor, abre `tela.php` no navegador, imprime o IP no console. Um clique.

**Como testar** Reiniciar o notebook e subir tudo só com o duplo clique.

**Pronto quando** você não precisa lembrar de nenhum comando.

---

## T18 · Encerrar e limpar a sessão

**Entrega:** próxima turma começa limpa, prova preservada.

**Passos**
1. Em `admin/sessao.php`, botão "Encerrar e limpar" com confirmação.
2. `DELETE FROM sessoes WHERE id = ?` — o `CASCADE` leva participantes e respostas.
3. **A prova permanece.** Só a aplicação some.

**Como testar**
```bash
php -r '$p=new PDO("sqlite:db/quizsala.sqlite");
foreach($p->query("SELECT (SELECT COUNT(*) FROM provas) provas,
  (SELECT COUNT(*) FROM participantes) participantes,
  (SELECT COUNT(*) FROM respostas) respostas") as $r) print_r($r);'
```

**Pronto quando** participantes e respostas zeram e a contagem de provas não muda.

---

## T19 · Documento de setup

**Arquivos:** `SETUP.md`

**Conteúdo**
1. Configurar o roteador: SSID aberto, DHCP, sem senha.
2. IP fixo no notebook (ex.: `192.168.0.10`).
3. Liberar a porta 8080 no firewall — **é aqui que trava na primeira vez**, e o sintoma engana: funciona no `localhost` e não funciona em nenhum celular.
4. Desativar suspensão automática.
5. Checklist de aula.

**Como testar** Outra pessoa monta tudo seguindo só o documento, sem perguntar nada.

**Pronto quando** ela consegue.

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

## Resumo

| Bloco | Tarefas | Horas | Você consegue, ao terminar |
|---|---|---|---|
| A — Projetor | T01–T04 | 5 | Aplicar uma questão avulsa, comandando pelo notebook |
| B — Controle | T05–T08 | 4 | Aplicar prova inteira pelo celular, em várias turmas |
| C — Admin | T09–T14 | 7 | Criar conteúdo sem tocar no banco |
| D — Operação | T15–T20 | 7 | Entregar para outro professor usar |

**23 horas.** Depois de qualquer tarefa o sistema fica utilizável — parar no T04 já dá uma ferramenta de aula, mais rústica.

O ponto de decisão real é o **T03**: é ali que o número aparece na parede e você descobre se a ideia funciona com gente de verdade. Se a reação da turma não vier, vale repensar o conceito antes de investir as 18 horas restantes.
