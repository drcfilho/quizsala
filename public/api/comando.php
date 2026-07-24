<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/util.php';

// "iniciar" nao esta no contrato original de comando.php (tasks.md T05 lista
// so revelar|proxima|encerrar), mas sem ela a sessao nunca sai de
// "aguardando" (design.md secao 5: criada -> aguardando -[professor
// inicia]-> respondendo). Preenche essa lacuna entre arquitetura.md e
// tasks.md - sem ela o admin nunca aplicaria uma sessao nova do zero.
const ACOES_VALIDAS = ['iniciar', 'revelar', 'proxima', 'encerrar'];

$corpo = json_decode((string) file_get_contents('php://input'), true) ?? [];
$codigo = (string) ($corpo['codigo'] ?? '');
$acao = (string) ($corpo['acao'] ?? '');
$versaoEsperada = (int) ($corpo['versao_esperada'] ?? -1);

if (!in_array($acao, ACOES_VALIDAS, true)) {
    jsonResponder(['erro' => 'acao'], 400);
    exit;
}

$pdo = Db::conexao();
$sessao = sessaoPorCodigo($pdo, $codigo);

if ($sessao === null) {
    jsonResponder(['erro' => 'codigo'], 404);
    exit;
}

$faseAtual = $sessao['fase'];
$questaoAtual = (int) $sessao['questao_atual'];
$novaFase = null;
$novaQuestao = $questaoAtual;

switch ($acao) {
    case 'iniciar':
        if ($faseAtual !== 'aguardando') {
            jsonResponder(['erro' => 'fase'], 409);
            exit;
        }
        $novaFase = 'respondendo';
        $novaQuestao = 1;
        break;

    case 'revelar':
        if ($faseAtual !== 'respondendo') {
            jsonResponder(['erro' => 'fase'], 409);
            exit;
        }
        $novaFase = 'revelado';
        break;

    case 'proxima':
        if ($faseAtual !== 'revelado') {
            jsonResponder(['erro' => 'fase'], 409);
            exit;
        }
        $total = totalQuestoes($pdo, (int) $sessao['prova_id']);
        if ($questaoAtual + 1 > $total) {
            $novaFase = 'encerrada';
        } else {
            $novaFase = 'respondendo';
            $novaQuestao = $questaoAtual + 1;
        }
        break;

    case 'encerrar':
        // sempre permitido, de qualquer fase - e a valvula de escape que o
        // professor precisa ter sempre disponivel (design.md D6).
        $novaFase = 'encerrada';
        break;
}

// T06: guarda de toque duplo. O WHERE compara a versao esperada pelo
// cliente contra a atual de forma atomica - se outra requisicao ja mudou
// a sessao entre a leitura acima e este UPDATE, a linha nao bate e
// rowCount() vem 0. Sem essa guarda, dois toques rapidos em "Proxima"
// pulariam duas questoes na frente da turma.
$stmt = $pdo->prepare(
    'UPDATE sessoes SET fase = ?, questao_atual = ?, versao = versao + 1 WHERE codigo = ? AND versao = ?'
);
$stmt->execute([$novaFase, $novaQuestao, $codigo, $versaoEsperada]);

if ($stmt->rowCount() !== 1) {
    jsonResponder(['erro' => 'versao'], 409);
    exit;
}

jsonResponder([
    'ok' => true,
    'fase' => $novaFase,
    'questao_atual' => $novaQuestao,
    'versao' => $versaoEsperada + 1,
]);
