<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/util.php';
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/admin_layout.php';

exigirAdmin();

$pdo = Db::conexao();
$provaId = (int) ($_GET['prova_id'] ?? $_POST['prova_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM provas WHERE id = ?');
$stmt->execute([$provaId]);
$prova = $stmt->fetch();

if ($prova === false) {
    header('Location: provas.php');
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $acao = (string) ($_POST['acao'] ?? '');
    $questaoId = (int) ($_POST['questao_id'] ?? 0);

    if ($acao === 'excluir') {
        excluirQuestao($pdo, $provaId, $questaoId);
    } elseif ($acao === 'subir') {
        moverQuestao($pdo, $provaId, $questaoId, -1);
    } elseif ($acao === 'descer') {
        moverQuestao($pdo, $provaId, $questaoId, 1);
    } elseif ($acao === 'renomear') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        if ($titulo === '') {
            $erro = 'Dê um título pra prova.';
        } else {
            $pdo->prepare('UPDATE provas SET titulo = ? WHERE id = ?')->execute([$titulo, $provaId]);
            $prova['titulo'] = $titulo;
        }
    } elseif ($acao === 'testar') {
        $sessao = criarSessao($pdo, $provaId);
        header('Location: sessao.php?codigo=' . $sessao['codigo'] . '&pt=' . $sessao['token_professor']);
        exit;
    }

    if ($erro === null) {
        header('Location: questoes.php?prova_id=' . $provaId);
        exit;
    }
}

$stmt = $pdo->prepare('SELECT * FROM questoes WHERE prova_id = ? ORDER BY ordem');
$stmt->execute([$provaId]);
$questoes = $stmt->fetchAll();

// T22: passo do fluxo guiado - qual e o proximo passo natural pra esta
// prova, com base no que ja existe (nao no que o professor "deveria" ter
// feito, ele pode ir e voltar livremente).
$passoFluxo = empty($questoes) ? 'questoes' : ((int) $prova['publicada'] === 0 ? 'publicar' : 'sessao');
$concluidosFluxo = ['criar'];
if (!empty($questoes)) {
    $concluidosFluxo[] = 'questoes';
}
if ((int) $prova['publicada'] === 1) {
    $concluidosFluxo[] = 'publicar';
}

abrirLayoutAdmin('Questões — ' . $prova['titulo'], 'provas');
?>
<div class="cartao-admin">
<p class="cabecalho-admin"><a class="link-voltar" href="provas.php">&larr; Provas</a></p>
<?php fluxoProva($passoFluxo, $concluidosFluxo); ?>

<?php if ($erro !== null): ?>
<p class="erro-campo"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>

<form method="post" class="form-titulo-prova">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(tokenCsrf()) ?>">
<input type="hidden" name="acao" value="renomear">
<input type="hidden" name="prova_id" value="<?= $provaId ?>">
<input class="campo-admin campo-titulo-prova" type="text" name="titulo" value="<?= htmlspecialchars($prova['titulo']) ?>">
<button type="submit" class="botao-pequeno">Salvar título</button>
</form>

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
<input type="hidden" name="csrf" value="<?= htmlspecialchars(tokenCsrf()) ?>">
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

<?php if (!empty($questoes)): ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(tokenCsrf()) ?>">
<input type="hidden" name="acao" value="testar">
<button type="submit" class="botao-secundario">Testar prova (abre uma sessão)</button>
</form>
<?php endif; ?>

<?php if (!empty($questoes) && (int) $prova['publicada'] === 0): ?>
<form method="post" action="provas.php">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(tokenCsrf()) ?>">
<input type="hidden" name="acao" value="publicar">
<input type="hidden" name="prova_id" value="<?= $provaId ?>">
<button type="submit" class="botao-acao">Publicar prova (passo 3)</button>
</form>
<?php elseif ((int) $prova['publicada'] === 1): ?>
<a class="botao-acao botao-como-link" href="nova-sessao.php">Abrir sessão (passo 4)</a>
<?php endif; ?>

<a class="botao-secundario botao-como-link" href="provas.php">Salvar prova e voltar</a>

</div>
<?php fecharLayoutAdmin(); ?>
