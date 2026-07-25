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
<main class="tela-prova">
<div class="quizsala-topo-prova">
<header class="cabecalho-prova">
<span class="marca-registro" aria-hidden="true">&#9642;</span>
<span class="nome-aluno" id="nome-aluno"></span>
<span class="contador-questao" id="contador-questao"></span>
<span class="marca-registro" aria-hidden="true">&#9642;</span>
</header>
<button type="button" class="button is-small is-ghost" data-alternar-tema aria-label="Alternar tema claro/escuro">
<span class="icon">🌙</span>
</button>
</div>
<div id="conteudo-prova" role="group" aria-live="polite"></div>
</main>
<script src="assets/tema.js?v=<?= filemtime(__DIR__ . '/assets/tema.js') ?>" defer></script>
<script src="assets/aluno.js?v=<?= filemtime(__DIR__ . '/assets/aluno.js') ?>" defer></script>
</body>
</html>
