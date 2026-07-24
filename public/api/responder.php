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

// INSERT OR IGNORE + UNIQUE(participante_id, questao_id) resolvem o
// reenvio sem transacao explicita - gravou:false so significa "ja tinha
// respondido", nao e erro.
$stmt = $pdo->prepare(
    'INSERT OR IGNORE INTO respostas (sessao_id, participante_id, questao_id, alternativa_id)
     VALUES (?, ?, ?, ?)'
);
$stmt->execute([$sessao['id'], $participante['id'], $questaoAtual['id'], $alternativaId]);
$gravou = $stmt->rowCount() > 0;

$stmt = $pdo->prepare('SELECT alternativa_id FROM respostas WHERE participante_id = ? AND questao_id = ?');
$stmt->execute([$participante['id'], $questaoAtual['id']]);
$escolhida = (int) $stmt->fetchColumn();

jsonResponder(['ok' => true, 'gravou' => $gravou, 'escolhida' => $escolhida]);
