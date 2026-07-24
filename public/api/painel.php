<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/util.php';

// Sem gate de versao (design.md D3): e uma tela so, e os contadores mudam
// por acao do aluno, nao do professor - o custo de recalcular a cada poll
// e irrelevante numa unica tela.
$codigo = (string) ($_GET['codigo'] ?? '');

$pdo = Db::conexao();
$sessao = sessaoPorCodigo($pdo, $codigo);

if ($sessao === null) {
    jsonResponder(['erro' => 'codigo'], 404);
    exit;
}

$payload = ['fase' => $sessao['fase']];

if (in_array($sessao['fase'], ['respondendo', 'revelado'], true)) {
    $questaoAtual = questaoPorOrdem($pdo, (int) $sessao['prova_id'], (int) $sessao['questao_atual']);

    if ($questaoAtual !== null) {
        $payload['questao'] = [
            'ordem' => (int) $sessao['questao_atual'],
            'total' => totalQuestoes($pdo, (int) $sessao['prova_id']),
            'enunciado' => $questaoAtual['enunciado'],
        ];
        $payload['online'] = contarOnline($pdo, (int) $sessao['id']);
        $payload['responderam'] = contarResponderam($pdo, (int) $sessao['id'], (int) $questaoAtual['id']);

        // D7: distribuicao (e a letra "correta") so existe a partir da
        // revelacao - antes disso o campo nem aparece no payload.
        if ($sessao['fase'] === 'revelado') {
            $alternativas = alternativasDaQuestao($pdo, (int) $questaoAtual['id']);
            $stmtContagem = $pdo->prepare(
                'SELECT COUNT(*) FROM respostas WHERE questao_id = ? AND alternativa_id = ?'
            );

            $distribuicao = [];
            $acertos = 0;
            $erros = 0;

            foreach ($alternativas as $i => $alternativa) {
                $stmtContagem->execute([$questaoAtual['id'], $alternativa['id']]);
                $n = (int) $stmtContagem->fetchColumn();
                $ehCorreta = (int) $alternativa['correta'] === 1;

                $distribuicao[] = [
                    'letra' => letraAlternativa($i),
                    'n' => $n,
                    'correta' => $ehCorreta,
                ];

                if ($ehCorreta) {
                    $acertos += $n;
                } else {
                    $erros += $n;
                }
            }

            $payload['distribuicao'] = $distribuicao;
            $payload['acertos'] = $acertos;
            $payload['erros'] = $erros;
        }
    }
}

jsonResponder($payload);
