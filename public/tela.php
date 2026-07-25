<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala</title>
<link rel="stylesheet" href="assets/vendor/bulma.css?v=<?= filemtime(__DIR__ . '/assets/vendor/bulma.css') ?>">
<link rel="stylesheet" href="assets/tela.css?v=<?= filemtime(__DIR__ . '/assets/tela.css') ?>">
</head>
<body>
<button type="button" class="button is-small is-ghost quizsala-alternar-tema-projetor" data-alternar-tema aria-label="Alternar tema claro/escuro">
<span class="icon">🌙</span>
</button>
<main class="painel" id="painel" data-fase="carregando">
<div id="conteudo-painel"></div>
</main>
<script src="assets/tema.js?v=<?= filemtime(__DIR__ . '/assets/tema.js') ?>" defer></script>
<script src="assets/tela.js?v=<?= filemtime(__DIR__ . '/assets/tela.js') ?>" defer></script>
</body>
</html>
