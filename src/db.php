<?php

declare(strict_types=1);

// D1 (design.md): SQLite em arquivo unico, WAL pra leitura concorrente
// durante escrita, busy_timeout cobre o pico de varios alunos respondendo
// quase juntos.
final class Db
{
    private static ?PDO $conexao = null;

    // Bancos ja existentes (db/quizsala.sqlite de uma instalacao antiga) nao
    // ganham colunas novas do schema.sql sozinhos - so bin/init-db.php le o
    // schema, e so roda em banco inexistente. Cada linha aqui e uma coluna
    // que schema.sql passou a ter depois de o banco ja existir; aplicada uma
    // vez, sozinha, sem apagar dados (achado: erro "no such column: ativa"
    // rodando o servidor manualmente num banco criado antes da T25).
    private const MIGRACOES = [
        ['sessoes', 'ativa', 'ALTER TABLE sessoes ADD COLUMN ativa INTEGER NOT NULL DEFAULT 0 CHECK (ativa IN (0,1))'],
    ];

    public static function conexao(): PDO
    {
        if (self::$conexao !== null) {
            return self::$conexao;
        }

        $caminho = __DIR__ . '/../db/quizsala.sqlite';
        $pdo = new PDO('sqlite:' . $caminho);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 3000');
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::migrar($pdo);

        self::$conexao = $pdo;

        return $pdo;
    }

    // ponytail: lista de ALTER TABLE aplicados na conexao, uma vez cada
    // (idempotente via PRAGMA table_info). So cobre "adicionar coluna" -
    // SQLite nao suporta ALTER pra renomear/remover coluna sem recriar a
    // tabela; se isso for preciso, trocar por uma lib de migracao de verdade.
    public static function migrar(PDO $pdo): void
    {
        foreach (self::MIGRACOES as [$tabela, $coluna, $ddl]) {
            $colunas = array_column($pdo->query("PRAGMA table_info($tabela)")->fetchAll(), 'name');
            if (!in_array($coluna, $colunas, true)) {
                $pdo->exec($ddl);
            }
        }
    }
}
