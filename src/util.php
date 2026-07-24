<?php

declare(strict_types=1);

function jsonResponder(array $dados, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
}

// mbstring nao e garantida em toda instalacao PHP (design.md secao 10) -
// cai para preg_match com /u quando mb_substr nao existe.
function cortar(string $texto, int $tamanho): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, $tamanho);
    }

    if (preg_match('/^.{0,' . $tamanho . '}/us', $texto, $m) === 1) {
        return $m[0];
    }

    return substr($texto, 0, $tamanho);
}

function gerarToken(): string
{
    return bin2hex(random_bytes(16));
}

function letraAlternativa(int $indice): string
{
    return chr(65 + $indice);
}

function sessaoPorCodigo(PDO $pdo, string $codigo): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM sessoes WHERE codigo = ?');
    $stmt->execute([$codigo]);

    return $stmt->fetch() ?: null;
}

function participantePorToken(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM participantes WHERE token = ?');
    $stmt->execute([$token]);

    return $stmt->fetch() ?: null;
}

function questaoPorOrdem(PDO $pdo, int $provaId, int $ordem): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM questoes WHERE prova_id = ? AND ordem = ?');
    $stmt->execute([$provaId, $ordem]);

    return $stmt->fetch() ?: null;
}

function alternativasDaQuestao(PDO $pdo, int $questaoId): array
{
    $stmt = $pdo->prepare('SELECT * FROM alternativas WHERE questao_id = ? ORDER BY ordem');
    $stmt->execute([$questaoId]);

    return $stmt->fetchAll();
}

function totalQuestoes(PDO $pdo, int $provaId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM questoes WHERE prova_id = ?');
    $stmt->execute([$provaId]);

    return (int) $stmt->fetchColumn();
}

// D5: apelido sequencial no modo anonimo - so pro painel ter o que exibir.
function proximoApelidoAnonimo(PDO $pdo, int $sessaoId): string
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM participantes WHERE sessao_id = ?');
    $stmt->execute([$sessaoId]);
    $n = (int) $stmt->fetchColumn() + 1;

    return sprintf('Aluno %02d', $n);
}
