<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/util.php';
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/admin_layout.php';

exigirAdmin();

$pdo = Db::conexao();
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();

    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $arquivo = $_FILES['csv'] ?? null;

    if ($titulo === '') {
        $erros[] = 'Dê um título pra prova.';
    }

    if ($arquivo === null || $arquivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'])) {
        $erros[] = 'Escolha um arquivo CSV.';
    }

    if (empty($erros)) {
        $resultado = importarProvaCsv($pdo, $titulo, $arquivo['tmp_name']);
        if ($resultado['ok']) {
            header('Location: questoes.php?prova_id=' . $resultado['prova_id']);
            exit;
        }
        $erros = $resultado['erros'];
    }
}

abrirLayoutAdmin('Importar CSV', 'importar-csv');
?>
<div class="cartao-admin">
<h1 class="titulo-pagina">Importar prova de um CSV</h1>

<?php if (!empty($erros)): ?>
<ul class="lista-erros-csv">
<?php foreach ($erros as $e): ?>
<li class="erro-campo"><?= htmlspecialchars($e) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<p class="mensagem-ajuda-csv">
Colunas do CSV, nessa ordem: <code>enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, alternativa_e, correta, explicacao</code>.
<code>correta</code> é a letra (A-E) da alternativa certa. <code>alternativa_e</code> e <code>explicacao</code> são opcionais.
A prova nasce como rascunho — publique depois de conferir.
<a href="../exemplos/exemplo-prova.csv" download>Baixar exemplo</a>.
</p>

<form method="post" enctype="multipart/form-data" class="form-questao">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(tokenCsrf()) ?>">

<label class="rotulo" for="titulo">Título da prova</label>
<input class="campo-admin" type="text" id="titulo" name="titulo" placeholder="Título da prova" value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>" required>

<label class="rotulo" for="csv">Arquivo CSV</label>
<input class="campo-admin" type="file" id="csv" name="csv" accept=".csv,text/csv" required>

<button type="submit" class="botao-acao">Importar</button>
</form>

</div>
<?php fecharLayoutAdmin(); ?>
