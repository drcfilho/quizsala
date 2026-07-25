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
<button type="button" class="button is-small is-light quizsala-alternar-tema-sessao" data-alternar-tema aria-label="Alternar tema claro/escuro">
<span class="icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg></span>
</button>
<main class="tela-admin">
<div id="conteudo-admin">Carregando...</div>
</main>
<script src="../assets/tema.js?v=<?= filemtime(__DIR__ . '/../assets/tema.js') ?>" defer></script>
<script src="../assets/admin.js?v=<?= filemtime(__DIR__ . '/../assets/admin.js') ?>" defer></script>
</body>
</html>
