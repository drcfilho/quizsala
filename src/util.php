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

// T08: abre sessao nova pra uma prova (usada por nova-sessao.php e pelo
// "Testar prova" de questoes.php) - codigo + token_professor gerados aqui,
// fase/questao_atual/versao ficam no default do schema (aguardando).
function criarSessao(PDO $pdo, int $provaId, string $identificacao = 'anonimo'): array
{
    $codigo = gerarCodigoSala($pdo);
    $tokenProfessor = gerarToken();

    $pdo->prepare(
        'INSERT INTO sessoes (prova_id, codigo, token_professor, modo, identificacao)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$provaId, $codigo, $tokenProfessor, 'sincrono', $identificacao]);

    return ['codigo' => $codigo, 'token_professor' => $tokenProfessor];
}

// T08: codigo de 6 caracteres sem ambiguidade visual (sem 0 O 1 I 5 S) -
// alguem vai ler isso projetado do fundo da sala. Regenera em caso de
// colisao contra o UNIQUE (codigo).
function gerarCodigoSala(PDO $pdo): string
{
    $alfabeto = '2346789ABCDEFGHJKLMNPQRTUVWXYZ';

    do {
        $codigo = '';
        for ($i = 0; $i < 6; $i++) {
            $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
        $existe = sessaoPorCodigo($pdo, $codigo) !== null;
    } while ($existe);

    return $codigo;
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

    $inserirQuestao = $pdo->prepare(
        'INSERT INTO questoes (prova_id, enunciado, explicacao, ordem) VALUES (?, ?, ?, ?)'
    );
    $inserirAlternativa = $pdo->prepare(
        'INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES (?, ?, ?, ?)'
    );

    foreach ($questoes as $questao) {
        $inserirQuestao->execute([$novaProvaId, $questao['enunciado'], $questao['explicacao'], $questao['ordem']]);
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

// Resultado final do aluno (fase encerrada): placar e, por questao, o que
// ele respondeu vs a correta - base tanto do placar quanto do comprovante.
function resultadoParticipante(PDO $pdo, int $provaId, int $participanteId): array
{
    $stmt = $pdo->prepare('SELECT * FROM questoes WHERE prova_id = ? ORDER BY ordem');
    $stmt->execute([$provaId]);
    $questoes = $stmt->fetchAll();

    $stmtResposta = $pdo->prepare(
        'SELECT alternativa_id FROM respostas WHERE participante_id = ? AND questao_id = ?'
    );

    $itens = [];
    $acertos = 0;

    foreach ($questoes as $questao) {
        $alternativas = alternativasDaQuestao($pdo, (int) $questao['id']);
        $stmtResposta->execute([$participanteId, (int) $questao['id']]);
        $escolhidaId = $stmtResposta->fetchColumn();
        $escolhidaId = $escolhidaId !== false ? (int) $escolhidaId : null;

        $escolhidaTexto = null;
        $corretaTexto = null;
        $acertou = false;

        foreach ($alternativas as $i => $alt) {
            $letra = letraAlternativa($i);
            if ((int) $alt['id'] === $escolhidaId) {
                $escolhidaTexto = $letra . ') ' . $alt['texto'];
            }
            if ((int) $alt['correta'] === 1) {
                $corretaTexto = $letra . ') ' . $alt['texto'];
                $acertou = $escolhidaId === (int) $alt['id'];
            }
        }

        if ($acertou) {
            $acertos++;
        }

        $itens[] = [
            'ordem' => (int) $questao['ordem'],
            'enunciado' => $questao['enunciado'],
            'escolhida' => $escolhidaTexto,
            'correta' => $corretaTexto,
            'acertou' => $acertou,
        ];
    }

    return ['acertos' => $acertos, 'total' => count($questoes), 'questoes' => $itens];
}

// T11: mesma regra de validacao usada pelo editor manual (questao.php) e
// pela importacao CSV - um so lugar pra nao dessincronizar as duas.
function validarQuestao(string $enunciado, array $alternativas, ?int $corretaIndice): array
{
    $erros = [];

    if ($enunciado === '') {
        $erros['enunciado'] = 'Escreva o enunciado.';
    }

    $preenchidas = array_filter($alternativas, fn (string $t) => trim($t) !== '');
    if (count($preenchidas) < 2) {
        $erros['alternativas'] = 'Preencha pelo menos 2 alternativas.';
    }

    if ($corretaIndice === null || !isset($alternativas[$corretaIndice]) || trim($alternativas[$corretaIndice]) === '') {
        $erros['correta'] = 'Marque qual alternativa é a certa.';
    }

    return $erros;
}

// Cria (questaoId = 0) ou atualiza uma questao + suas alternativas.
// Chamador ja validou com validarQuestao() antes de chegar aqui.
function salvarQuestao(
    PDO $pdo,
    int $provaId,
    int $questaoId,
    string $enunciado,
    array $alternativas,
    int $corretaIndice,
    ?string $explicacao
): int {
    if ($questaoId > 0) {
        $pdo->prepare('UPDATE questoes SET enunciado = ?, explicacao = ? WHERE id = ?')
            ->execute([$enunciado, $explicacao, $questaoId]);
        $pdo->prepare('DELETE FROM alternativas WHERE questao_id = ?')->execute([$questaoId]);
    } else {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) FROM questoes WHERE prova_id = ?');
        $stmt->execute([$provaId]);
        $ordem = (int) $stmt->fetchColumn() + 1;

        $pdo->prepare('INSERT INTO questoes (prova_id, enunciado, explicacao, ordem) VALUES (?, ?, ?, ?)')
            ->execute([$provaId, $enunciado, $explicacao, $ordem]);
        $questaoId = (int) $pdo->lastInsertId();
    }

    $inserirAlternativa = $pdo->prepare(
        'INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES (?, ?, ?, ?)'
    );
    $ordemAlt = 1;
    foreach ($alternativas as $i => $texto) {
        $texto = trim($texto);
        if ($texto === '') {
            continue;
        }
        $inserirAlternativa->execute([$questaoId, $texto, $i === $corretaIndice ? 1 : 0, $ordemAlt]);
        $ordemAlt++;
    }

    return $questaoId;
}

// Importa uma prova inteira de um CSV: enunciado, alternativa_a..e, correta
// (letra A-E), explicacao (opcional). Uma linha invalida cancela o import
// inteiro (nada fica pela metade) - erros trazem o numero da linha.
function importarProvaCsv(PDO $pdo, string $titulo, string $caminhoArquivo): array
{
    $fp = fopen($caminhoArquivo, 'r');
    if ($fp === false) {
        return ['ok' => false, 'prova_id' => null, 'erros' => ['Não foi possível ler o arquivo.']];
    }

    fgetcsv($fp); // cabecalho, descartado

    $numeroLinha = 1;
    $erros = [];
    $questoesValidas = [];

    while (($linha = fgetcsv($fp)) !== false) {
        $numeroLinha++;

        if (count(array_filter($linha, fn ($c) => trim((string) $c) !== '')) === 0) {
            continue;
        }

        $enunciado = trim((string) ($linha[0] ?? ''));
        $alternativas = [
            trim((string) ($linha[1] ?? '')),
            trim((string) ($linha[2] ?? '')),
            trim((string) ($linha[3] ?? '')),
            trim((string) ($linha[4] ?? '')),
            trim((string) ($linha[5] ?? '')),
        ];
        $letraCorreta = strtoupper(trim((string) ($linha[6] ?? '')));
        $explicacao = trim((string) ($linha[7] ?? ''));
        $explicacao = $explicacao !== '' ? $explicacao : null;

        $corretaIndice = (strlen($letraCorreta) === 1 && $letraCorreta >= 'A' && $letraCorreta <= 'E')
            ? (ord($letraCorreta) - 65)
            : null;

        $errosLinha = validarQuestao($enunciado, $alternativas, $corretaIndice);
        if (!empty($errosLinha)) {
            $erros[] = 'Linha ' . $numeroLinha . ': ' . implode(' ', $errosLinha);
            continue;
        }

        $questoesValidas[] = [$enunciado, $alternativas, $corretaIndice, $explicacao];
    }
    fclose($fp);

    if (empty($questoesValidas) && empty($erros)) {
        $erros[] = 'O arquivo não tem nenhuma questão.';
    }

    if (!empty($erros)) {
        return ['ok' => false, 'prova_id' => null, 'erros' => $erros];
    }

    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO provas (titulo) VALUES (?)')->execute([$titulo]);
    $provaId = (int) $pdo->lastInsertId();

    foreach ($questoesValidas as [$enunciado, $alternativas, $corretaIndice, $explicacao]) {
        salvarQuestao($pdo, $provaId, 0, $enunciado, $alternativas, $corretaIndice, $explicacao);
    }

    $pdo->commit();

    return ['ok' => true, 'prova_id' => $provaId, 'erros' => []];
}

// D5: apelido sequencial no modo anonimo - so pro painel ter o que exibir.
function proximoApelidoAnonimo(PDO $pdo, int $sessaoId): string
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM participantes WHERE sessao_id = ?');
    $stmt->execute([$sessaoId]);
    $n = (int) $stmt->fetchColumn() + 1;

    return sprintf('Aluno %02d', $n);
}
