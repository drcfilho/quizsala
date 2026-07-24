<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/util.php';
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/admin_layout.php';

exigirAdmin();

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
$explicacao = '';
$duracaoSegundos = '';
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
    $explicacao = (string) ($questao['explicacao'] ?? '');
    $duracaoSegundos = $questao['duracao_segundos'] !== null ? (string) $questao['duracao_segundos'] : '';
    foreach (alternativasDaQuestao($pdo, $questaoId) as $i => $alt) {
        $alternativas[$i] = $alt['texto'];
        if ((int) $alt['correta'] === 1) {
            $corretaIndice = $i;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $enunciado = trim((string) ($_POST['enunciado'] ?? ''));
    $explicacao = trim((string) ($_POST['explicacao'] ?? ''));
    $duracaoSegundos = trim((string) ($_POST['duracao_segundos'] ?? ''));
    for ($i = 0; $i < 5; $i++) {
        $alternativas[$i] = trim((string) ($_POST['alt' . $i] ?? ''));
    }
    $corretaIndice = isset($_POST['correta']) && $_POST['correta'] !== '' ? (int) $_POST['correta'] : null;

    $erros = validarQuestao($enunciado, $alternativas, $corretaIndice);

    if (empty($erros)) {
        // Em branco ou 0 vira NULL (sem cronometro) - sem caminho de erro
        // de validacao novo, qualquer valor nao-positivo so desliga.
        $duracaoValida = (int) $duracaoSegundos > 0 ? (int) $duracaoSegundos : null;
        salvarQuestao($pdo, $provaId, $questaoId, $enunciado, $alternativas, $corretaIndice, $explicacao !== '' ? $explicacao : null, $duracaoValida);
        header('Location: questoes.php?prova_id=' . $provaId);
        exit;
    }
}

abrirLayoutAdmin('Editor de questão', 'provas');
?>
<div class="cartao-admin">
<p class="cabecalho-admin"><a class="link-voltar" href="questoes.php?prova_id=<?= $provaId ?>">&larr; <?= htmlspecialchars($prova['titulo']) ?></a></p>
<?php fluxoProva('questoes', ['criar']); ?>

<form method="post" class="form-questao">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(tokenCsrf()) ?>">
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

<?php
// Campo oculto por padrao - so abre se ja tinha explicacao salva ou se o
// professor clicar. <details> e nativo, sem JS nenhum (ladder: recurso da
// plataforma antes de escrever codigo).
?>
<details class="detalhe-explicacao" <?= $explicacao !== '' ? 'open' : '' ?>>
<summary>+ Explicação (por que a resposta certa está certa)</summary>
<textarea class="campo-admin campo-textarea" name="explicacao" rows="2" placeholder="Opcional - só o professor vê isso no editor."><?= htmlspecialchars($explicacao) ?></textarea>
</details>

<details class="detalhe-explicacao" <?= $duracaoSegundos !== '' ? 'open' : '' ?>>
<summary>+ Cronômetro (mostra contagem regressiva no projetor)</summary>
<label class="rotulo" for="duracao_segundos">Segundos</label>
<input class="campo-admin" type="number" min="0" step="5" id="duracao_segundos" name="duracao_segundos" value="<?= htmlspecialchars($duracaoSegundos) ?>" placeholder="Sem cronômetro">
</details>

<button type="submit" class="botao-acao">Salvar questão</button>
</form>

</div>
<?php fecharLayoutAdmin(); ?>
