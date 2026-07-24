<?php

declare(strict_types=1);

// D1 (design.md): SQLite em arquivo unico, WAL pra leitura concorrente
// durante escrita, busy_timeout cobre o pico de varios alunos respondendo
// quase juntos.
final class Db
{
    private static ?PDO $conexao = null;

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

        self::$conexao = $pdo;

        return $pdo;
    }
}
