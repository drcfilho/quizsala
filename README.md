# QuizSala

Ferramenta de aula: o professor aplica uma prova de múltipla escolha numa sala sem internet, cada aluno responde pelo próprio celular, e a contagem de acertos e erros aparece na hora na tela projetada.

Documentação completa de arquitetura e decisões em [`arquitetura.md`](arquitetura.md). Fases, marcos e critério de pronto da v1 em [`plan.md`](plan.md). Quebra de tarefas em [`tasks.md`](tasks.md). Guia de instalação e checklist de aula em [`SETUP.md`](SETUP.md). **Manual de uso do dia a dia (criar prova, publicar, aplicar em aula) em [`MANUAL.md`](MANUAL.md).**

## Estado atual

**Blocos A-C completos** (painel do projetor, controle do professor, admin de provas — T01-T14, mais T08). Também implementado, além do plano original, a pedido do usuário: placar final do aluno com comprovante em PDF, importação de prova por CSV, campo de explicação da resposta certa, publicar/despublicar prova (com trava contra despublicar prova já em aplicação), editar/duplicar/excluir prova. Ver `tasks.md` pros detalhes de cada um.

**Bloco D (operação em sala):** T17 (scripts de partida `iniciar.bat`/`iniciar.ps1`) e T19 (`SETUP.md`) prontos. Faltam T15-T16 (QR Code offline — hoje a sala é acessada digitando o código, sem QR) e T18 (encerrar/limpar sessão pelo admin — hoje só via SQL direto). T20 (ensaio com turma real) ainda não aconteceu.

**Ainda não verificado em hardware real:** legibilidade do painel num projetor físico (T04) e o fluxo do admin num celular físico de verdade além do teste em viewport reduzido (T14).

Bateria `bin/teste.sh` com 54 verificações ponta a ponta, todas passando.

## Stack

PHP 8.2+ / SQLite (arquivo único, `PRAGMA journal_mode = WAL`) / CSS próprio / JavaScript puro. Sem framework, sem build step, sem internet no ambiente de uso (ver `arquitetura.md` seção 2).

## Rodando localmente

Guia completo (requisitos, rede da sala, firewall, checklist) em [`SETUP.md`](SETUP.md). Resumo rápido:

1. PHP 8.2+ instalado e no `PATH`, com `pdo_sqlite` habilitado (`php -m | grep sqlite`).
2. Windows: duplo clique em `iniciar.bat` — confere os requisitos, cria o banco na primeira vez, sobe o servidor e abre o projetor.
3. Manual (qualquer sistema):
   ```sh
   php bin/init-db.php
   cd public && php -S 0.0.0.0:8080
   ```
   Acesse `http://localhost:8080/index.php` (aluno, código `AULA01`) ou `http://localhost:8080/admin/index.php` (professor/admin, senha em `db/admin.senha`).
4. Bateria de testes ponta a ponta:
   ```sh
   bash bin/teste.sh
   ```

## Estrutura

```
bin/             init-db.php (recria o banco + seed), teste.sh (bateria ponta a ponta)
db/              schema.sql; quizsala.sqlite e admin.senha são gerados, fora do versionamento
src/             db.php (PDO singleton, WAL), util.php (JSON, escape, consultas, validação/CSV), auth.php (senha do admin, CSRF)
public/          raiz do servidor web — index.php, prova.php, tela.php, admin/, api/, assets/, exemplos/
iniciar.bat      duplo clique no Windows - chama o iniciar.ps1
iniciar.ps1      testa PHP instalado, cria o banco se preciso, sobe o servidor, abre o navegador
arquitetura.md   arquitetura, decisões travadas e contrato da API
plan.md          fases, marcos e critério de pronto da v1
tasks.md         quebra de tarefas por bloco, com passos e critérios de verificação
SETUP.md         instalação, configuração de rede/firewall, checklist de aula
MANUAL.md        manual de uso: criar prova, publicar, abrir sessão, aplicar em aula
PRODUCT.md       registro, públicos, personalidade e princípios de design
DESIGN.md        sistema visual: cores, tipografia, elevação, componentes
```
