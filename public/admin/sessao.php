<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala — Controle</title>
<link rel="stylesheet" href="../assets/vendor/bulma.css?v=<?= filemtime(__DIR__ . '/../assets/vendor/bulma.css') ?>">
<link rel="stylesheet" href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>">
</head>
<body>
<button type="button" class="button is-small is-ghost quizsala-alternar-tema-sessao" data-alternar-tema aria-label="Alternar tema claro/escuro">
<span class="icon">🌙</span>
</button>
<main class="tela-admin">
<div id="conteudo-admin">Carregando...</div>
</main>
<script src="../assets/tema.js?v=<?= filemtime(__DIR__ . '/../assets/tema.js') ?>" defer></script>
<script src="../assets/admin.js?v=<?= filemtime(__DIR__ . '/../assets/admin.js') ?>" defer></script>
</body>
</html>
