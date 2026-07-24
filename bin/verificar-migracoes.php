<?php

declare(strict_types=1);

// Self-teste de Db::migrar(): simula um banco criado antes de uma coluna
// existir no schema.sql e confere que a migracao aplica sem apagar dados e
// que rodar de novo (toda conexao chama migrar()) nao quebra.
require __DIR__ . '/../src/db.php';

$caminho = tempnam(sys_get_temp_dir(), 'quizsala_migra_') . '.sqlite';

$pdo = new PDO('sqlite:' . $caminho);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE sessoes (id INTEGER PRIMARY KEY, codigo TEXT)'); // schema pre-T25, sem "ativa"
$pdo->prepare('INSERT INTO sessoes (codigo) VALUES (?)')->execute(['AULA01']);

Db::migrar($pdo);

$colunas = array_column($pdo->query('PRAGMA table_info(sessoes)')->fetchAll(), 'name');
if (!in_array('ativa', $colunas, true)) {
    fwrite(STDERR, "FALHA: coluna 'ativa' nao foi criada\n");
    exit(1);
}

$codigo = $pdo->query('SELECT codigo FROM sessoes WHERE id = 1')->fetchColumn();
if ($codigo !== 'AULA01') {
    fwrite(STDERR, "FALHA: dado existente foi perdido na migracao\n");
    exit(1);
}

Db::migrar($pdo); // idempotente - rodar de novo nao pode falhar nem duplicar coluna

$pdo = null; // libera o lock do arquivo antes de apagar (Windows nao apaga arquivo aberto)
unlink($caminho);
echo "OK - Db::migrar adiciona 'ativa' preservando dados e e idempotente\n";
