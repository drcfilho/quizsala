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
<link rel="stylesheet" href="assets/estilo.css?v=<?= filemtime(__DIR__ . '/assets/estilo.css') ?>">
</head>
<body>
<main class="tela-entrada">
<div class="cartao">
<h1 class="titulo">QuizSala</h1>
<?php if ($mensagemErro !== ''): ?>
<p class="aviso"><?= htmlspecialchars($mensagemErro) ?></p>
<?php endif; ?>
<form method="post" action="api/entrar.php">
<label class="rotulo" for="codigo">Código da sala</label>
<input
  class="campo campo-codigo"
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
<label class="rotulo" for="nome">Nome (se pedido pelo professor)</label>
<input
  class="campo"
  type="text"
  id="nome"
  name="nome"
  autocapitalize="words"
  autocomplete="off"
  enterkeyhint="go"
  maxlength="60"
>
<button type="submit" class="botao-principal">Entrar</button>
</form>
</div>
</main>
</body>
</html>
