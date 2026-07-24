<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/util.php';

$corpo = json_decode((string) file_get_contents('php://input'), true) ?? [];
$token = (string) ($corpo['token'] ?? '');
$alternativaId = (int) ($corpo['alternativa_id'] ?? 0);

$pdo = Db::conexao();
$participante = participantePorToken($pdo, $token);

if ($participante === null) {
    jsonResponder(['erro' => 'token'], 404);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM sessoes WHERE id = ?');
$stmt->execute([$participante['sessao_id']]);
$sessao = $stmt->fetch();

// so aceita gravacao em fase = respondendo (design.md secao 5).
if ($sessao['fase'] !== 'respondendo') {
    jsonResponder(['erro' => 'fechada'], 409);
    exit;
}

$questaoAtual = questaoPorOrdem($pdo, (int) $sessao['prova_id'], (int) $sessao['questao_atual']);

// impede responder uma questao que nao e a que esta no ar (cliente adulterado).
$stmt = $pdo->prepare('SELECT id FROM alternativas WHERE id = ? AND questao_id = ?');
$stmt->execute([$alternativaId, $questaoAtual['id']]);

if ($stmt->fetch() === false) {
    jsonResponder(['erro' => 'alternativa'], 422);
    exit;
}

// O aluno pode trocar de ideia na mesma questao enquanto ela seguir no ar
// (fase = respondendo, ja checado acima) - UPSERT em vez de INSERT OR
// IGNORE. UNIQUE(participante_id, questao_id) continua garantindo uma
// linha so por questao; o ON CONFLICT so troca a alternativa e recarrega
// o timestamp. Assim que o professor revela, a checagem de fase no topo
// deste arquivo passa a rejeitar com 409 - e isso que trava a resposta,
// nao mais a primeira gravacao.
$stmt = $pdo->prepare(
    "INSERT INTO respostas (sessao_id, participante_id, questao_id, alternativa_id)
     VALUES (?, ?, ?, ?)
     ON CONFLICT (participante_id, questao_id) DO UPDATE SET
        alternativa_id = excluded.alternativa_id,
        criada_em = strftime('%s','now')"
);
$stmt->execute([$sessao['id'], $participante['id'], $questaoAtual['id'], $alternativaId]);

jsonResponder(['ok' => true, 'gravou' => true, 'escolhida' => $alternativaId]);
