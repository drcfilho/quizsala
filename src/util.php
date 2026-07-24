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

// Online = visto nos ultimos 6s (3x o intervalo de poll, tolera 1 poll
// perdido). Presenca por heartbeat, nunca trava avanco (design.md D6).
function contarOnline(PDO $pdo, int $sessaoId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM participantes WHERE sessao_id = ? AND last_seen >= strftime('%s','now') - 6"
    );
    $stmt->execute([$sessaoId]);

    return (int) $stmt->fetchColumn();
}

function contarResponderam(PDO $pdo, int $sessaoId, int $questaoId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM respostas WHERE sessao_id = ? AND questao_id = ?');
    $stmt->execute([$sessaoId, $questaoId]);

    return (int) $stmt->fetchColumn();
}

// T13: duplica prova inteira (questoes + alternativas) com IDs novos -
// titulo original preservado, so a copia leva o sufixo.
function duplicarProva(PDO $pdo, int $provaId): int
{
    $stmt = $pdo->prepare('SELECT titulo FROM provas WHERE id = ?');
    $stmt->execute([$provaId]);
    $original = $stmt->fetch();

    if ($original === false) {
        throw new InvalidArgumentException('prova nao encontrada');
    }

    $pdo->beginTransaction();

    $pdo->prepare('INSERT INTO provas (titulo) VALUES (?)')->execute([$original['titulo'] . ' (cópia)']);
    $novaProvaId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT * FROM questoes WHERE prova_id = ? ORDER BY ordem');
    $stmt->execute([$provaId]);
    $questoes = $stmt->fetchAll();

    $inserirQuestao = $pdo->prepare('INSERT INTO questoes (prova_id, enunciado, ordem) VALUES (?, ?, ?)');
    $inserirAlternativa = $pdo->prepare(
        'INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES (?, ?, ?, ?)'
    );

    foreach ($questoes as $questao) {
        $inserirQuestao->execute([$novaProvaId, $questao['enunciado'], $questao['ordem']]);
        $novaQuestaoId = (int) $pdo->lastInsertId();

        foreach (alternativasDaQuestao($pdo, (int) $questao['id']) as $alt) {
            $inserirAlternativa->execute([$novaQuestaoId, $alt['texto'], $alt['correta'], $alt['ordem']]);
        }
    }

    $pdo->commit();

    return $novaProvaId;
}

// T12: exclui uma questao e renumera as seguintes - sem isso "ordem" fica
// descontinua (1, 3, 4) e a sessao pula pra uma questao inexistente no meio
// da aula.
function excluirQuestao(PDO $pdo, int $provaId, int $questaoId): void
{
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM questoes WHERE id = ? AND prova_id = ?')->execute([$questaoId, $provaId]);

    $stmt = $pdo->prepare('SELECT id FROM questoes WHERE prova_id = ? ORDER BY ordem');
    $stmt->execute([$provaId]);
    $restantes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $atualizar = $pdo->prepare('UPDATE questoes SET ordem = ? WHERE id = ?');
    foreach ($restantes as $i => $id) {
        $atualizar->execute([$i + 1, $id]);
    }

    $pdo->commit();
}

// T12: troca "ordem" com a vizinha (direcao -1 sobe, +1 desce). Fora dos
// limites (primeira/ultima) nao faz nada, silenciosamente.
function moverQuestao(PDO $pdo, int $provaId, int $questaoId, int $direcao): void
{
    $stmt = $pdo->prepare('SELECT id, ordem FROM questoes WHERE prova_id = ? ORDER BY ordem');
    $stmt->execute([$provaId]);
    $questoes = $stmt->fetchAll();

    $indice = null;
    foreach ($questoes as $i => $q) {
        if ((int) $q['id'] === $questaoId) {
            $indice = $i;
            break;
        }
    }

    $vizinho = $indice === null ? null : $indice + $direcao;
    if ($vizinho === null || $vizinho < 0 || $vizinho >= count($questoes)) {
        return;
    }

    $a = $questoes[$indice];
    $b = $questoes[$vizinho];

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE questoes SET ordem = ? WHERE id = ?')->execute([(int) $b['ordem'], (int) $a['id']]);
    $pdo->prepare('UPDATE questoes SET ordem = ? WHERE id = ?')->execute([(int) $a['ordem'], (int) $b['id']]);
    $pdo->commit();
}

// D5: apelido sequencial no modo anonimo - so pro painel ter o que exibir.
function proximoApelidoAnonimo(PDO $pdo, int $sessaoId): string
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM participantes WHERE sessao_id = ?');
    $stmt->execute([$sessaoId]);
    $n = (int) $stmt->fetchColumn() + 1;

    return sprintf('Aluno %02d', $n);
}
