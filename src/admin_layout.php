<?php

declare(strict_types=1);

// T22: shell compartilhado das paginas de CONTEUDO do admin (provas,
// questoes, importar CSV, nova sessao, trocar senha) - pensadas pra
// desktop, com menu fixo. admin/sessao.php (controle ao vivo, professor
// andando pela sala) fica de fora de proposito - continua isolada,
// mobile-first (arquitetura.md secao 7), sem usar este arquivo.
const ADMIN_NAV = [
    'sessoes' => ['label' => 'Sessões', 'href' => 'index.php'],
    'provas' => ['label' => 'Provas', 'href' => 'provas.php'],
    'nova-sessao' => ['label' => 'Nova sessão', 'href' => 'nova-sessao.php'],
    'importar-csv' => ['label' => 'Importar CSV', 'href' => 'importar-csv.php'],
    'senha' => ['label' => 'Trocar senha', 'href' => 'senha.php'],
];

function abrirLayoutAdmin(string $titulo, string $paginaAtiva): void
{
    ?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala — <?= htmlspecialchars($titulo) ?></title>
<link rel="stylesheet" href="../assets/vendor/bulma.css?v=<?= filemtime(__DIR__ . '/../public/assets/vendor/bulma.css') ?>">
<link rel="stylesheet" href="../assets/admin.css?v=<?= filemtime(__DIR__ . '/../public/assets/admin.css') ?>">
</head>
<body>
<div class="shell-admin">
<nav class="menu-admin">
<div class="quizsala-topo-menu-admin">
<p class="marca-admin">QuizSala</p>
<button type="button" class="button is-small is-light" data-alternar-tema aria-label="Alternar tema claro/escuro">
<span class="icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg></span>
</button>
</div>
<input type="checkbox" id="menu-admin-toggle" class="menu-admin-toggle-input">
<label for="menu-admin-toggle" class="menu-admin-toggle">Menu</label>
<ul class="lista-menu-admin">
<?php foreach (ADMIN_NAV as $chave => $item): ?>
<li><a class="link-menu-admin<?= $chave === $paginaAtiva ? ' ativo' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a></li>
<?php endforeach; ?>
</ul>
</nav>
<main class="conteudo-admin">
    <?php
}

function fecharLayoutAdmin(): void
{
    ?>
</main>
</div>
<script src="../assets/tema.js?v=<?= filemtime(__DIR__ . '/../public/assets/tema.js') ?>" defer></script>
</body>
</html>
    <?php
}

// T22 passo 3: indicador do fluxo de criacao de uma prova. So aparece nas
// paginas que fazem parte desse fluxo (questoes.php, questao.php) - nao
// existe um "passo atual" unico em provas.php, que e o hub de todas as
// provas, nao de uma so.
const PASSOS_FLUXO_PROVA = [
    'criar' => '1. Criar prova',
    'questoes' => '2. Questões',
    'publicar' => '3. Publicar',
    'sessao' => '4. Sessão',
];

/** @param string[] $concluidos */
function fluxoProva(string $passoAtual, array $concluidos = []): void
{
    ?>
<ol class="fluxo-prova">
<?php foreach (PASSOS_FLUXO_PROVA as $chave => $rotulo): ?>
<li class="passo-fluxo<?= $chave === $passoAtual ? ' atual' : '' ?><?= in_array($chave, $concluidos, true) ? ' concluido' : '' ?>"><?= htmlspecialchars($rotulo) ?></li>
<?php endforeach; ?>
</ol>
    <?php
}
