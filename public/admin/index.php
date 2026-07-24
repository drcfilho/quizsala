<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/util.php';
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/admin_layout.php';

exigirAdmin();

$pdo = Db::conexao();
$erro = null;

// T18/T23: "Limpar" saiu do controle ao vivo (admin/sessao.php, mobile) e
// veio pra ca, a pedido do usuario - e arrumacao feita depois, na mesa, nao
// no meio da aula. sessao.php continua com "Encerrar" (escape hatch,
// precisa estar disponivel na hora); "Limpar" so aparece aqui, depois que
// a sessao ja esta encerrada.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $acao = (string) ($_POST['acao'] ?? '');
    $sessaoId = (int) ($_POST['sessao_id'] ?? 0);

    if ($acao === 'limpar') {
        $stmt = $pdo->prepare('SELECT fase FROM sessoes WHERE id = ?');
        $stmt->execute([$sessaoId]);
        $fase = $stmt->fetchColumn();

        if ($fase === 'encerrada') {
            limparSessao($pdo, $sessaoId);
        } else {
            $erro = 'Essa sessão não está encerrada — não dá pra limpar.';
        }
    }
}

$sessoes = $pdo->query(
    "SELECT s.codigo, s.token_professor, s.fase, p.titulo,
            (SELECT COUNT(*) FROM participantes WHERE sessao_id = s.id) AS total_participantes
     FROM sessoes s JOIN provas p ON p.id = s.prova_id
     WHERE s.fase != 'encerrada'
     ORDER BY s.id DESC"
)->fetchAll();

$sessoesEncerradas = $pdo->query(
    "SELECT s.id, s.codigo, p.titulo,
            (SELECT COUNT(*) FROM participantes WHERE sessao_id = s.id) AS total_participantes
     FROM sessoes s JOIN provas p ON p.id = s.prova_id
     WHERE s.fase = 'encerrada'
     ORDER BY s.id DESC"
)->fetchAll();

abrirLayoutAdmin('Sessões', 'sessoes');
?>
<div class="cartao-admin">
<h1 class="titulo-pagina">Sessões ativas</h1>

<?php if ($erro !== null): ?>
<p class="erro-campo"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>

<?php if (empty($sessoes)): ?>
<p class="mensagem-admin">Nenhuma sessão ativa.</p>
<?php else: ?>
<ul class="lista-provas">
<?php foreach ($sessoes as $sessao): ?>
<li class="item-prova">
<a class="link-prova" href="sessao.php?codigo=<?= urlencode($sessao['codigo']) ?>&pt=<?= urlencode($sessao['token_professor']) ?>">
<span class="titulo-prova"><?= htmlspecialchars($sessao['titulo']) ?> — <?= htmlspecialchars($sessao['codigo']) ?></span>
<span class="contagem-prova"><?= (int) $sessao['total_participantes'] ?> participantes · <?= htmlspecialchars($sessao['fase']) ?></span>
</a>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<a class="botao-acao botao-como-link" href="nova-sessao.php">Nova sessão</a>

<?php if (!empty($sessoesEncerradas)): ?>
<h2 class="subtitulo-pagina">Sessões encerradas</h2>
<ul class="lista-provas">
<?php foreach ($sessoesEncerradas as $sessao): ?>
<li class="item-prova item-prova-coluna">
<span class="link-prova">
<span class="titulo-prova"><?= htmlspecialchars($sessao['titulo']) ?> — <?= htmlspecialchars($sessao['codigo']) ?></span>
<span class="contagem-prova"><?= (int) $sessao['total_participantes'] ?> participantes · encerrada</span>
</span>
<div class="botoes-item-prova">
<form method="post" class="form-inline" onsubmit="return confirmarLimparSessao(this)">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(tokenCsrf()) ?>">
<input type="hidden" name="acao" value="limpar">
<input type="hidden" name="sessao_id" value="<?= (int) $sessao['id'] ?>">
<button type="submit" class="botao-pequeno botao-excluir">Limpar</button>
</form>
</div>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

</div>
<script>
function confirmarLimparSessao(form) {
    if (!confirm('Apagar esta sessão? As respostas dos alunos somem para sempre - a prova continua existindo.')) {
        return false;
    }
    return prompt('Pra confirmar, digite "limpar":') === 'limpar';
}
</script>
<?php fecharLayoutAdmin(); ?>
