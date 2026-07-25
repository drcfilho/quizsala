# QuizSala — Manual de uso

Guia do dia a dia: como criar uma prova, publicar, abrir uma sessão e aplicar em aula. Setup de rede/roteador/firewall (uma vez só, por notebook) fica em [`docs/SETUP.md`](docs/SETUP.md) — este documento assume que isso já está feito.

## Antes de começar: três telas, três públicos

- **Você (professor)** comanda tudo pelo **admin** — de dois jeitos diferentes, de propósito:
  - As páginas de **conteúdo** (provas, questões, sessões, senha) são pensadas pra desktop, com menu lateral — o trabalho de montar uma prova é mais rápido num teclado de verdade.
  - O **controle ao vivo** (`admin/sessao.php`) é isolado, pensado pro seu celular — você anda pela sala com ele na mão durante a aula.
- **Os alunos** entram pelo próprio celular, numa tela simples: código da sala (ou QR), responder, ver o resultado.
- **O projetor/TV** mostra uma tela só de leitura — ninguém interage com ela, ela só exibe o que está acontecendo.

## 1. Criar uma prova

1. Acesse `admin/provas.php` (senha em `db/admin.senha`, ou a que você definiu em "Trocar senha").
2. Em "Nova prova", dê um título e clique em **Criar prova**.
3. Ela nasce como **rascunho** — não aparece pra ninguém ainda, nem pra abrir sessão. Isso é de propósito: dá pra montar com calma antes de publicar.

**Alternativa:** se você já tem as questões numa planilha, use **Importar CSV** (menu lateral) em vez de criar uma por uma. Formato: `enunciado, alternativa_a..e, correta (letra A-E), explicacao` — baixe o exemplo na própria tela. Uma linha inválida cancela a importação inteira (nada fica pela metade).

## 2. Adicionar questões

Clique em **Editar** na prova (ou ela já abre direto depois de criada). Você vai ver um indicador de progresso no topo (`1. Criar prova → 2. Questões → 3. Publicar → 4. Sessão`) mostrando onde você está.

Para cada questão:

1. **Nova questão** → escreva o enunciado, preencha pelo menos 2 alternativas, marque com o rádio qual é a certa.
2. **+ Explicação** (opcional, fica fechado por padrão) — anote por que a resposta certa está certa. Aparece no projetor no momento da revelação, junto com as barras de distribuição.
3. **+ Cronômetro** (opcional, fica fechado por padrão) — defina uma duração em segundos pra essa questão específica. Com isso preenchido, o projetor e o seu celular mostram uma contagem regressiva enquanto a questão está no ar. Ao zerar, ela só avisa visualmente (o rótulo muda pra "Tempo esgotado" e o botão Revelar ganha destaque) — a revelação continua sempre manual, um clique seu. Deixe em branco pra questão ficar sem cronômetro.
4. **Salvar questão**.

Na lista de questões dá pra reordenar (setas ↑/↓) e excluir — excluir renumera as seguintes automaticamente, então a prova nunca fica com um buraco na ordem.

**Testar antes de publicar:** o botão **"Testar prova (abre uma sessão)"** cria uma sessão de verdade na hora, mesmo com a prova ainda em rascunho — use pra conferir o fluxo completo (projetor + celular) antes de expor pra turma.

## 3. Publicar

Com pelo menos uma questão salva, o botão principal da tela de questões vira **"Publicar prova"**. Só provas publicadas aparecem no seletor de "Nova sessão" — é assim que uma prova fica disponível de verdade pro aluno, pro projetor e pro professor.

Mudou de ideia? **Despublicar** funciona a qualquer momento — exceto se já existe uma sessão dessa prova em andamento (`respondendo`/`revelado`). Isso é proposital: tirar o tapete no meio da aplicação seria pior que deixar terminar.

## 4. Abrir uma sessão

Com a prova publicada, clique em **"Abrir sessão"** (ou vá em **Nova sessão** no menu lateral):

1. Escolha a prova.
2. Escolha a identificação do aluno: **Anônimo** (apelido automático "Aluno 01", "Aluno 02"...) ou **Com nome** (aluno digita o próprio nome ao entrar).
3. **Abrir sessão** — você cai direto no controle ao vivo, já com o código da sala gerado (6 caracteres, sem `0 O 1 I 5 S` — nada de confundir letra com número projetado do fundo da sala).

Guarde esse link do controle: ele tem um token de professor embutido na URL (`?pt=...`) e fica salvo no navegador do seu celular. Sem esse token ninguém mais comanda a sessão — nem sabendo o código público da sala.

**Antes de projetar, ative essa sessão:** o QuizSala nunca escolhe uma sessão sozinho — nem a mais recente, nem uma "de exemplo" deixada de uma aula anterior. Vá em **Sessões** (menu lateral) e clique **"Ativar no projetor"** na sessão que você acabou de abrir. Ela ganha um selo **"● No projetor"**; é essa, e só essa, que aparece pra quem abrir o projetor sem um código na URL.

## 5. Durante a aula

**No projetor:** abra `tela.php`. Sem código na URL, ele mostra sozinho a sessão que você ativou no passo anterior:

- Antes de você clicar **Iniciar prova**, ele mostra uma tela de espera: QR Code grande, código em letras gigantes, contador de quantos já entraram.
- Se você encerrar e limpar essa sessão sem ativar outra, ele volta pra "Aguardando o início da sessão".
- Quer apontar pra uma sessão específica, ignorando qual está ativada? Abra com o código na URL: `tela.php?codigo=X`.

**No seu celular** (`admin/sessao.php`, o link que você guardou):

| Botão | O que faz |
|---|---|
| **Iniciar prova** | Sai de "aguardando" e libera a primeira questão |
| **Revelar** | Mostra acertos/erros e a distribuição por alternativa — no projetor e pro aluno |
| **Próxima questão** | Avança; se era a última, encerra a prova sozinho |
| **Parar prova** | Força o fim a qualquer momento — inclusive antes de "Iniciar prova", se você abriu a sessão errada — vira o botão de escape, sempre disponível |

Detalhes que ajudam no dia a dia:

- O contador **"X online · Y responderam"** fica sempre visível. Ao bater 100%, o botão **Revelar** ganha destaque — é só um aviso, ele nunca dispara sozinho.
- Toque duplo sem querer não pula questão: o servidor só aplica o comando se a versão que o celular conhece bater com a atual.
- Ao **Revelar**, a explicação da questão (se você salvou uma) aparece no projetor junto com as barras de distribuição.
- Na última questão, **Próxima questão** encerra a prova e o projetor mostra o **resumo final**: participantes, questões, e acertos/erros/não-responderam por questão. Daí você decide: abrir outra sessão ou parar por aqui.
- No celular do aluno, a tela de placar avança sozinha pro "Obrigado por participar!" depois de 5 minutos parada — ninguém precisa lembrar de clicar em "Concluir".

## 6. Depois da aula

O controle ao vivo não some sozinho ao encerrar — ele só avisa "Prova encerrada." e te manda pro admin no computador. É lá, na lista de **Sessões** (menu lateral → Sessões → seção "Sessões encerradas"), que fica o botão **Limpar**: apaga a sessão inteira — participantes e respostas somem, **a prova continua existindo** pra usar de novo com outra turma. Pede confirmação dupla (confirmar + digitar "limpar"), porque é definitivo: se você ainda quer analisar os dados dessa aplicação, não limpe ainda. É de propósito que essa ação não fica no celular — é arrumação pra fazer com calma, na mesa, não uma decisão pra tomar no meio da aula.

Pra desligar o notebook com segurança, rode `parar.bat` (Windows) ou `./parar.sh` (Linux) antes — ele encerra o servidor sem deixar processo pendurado. Ver [`docs/SETUP.md`](docs/SETUP.md) pro resto do checklist de aula.

## Outras coisas úteis

- **Duplicar prova** (`provas.php`): copia questões e alternativas pra uma prova nova e independente — editar a cópia não mexe na original.
- **Trocar senha do admin** (menu lateral → "Trocar senha"): define sua própria senha em vez de usar a gerada automaticamente. Mínimo 6 caracteres.
- **Excluir prova**: apaga a prova, as questões e qualquer sessão dela — pede confirmação dupla (digitar "excluir") porque não tem volta.
- **Várias sessões da mesma prova**: dá pra abrir quantas sessões quiser da mesma prova, pra turmas diferentes — as respostas não se misturam entre sessões.

## Se algo der errado

- **Celular não entra na rede**: confira o SSID/roteador — ver checklist em `docs/SETUP.md`.
- **Página abre mas não atualiza**: o poll é a cada 2 segundos; espere um pouco antes de assumir que travou.
- **Prova não aparece pra abrir sessão**: ela precisa estar **publicada** (`provas.php`).
- **Botão do controle não funciona (nada acontece)**: o link provavelmente perdeu o `?pt=...` — volte em `admin/index.php` (lista de sessões ativas) e entre pelo link de lá.
- **Projetor preso em "Aguardando o início da sessão"**: ninguém ativou uma sessão ainda (ou a que estava ativa foi encerrada e limpa). Vá em **Sessões** e clique **"Ativar no projetor"** na sessão certa.
