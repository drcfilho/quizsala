<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/util.php';

// Codigo e sempre armazenado maiusculo (bin/init-db.php, T08 futuro) - sem
// normalizar aqui, "aula01" nunca bate com "AULA01" (SQLite e case-sensitive
// por padrao). autocapitalize no campo e so dica de teclado mobile, nao
// garante nada no desktop nem em colar texto.
$codigo = strtoupper(trim((string) ($_POST['codigo'] ?? '')));
$nome = trim((string) ($_POST['nome'] ?? ''));

$pdo = Db::conexao();
$sessao = $codigo !== '' ? sessaoPorCodigo($pdo, $codigo) : null;

if ($sessao === null || $sessao['fase'] === 'encerrada') {
    header('Location: ../index.php?erro=codigo&s=' . rawurlencode($codigo));
    exit;
}

if ($sessao['identificacao'] === 'nome' && $nome === '') {
    header('Location: ../index.php?erro=nome&s=' . rawurlencode($codigo));
    exit;
}

$apelido = $sessao['identificacao'] === 'anonimo'
    ? proximoApelidoAnonimo($pdo, (int) $sessao['id'])
    : cortar($nome, 60);

$token = gerarToken();

$pdo->prepare(
    'INSERT INTO participantes (sessao_id, token, nome, last_seen) VALUES (?, ?, ?, strftime(\'%s\',\'now\'))'
)->execute([$sessao['id'], $token, $apelido]);

header('Location: ../prova.php#t=' . $token);
