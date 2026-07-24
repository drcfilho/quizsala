<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/util.php';

$pdo = Db::conexao();
$provaId = (int) ($_GET['prova_id'] ?? $_POST['prova_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM provas WHERE id = ?');
$stmt->execute([$provaId]);
$prova = $stmt->fetch();

if ($prova === false) {
    header('Location: provas.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    $questaoId = (int) ($_POST['questao_id'] ?? 0);

    if ($acao === 'excluir') {
        excluirQuestao($pdo, $provaId, $questaoId);
    } elseif ($acao === 'subir') {
        moverQuestao($pdo, $provaId, $questaoId, -1);
    } elseif ($acao === 'descer') {
        moverQuestao($pdo, $provaId, $questaoId, 1);
    }

    header('Location: questoes.php?prova_id=' . $provaId);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM questoes WHERE prova_id = ? ORDER BY ordem');
$stmt->execute([$provaId]);
$questoes = $stmt->fetchAll();

?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala — <?= htmlspecialchars($prova['titulo']) ?></title>
<link rel="stylesheet" href="../assets/admin.css">
</head>
<body>
<main class="tela-admin tela-admin-lista">
<div class="cartao-admin">
<p class="cabecalho-admin"><a class="link-voltar" href="provas.php">&larr; Provas</a></p>
<h1 class="titulo-pagina"><?= htmlspecialchars($prova['titulo']) ?></h1>

<?php if (empty($questoes)): ?>
<p class="mensagem-admin">Nenhuma questão ainda.</p>
<?php else: ?>
<ol class="lista-questoes">
<?php foreach ($questoes as $i => $questao): ?>
<li class="item-questao">
<a class="enunciado-questao-admin" href="questao.php?prova_id=<?= $provaId ?>&id=<?= (int) $questao['id'] ?>">
<?= (int) $questao['ordem'] ?>. <?= htmlspecialchars($questao['enunciado']) ?>
</a>
<div class="botoes-questao">
<form method="post">
<input type="hidden" name="questao_id" value="<?= (int) $questao['id'] ?>">
<input type="hidden" name="prova_id" value="<?= $provaId ?>">
<button type="submit" name="acao" value="subir" class="botao-pequeno" <?= $i === 0 ? 'disabled' : '' ?>>&uarr;</button>
<button type="submit" name="acao" value="descer" class="botao-pequeno" <?= $i === count($questoes) - 1 ? 'disabled' : '' ?>>&darr;</button>
<button type="submit" name="acao" value="excluir" class="botao-pequeno botao-excluir" onclick="return confirm('Excluir esta questão?')">Excluir</button>
</form>
</div>
</li>
<?php endforeach; ?>
</ol>
<?php endif; ?>

<a class="botao-acao botao-como-link" href="questao.php?prova_id=<?= $provaId ?>">Nova questão</a>

</div>
</main>
</body>
</html>
