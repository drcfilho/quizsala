<?php

declare(strict_types=1);

$codigo = htmlspecialchars((string) ($_GET['s'] ?? ''));
$erro = (string) ($_GET['erro'] ?? '');

$mensagemErro = match ($erro) {
    'codigo' => 'Código não encontrado ou sessão encerrada. Confira com o professor.',
    'nome' => 'Essa sessão pede nome pra entrar.',
    default => '',
};
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala</title>
<link rel="stylesheet" href="assets/vendor/bulma.css?v=<?= filemtime(__DIR__ . '/assets/vendor/bulma.css') ?>">
<link rel="stylesheet" href="assets/estilo.css?v=<?= filemtime(__DIR__ . '/assets/estilo.css') ?>">
</head>
<body>
<section class="hero is-fullheight">
<div class="hero-body">
<div class="container quizsala-container-estreito">
<div class="box">
<div class="level mb-4">
<div class="level-left">
<h1 class="title is-4 mb-0">QuizSala</h1>
</div>
<div class="level-right">
<button type="button" class="button is-small is-light" data-alternar-tema aria-label="Alternar tema claro/escuro">
<span class="icon">🌙</span>
</button>
</div>
</div>
<?php if ($mensagemErro !== ''): ?>
<p class="help is-danger"><?= htmlspecialchars($mensagemErro) ?></p>
<?php endif; ?>
<form method="post" action="api/entrar.php">
<div class="field">
<label class="label" for="codigo">Código da sala</label>
<div class="control">
<input
  class="input quizsala-campo-codigo"
  type="text"
  id="codigo"
  name="codigo"
  value="<?= $codigo ?>"
  required
  autocapitalize="characters"
  autocomplete="off"
  inputmode="text"
  enterkeyhint="next"
  maxlength="6"
>
</div>
</div>
<div class="field">
<label class="label" for="nome">Nome (se pedido pelo professor)</label>
<div class="control">
<input
  class="input"
  type="text"
  id="nome"
  name="nome"
  autocapitalize="words"
  autocomplete="off"
  enterkeyhint="go"
  maxlength="60"
>
</div>
</div>
<div class="control">
<button type="submit" class="button is-primary is-fullwidth">Entrar</button>
</div>
</form>
</div>
</div>
</div>
</section>
<script src="assets/tema.js?v=<?= filemtime(__DIR__ . '/assets/tema.js') ?>" defer></script>
</body>
</html>
