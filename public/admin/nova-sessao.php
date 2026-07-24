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

    $provaId = (int) ($_POST['prova_id'] ?? 0);
    $identificacao = (string) ($_POST['identificacao'] ?? 'anonimo');

    $stmt = $pdo->prepare('SELECT id FROM provas WHERE id = ? AND publicada = 1');
    $stmt->execute([$provaId]);

    if ($stmt->fetchColumn() === false) {
        $erro = 'Escolha uma prova publicada.';
    } elseif (!in_array($identificacao, ['anonimo', 'nome'], true)) {
        $erro = 'Identificação inválida.';
    } else {
        $sessao = criarSessao($pdo, $provaId, $identificacao);
        header('Location: sessao.php?codigo=' . $sessao['codigo'] . '&pt=' . $sessao['token_professor']);
        exit;
    }
}

$provas = $pdo->query('SELECT id, titulo FROM provas WHERE publicada = 1 ORDER BY titulo')->fetchAll();

?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala — Nova sessão</title>
<link rel="stylesheet" href="../assets/admin.css">
</head>
<body>
<main class="tela-admin tela-admin-lista">
<div class="cartao-admin">
<p class="cabecalho-admin"><a class="link-voltar" href="index.php">&larr; Sessões</a></p>
<h1 class="titulo-pagina">Nova sessão</h1>

<?php if ($erro !== null): ?>
<p class="erro-campo"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>

<?php if (empty($provas)): ?>
<p class="mensagem-admin">Nenhuma prova publicada ainda. Crie uma prova e clique em "Publicar".</p>
<a class="botao-acao botao-como-link" href="provas.php">Ir para provas</a>
<?php else: ?>
<form method="post" class="form-questao">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(tokenCsrf()) ?>">

<label class="rotulo" for="prova_id">Prova</label>
<select class="campo-admin" id="prova_id" name="prova_id">
<?php foreach ($provas as $prova): ?>
<option value="<?= (int) $prova['id'] ?>"><?= htmlspecialchars($prova['titulo']) ?></option>
<?php endforeach; ?>
</select>

<label class="rotulo">Identificação do aluno</label>
<div class="linha-alternativa-admin">
<input type="radio" name="identificacao" value="anonimo" id="id-anonimo" checked>
<label for="id-anonimo">Anônimo (apelido "Aluno 01")</label>
</div>
<div class="linha-alternativa-admin">
<input type="radio" name="identificacao" value="nome" id="id-nome">
<label for="id-nome">Com nome (aluno digita o próprio nome)</label>
</div>

<button type="submit" class="botao-acao">Abrir sessão</button>
</form>
<?php endif; ?>

</div>
</main>
</body>
</html>
