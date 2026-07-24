<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/util.php';

$pdo = Db::conexao();
$provaId = (int) ($_GET['prova_id'] ?? $_POST['prova_id'] ?? 0);
$questaoId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM provas WHERE id = ?');
$stmt->execute([$provaId]);
$prova = $stmt->fetch();

if ($prova === false) {
    header('Location: provas.php');
    exit;
}

$enunciado = '';
$alternativas = ['', '', '', '', ''];
$corretaIndice = null;
$erros = [];

if ($questaoId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM questoes WHERE id = ? AND prova_id = ?');
    $stmt->execute([$questaoId, $provaId]);
    $questao = $stmt->fetch();

    if ($questao === false) {
        header('Location: questoes.php?prova_id=' . $provaId);
        exit;
    }

    $enunciado = $questao['enunciado'];
    foreach (alternativasDaQuestao($pdo, $questaoId) as $i => $alt) {
        $alternativas[$i] = $alt['texto'];
        if ((int) $alt['correta'] === 1) {
            $corretaIndice = $i;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enunciado = trim((string) ($_POST['enunciado'] ?? ''));
    for ($i = 0; $i < 5; $i++) {
        $alternativas[$i] = trim((string) ($_POST['alt' . $i] ?? ''));
    }
    $corretaIndice = isset($_POST['correta']) && $_POST['correta'] !== '' ? (int) $_POST['correta'] : null;

    if ($enunciado === '') {
        $erros['enunciado'] = 'Escreva o enunciado.';
    }

    $preenchidas = array_filter($alternativas, fn (string $t) => $t !== '');
    if (count($preenchidas) < 2) {
        $erros['alternativas'] = 'Preencha pelo menos 2 alternativas.';
    }

    if ($corretaIndice === null || $alternativas[$corretaIndice] === '') {
        $erros['correta'] = 'Marque qual alternativa é a certa.';
    }

    if (empty($erros)) {
        $pdo->beginTransaction();

        if ($questaoId > 0) {
            $pdo->prepare('UPDATE questoes SET enunciado = ? WHERE id = ?')->execute([$enunciado, $questaoId]);
            $pdo->prepare('DELETE FROM alternativas WHERE questao_id = ?')->execute([$questaoId]);
        } else {
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) FROM questoes WHERE prova_id = ?');
            $stmt->execute([$provaId]);
            $ordem = (int) $stmt->fetchColumn() + 1;

            $pdo->prepare('INSERT INTO questoes (prova_id, enunciado, ordem) VALUES (?, ?, ?)')
                ->execute([$provaId, $enunciado, $ordem]);
            $questaoId = (int) $pdo->lastInsertId();
        }

        $inserirAlternativa = $pdo->prepare(
            'INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES (?, ?, ?, ?)'
        );
        $ordemAlt = 1;
        foreach ($alternativas as $i => $texto) {
            if ($texto === '') {
                continue;
            }
            $inserirAlternativa->execute([$questaoId, $texto, $i === $corretaIndice ? 1 : 0, $ordemAlt]);
            $ordemAlt++;
        }

        $pdo->commit();
        header('Location: questoes.php?prova_id=' . $provaId);
        exit;
    }
}

?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala — Editor de questão</title>
<link rel="stylesheet" href="../assets/admin.css">
</head>
<body>
<main class="tela-admin tela-admin-lista">
<div class="cartao-admin">
<p class="cabecalho-admin"><a class="link-voltar" href="questoes.php?prova_id=<?= $provaId ?>">&larr; <?= htmlspecialchars($prova['titulo']) ?></a></p>

<form method="post" class="form-questao">
<input type="hidden" name="prova_id" value="<?= $provaId ?>">
<input type="hidden" name="id" value="<?= $questaoId ?>">

<label class="rotulo" for="enunciado">Enunciado</label>
<textarea class="campo-admin campo-textarea" id="enunciado" name="enunciado" rows="3"><?= htmlspecialchars($enunciado) ?></textarea>
<?php if (isset($erros['enunciado'])): ?>
<p class="erro-campo"><?= htmlspecialchars($erros['enunciado']) ?></p>
<?php endif; ?>

<label class="rotulo">Alternativas (marque a certa)</label>
<?php if (isset($erros['alternativas'])): ?>
<p class="erro-campo"><?= htmlspecialchars($erros['alternativas']) ?></p>
<?php endif; ?>
<?php if (isset($erros['correta'])): ?>
<p class="erro-campo"><?= htmlspecialchars($erros['correta']) ?></p>
<?php endif; ?>

<?php for ($i = 0; $i < 5; $i++): ?>
<div class="linha-alternativa-admin">
<input type="radio" name="correta" value="<?= $i ?>" id="correta<?= $i ?>" <?= $corretaIndice === $i ? 'checked' : '' ?>>
<label for="correta<?= $i ?>" class="rotulo-radio"><?= chr(65 + $i) ?></label>
<input class="campo-admin" type="text" name="alt<?= $i ?>" value="<?= htmlspecialchars($alternativas[$i]) ?>" placeholder="Alternativa <?= chr(65 + $i) ?>">
</div>
<?php endfor; ?>

<button type="submit" class="botao-acao">Salvar questão</button>
</form>

</div>
</main>
</body>
</html>
