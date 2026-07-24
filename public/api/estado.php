<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/util.php';

$token = (string) ($_GET['token'] ?? '');
$vCliente = (int) ($_GET['v'] ?? -1);

$pdo = Db::conexao();
$participante = participantePorToken($pdo, $token);

if ($participante === null) {
    jsonResponder(['erro' => 'token'], 404);
    exit;
}

// D3: todo poll grava presenca, ganho de graca - efeito colateral intencional.
$pdo->prepare('UPDATE participantes SET last_seen = strftime(\'%s\',\'now\') WHERE id = ?')
    ->execute([$participante['id']]);

$stmt = $pdo->prepare('SELECT * FROM sessoes WHERE id = ?');
$stmt->execute([$participante['sessao_id']]);
$sessao = $stmt->fetch();

$versaoAtual = (int) $sessao['versao'];

if ($vCliente === $versaoAtual) {
    jsonResponder(['v' => $versaoAtual]);
    exit;
}

$questao = null;
$escolhida = null;
$correta = null;

if (in_array($sessao['fase'], ['respondendo', 'revelado'], true)) {
    $questaoAtual = questaoPorOrdem($pdo, (int) $sessao['prova_id'], (int) $sessao['questao_atual']);

    if ($questaoAtual !== null) {
        $alternativas = alternativasDaQuestao($pdo, (int) $questaoAtual['id']);

        $questao = [
            'ordem' => (int) $sessao['questao_atual'],
            'total' => totalQuestoes($pdo, (int) $sessao['prova_id']),
            'enunciado' => $questaoAtual['enunciado'],
            'alternativas' => array_values(array_map(
                fn (array $a, int $i) => ['id' => (int) $a['id'], 'letra' => letraAlternativa($i), 'texto' => $a['texto']],
                $alternativas,
                array_keys($alternativas)
            )),
        ];

        $stmt = $pdo->prepare(
            'SELECT alternativa_id FROM respostas WHERE participante_id = ? AND questao_id = ?'
        );
        $stmt->execute([$participante['id'], $questaoAtual['id']]);
        $escolhida = $stmt->fetchColumn();
        $escolhida = $escolhida !== false ? (int) $escolhida : null;

        if ($sessao['fase'] === 'revelado') {
            foreach ($alternativas as $a) {
                if ((int) $a['correta'] === 1) {
                    $correta = (int) $a['id'];
                    break;
                }
            }
        }
    }
}

$payload = [
    'v' => $versaoAtual,
    'fase' => $sessao['fase'],
    'nome' => $participante['nome'],
    'questao' => $questao,
    'escolhida' => $escolhida,
];

if ($sessao['fase'] === 'revelado') {
    $payload['correta'] = $correta;
}

if ($sessao['fase'] === 'encerrada') {
    $payload['resultado'] = resultadoParticipante($pdo, (int) $sessao['prova_id'], (int) $participante['id']);
}

jsonResponder($payload);
