<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/util.php';
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/admin_layout.php';

exigirAdmin();

$pdo = Db::conexao();

$sessoes = $pdo->query(
    "SELECT s.codigo, s.token_professor, s.fase, p.titulo,
            (SELECT COUNT(*) FROM participantes WHERE sessao_id = s.id) AS total_participantes
     FROM sessoes s JOIN provas p ON p.id = s.prova_id
     WHERE s.fase != 'encerrada'
     ORDER BY s.id DESC"
)->fetchAll();

abrirLayoutAdmin('Sessões', 'sessoes');
?>
<div class="cartao-admin">
<h1 class="titulo-pagina">Sessões ativas</h1>

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

</div>
<?php fecharLayoutAdmin(); ?>
