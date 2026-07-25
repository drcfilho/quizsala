<?php

declare(strict_types=1);

// Area /admin protegida por uma senha unica (nao e sistema de contas -
// rede local fechada, um professor por vez, arquitetura.md secao 9). Sem
// isso qualquer aluno que digitasse a URL criava/editava/apagava questoes
// pelo navegador (achado por revisao automatica de seguranca: as paginas
// de admin de prova nao tinham checagem nenhuma). A senha e gerada uma vez
// por bin/init-db.php e fica fora do git (db/admin.senha).
function exigirAdmin(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $arquivoSenha = __DIR__ . '/../db/admin.senha';
    $senhaEsperada = is_file($arquivoSenha) ? trim((string) file_get_contents($arquivoSenha)) : '';

    if ($senhaEsperada !== '' && ($_SESSION['admin_ok'] ?? false) === true) {
        return;
    }

    $erro = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha_admin'])) {
        if ($senhaEsperada !== '' && hash_equals($senhaEsperada, (string) $_POST['senha_admin'])) {
            // Fixacao de sessao: troca o ID apos autenticar, senao um ID de
            // sessao capturado antes do login (ex: cookie fixado por outra
            // aba) continuaria valido depois (achado por revisao automatica).
            session_regenerate_id(true);
            $_SESSION['admin_ok'] = true;
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
        $erro = 'Senha incorreta.';
    }

    http_response_code(401);
    ?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuizSala — Entrar</title>
<link rel="stylesheet" href="/assets/vendor/bulma.css?v=<?= filemtime(__DIR__ . '/../public/assets/vendor/bulma.css') ?>">
<link rel="stylesheet" href="/assets/admin.css?v=<?= filemtime(__DIR__ . '/../public/assets/admin.css') ?>">
</head>
<body>
<section class="hero is-fullheight">
<div class="hero-body">
<div class="container quizsala-container-estreito-admin">
<div class="box">
<div class="level mb-4">
<div class="level-left">
<p class="title is-5 mb-0">Área do professor</p>
</div>
<div class="level-right">
<button type="button" class="button is-small is-light" data-alternar-tema aria-label="Alternar tema claro/escuro">
<span class="icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg></span>
</button>
</div>
</div>
<?php if ($erro !== null): ?>
<p class="help is-danger"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>
<form method="post">
<div class="field">
<label class="label" for="senha_admin">Senha</label>
<div class="control">
<input class="input" type="password" id="senha_admin" name="senha_admin" autofocus>
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
<script src="/assets/tema.js?v=<?= filemtime(__DIR__ . '/../public/assets/tema.js') ?>" defer></script>
</body>
</html>
    <?php
    exit;
}

// Token de CSRF por sessao - as paginas de admin mutam dado (criar/editar/
// excluir questao e prova) so com POST vindo do proprio formulario, nunca
// de outro site (achado junto com a falta de autenticacao acima).
function tokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf'];
}

function exigirCsrf(): void
{
    $token = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        exit('Formulário expirado, recarregue a página.');
    }
}
