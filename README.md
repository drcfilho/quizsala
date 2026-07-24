# QuizSala

Ferramenta de aula: o professor aplica uma prova de múltipla escolha numa sala sem internet, cada aluno responde pelo próprio celular, e a contagem de acertos e erros aparece na hora na tela projetada.

Documentação completa de arquitetura e decisões em [`arquitetura.md`](arquitetura.md). Fases, marcos e critério de pronto da v1 em [`plan.md`](plan.md). Quebra de tarefas em [`tasks.md`](tasks.md).

## Estado atual

**Fase 1 — Fluxo do aluno: completa.** Schema, entrada anônima/nomeada, polling por versão, registro de resposta com deduplicação, revelação de gabarito individual, tela do aluno completa. Bateria `bin/teste.sh` com 19 verificações, todas passando.

Restam as Fases 2 a 5 (painel do projetor, controle pelo celular, admin de provas, operação em sala) — detalhe em `plan.md`.

## Stack

PHP 8.2+ / SQLite (arquivo único, `PRAGMA journal_mode = WAL`) / CSS próprio / JavaScript puro. Sem framework, sem build step, sem internet no ambiente de uso (ver `arquitetura.md` seção 2).

## Rodando localmente

1. Confirme que a extensão `pdo_sqlite` está habilitada no PHP (`php -m | grep sqlite`). Se não estiver, descomente `extension=pdo_sqlite` no `php.ini`.

2. Recrie o banco com a prova de exemplo:
   ```sh
   php bin/init-db.php
   ```

3. Suba o servidor embutido do PHP a partir de `public/`:
   ```sh
   cd public && php -S 0.0.0.0:8080
   ```

4. Acesse `http://localhost:8080/index.php`, entre com o código `AULA01`.

5. Rode a bateria de testes ponta a ponta:
   ```sh
   bash bin/teste.sh
   ```

## Estrutura

```
bin/            init-db.php (recria o banco + seed), teste.sh (bateria ponta a ponta)
db/             schema.sql; quizsala.sqlite é gerado, fora do versionamento
src/            db.php (PDO singleton, WAL), util.php (JSON, escape, consultas de estado)
public/         raiz do servidor web — index.php, prova.php, api/, assets/
arquitetura.md  arquitetura, decisões travadas e contrato da API
plan.md         fases, marcos e critério de pronto da v1
tasks.md        quebra de tarefas por bloco, com passos e critérios de verificação
```
