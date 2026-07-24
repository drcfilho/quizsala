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
    $provaId = (int) ($_POST['prova_id'] ?? 0);

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
        duplicarProva($pdo, $provaId);
        header('Location: provas.php');
        exit;
    } elseif ($acao === 'publicar' || $acao === 'despublicar') {
        if ($acao === 'despublicar' && provaTemSessaoIniciada($pdo, $provaId)) {
            $erro = 'Essa prova já foi iniciada em alguma sessão — não dá pra despublicar no meio da aplicação. Espere encerrar.';
        } else {
            $pdo->prepare('UPDATE provas SET publicada = ? WHERE id = ?')->execute([$acao === 'publicar' ? 1 : 0, $provaId]);
            // Sessoes ja abertas dessa prova precisam saber na hora - sem isso
            // o projetor/aluno so veriam a mudanca na proxima acao real do
            // professor (revelar/proxima), porque o poll deles e guiado por
            // "versao" (design.md D3), que so muda aqui.
            $pdo->prepare('UPDATE sessoes SET versao = versao + 1 WHERE prova_id = ?')->execute([$provaId]);
            header('Location: provas.php');
            exit;
        }
    } elseif ($acao === 'excluir') {
        // Dupla confirmacao: o JS ja pede confirm() + digitar "excluir" antes
        // de submeter, mas confere de novo aqui - um POST direto (sem passar
        // pelo onsubmit) nao pode apagar a prova sem esse campo batendo.
        $confirmacao = strtolower(trim((string) ($_POST['confirmacao'] ?? '')));
        if ($confirmacao === 'excluir') {
            $pdo->prepare('DELETE FROM provas WHERE id = ?')->execute([$provaId]);
        }
        header('Location: provas.php');
        exit;
    }
}

$provas = $pdo->query(
    'SELECT p.id, p.titulo, p.publicada, COUNT(q.id) AS total_questoes
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
<p class="cabecalho-admin"><a class="link-voltar" href="index.php">&larr; Sessões</a></p>
<h1 class="titulo-pagina">Provas</h1>

<?php if ($erro !== null): ?>
<p class="erro-campo"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>

<?php if (empty($provas)): ?>
<p class="mensagem-admin">Nenhuma prova ainda.</p>
<?php else: ?>
<ul class="lista-provas">
<?php foreach ($provas as $prova): ?>
<li class="item-prova item-prova-coluna">
<a class="link-prova" href="questoes.php?prova_id=<?= (int) $prova['id'] ?>">
<span class="titulo-prova"><?= htmlspecialchars($prova['titulo']) ?></span>
<span class="contagem-prova">
<?= (int) $prova['total_questoes'] ?> questões ·
<span class="<?= $prova['publicada'] ? 'selo-publicada' : 'selo-rascunho' ?>"><?= $prova['publicada'] ? 'Publicada' : 'Rascunho' ?></span>
</span>
</a>
<div class="botoes-item-prova">
<a class="botao-pequeno" href="questoes.php?prova_id=<?= (int) $prova['id'] ?>">Editar</a>

<form method="post" class="form-inline">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(tokenCsrf()) ?>">
<input type="hidden" name="acao" value="<?= $prova['publicada'] ? 'despublicar' : 'publicar' ?>">
<input type="hidden" name="prova_id" value="<?= (int) $prova['id'] ?>">
<button type="submit" class="botao-pequeno"><?= $prova['publicada'] ? 'Despublicar' : 'Publicar' ?></button>
</form>

<form method="post" class="form-inline">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(tokenCsrf()) ?>">
<input type="hidden" name="acao" value="duplicar">
<input type="hidden" name="prova_id" value="<?= (int) $prova['id'] ?>">
<button type="submit" class="botao-pequeno">Duplicar</button>
</form>

<form method="post" class="form-inline" onsubmit="return confirmarExclusaoProva(this)">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(tokenCsrf()) ?>">
<input type="hidden" name="acao" value="excluir">
<input type="hidden" name="prova_id" value="<?= (int) $prova['id'] ?>">
<input type="hidden" name="confirmacao" value="">
<button type="submit" class="botao-pequeno botao-excluir">Excluir</button>
</form>
</div>
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

<a class="botao-secundario botao-como-link" href="importar-csv.php">Importar prova de um CSV</a>

</div>
</main>
<script>
function confirmarExclusaoProva(form) {
    if (!confirm('Excluir esta prova? Isso apaga todas as questões e sessões dela também.')) {
        return false;
    }
    var digitado = prompt('Pra confirmar, digite "excluir":');
    if (digitado === null || digitado.trim().toLowerCase() !== 'excluir') {
        return false;
    }
    form.confirmacao.value = 'excluir';
    return true;
}
</script>
</body>
</html>
