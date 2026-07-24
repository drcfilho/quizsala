<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/util.php';
require __DIR__ . '/../../src/auth.php';

exigirAdmin();

$pdo = Db::conexao();
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'criar') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        if ($titulo === '') {
            $erro = 'Dê um título pra prova.';
        } else {
            $pdo->prepare('INSERT INTO provas (titulo) VALUES (?)')->execute([$titulo]);
            header('Location: provas.php');
            exit;
        }
    } elseif ($acao === 'duplicar') {
        duplicarProva($pdo, (int) ($_POST['prova_id'] ?? 0));
        header('Location: provas.php');
        exit;
    }
}

$provas = $pdo->query(
    'SELECT p.id, p.titulo, COUNT(q.id) AS total_questoes
     FROM provas p LEFT JOIN questoes q ON q.prova_id = p.id
     GROUP BY p.id ORDER BY p.id DESC'
)->fetchAll();

?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala — Provas</title>
<link rel="stylesheet" href="../assets/admin.css">
</head>
<body>
<main class="tela-admin tela-admin-lista">
<div class="cartao-admin">
<p class="cabecalho-admin">Provas</p>

<?php if ($erro !== null): ?>
<p class="erro-campo"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>

<?php if (empty($provas)): ?>
<p class="mensagem-admin">Nenhuma prova ainda.</p>
<?php else: ?>
<ul class="lista-provas">
<?php foreach ($provas as $prova): ?>
<li class="item-prova">
<a class="link-prova" href="questoes.php?prova_id=<?= (int) $prova['id'] ?>">
<span class="titulo-prova"><?= htmlspecialchars($prova['titulo']) ?></span>
<span class="contagem-prova"><?= (int) $prova['total_questoes'] ?> questões</span>
</a>
<form method="post" class="form-inline">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(tokenCsrf()) ?>">
<input type="hidden" name="acao" value="duplicar">
<input type="hidden" name="prova_id" value="<?= (int) $prova['id'] ?>">
<button type="submit" class="botao-secundario botao-pequeno">Duplicar</button>
</form>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" class="form-prova">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(tokenCsrf()) ?>">
<input type="hidden" name="acao" value="criar">
<label class="rotulo" for="titulo">Nova prova</label>
<input class="campo-admin" type="text" id="titulo" name="titulo" placeholder="Título da prova" required>
<button type="submit" class="botao-acao">Criar prova</button>
</form>

</div>
</main>
</body>
</html>
