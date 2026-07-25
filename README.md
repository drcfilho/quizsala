# QuizSala

Ferramenta de aula: o professor aplica uma prova de múltipla escolha numa sala sem internet, cada aluno responde pelo próprio celular, e a contagem de acertos e erros aparece na hora na tela projetada.

## Documentação

| Arquivo | Pra quê |
|---|---|
| [`docs/SETUP.md`](docs/SETUP.md) | Instalar e configurar (rede, firewall, checklist de aula) |
| [`MANUAL.md`](MANUAL.md) | Usar no dia a dia — criar prova, publicar, aplicar em aula |
| [`docs/arquitetura.md`](docs/arquitetura.md) | Arquitetura, decisões travadas, contrato da API |
| [`docs/plan.md`](docs/plan.md) / [`docs/tasks.md`](docs/tasks.md) | Fases e quebra de tarefas do desenvolvimento |
| [`docs/PRODUCT.md`](docs/PRODUCT.md) / [`docs/DESIGN.md`](docs/DESIGN.md) | Contexto de produto e sistema visual |

## Como rodar

**Windows, do jeito fácil:** dê duplo clique em `iniciar.bat`.

Ele verifica sozinho se tem PHP e Visual C++ Redistributable instalados:
- **Se tiver os dois**, sobe o servidor direto.
- **Se faltar algo**, mostra um menu com as opções — instalar via `winget`, instalar só o Visual C++ (instalador embutido em `utils\`), usar a cópia portátil de PHP (`php\`, sem instalar nada no sistema), ou sair pra instalar manualmente.

Na primeira vez, ele também cria o banco (`db/quizsala.sqlite`) com uma prova de exemplo e abre o projetor no navegador.

**Manual, qualquer sistema** (precisa de PHP 8.2+ com `pdo_sqlite`):
```sh
php bin/init-db.php
cd public && php -S 0.0.0.0:8080
```
Acesse `http://localhost:8080/index.php` (aluno, código `AULA01`) ou `http://localhost:8080/admin/index.php` (professor/admin — a senha aparece no console ao criar o banco, e fica salva em `db/admin.senha`).

**Bateria de testes ponta a ponta:**
```sh
bash bin/teste.sh
```

Guia completo (rede da sala, firewall, checklist) em [`docs/SETUP.md`](docs/SETUP.md).

## Stack

PHP 8.2+ · SQLite (arquivo único) · Bulma (CSS vendorizado, ver `docs/DESIGN.md`) · JavaScript puro. Sem build step, sem internet no ambiente de uso.

## Estrutura

```
bin/             init-db.php (recria o banco + seed), teste.sh (bateria ponta a ponta)
db/              schema.sql; quizsala.sqlite e admin.senha são gerados, fora do versionamento
src/             db.php (PDO singleton, WAL), util.php (JSON/escape/CSV), auth.php (senha do admin, CSRF)
public/          raiz do servidor web — index.php, prova.php, tela.php, admin/, api/, assets/, exemplos/
manual/          manual.html — mesmo conteúdo do MANUAL.md, autocontido pra abrir no navegador
dbadmin/         phpLiteAdmin local (avançado) — ver "Mexer direto no banco" em docs/SETUP.md
docs/            documentação de arquitetura, produto, design e planejamento
iniciar.bat/ps1  duplo clique no Windows — detecta PHP/VC++, cria o banco, sobe o servidor, abre o navegador
parar.bat/ps1/sh encerra o servidor com segurança
php/             cópia portátil do PHP (php.exe + ext/pdo_sqlite + php.ini) — não versionada, ver docs/SETUP.md
utils/           instalador do Visual C++ Redistributable — não versionado, ver docs/SETUP.md
```

## Estado atual

- **Prontos:** projetor, controle do professor, admin de provas — provas, questões, publicar/despublicar, editar/duplicar/excluir, importar CSV, cronômetro por questão, explicação da resposta, placar final com PDF.
- **Faltando:** QR Code offline pra entrada (hoje é só código digitado) e encerrar/limpar sessão pelo admin sem depender de SQL direto.
- **Ainda não testado em hardware real:** legibilidade num projetor físico, e o admin num celular de verdade (só testado em viewport reduzido no navegador).

Detalhes por tarefa em `docs/tasks.md`. Bateria `bin/teste.sh`: 109 verificações passando (6 falhas conhecidas, pré-existentes, no fluxo de troca de senha — não afetam o uso normal).
