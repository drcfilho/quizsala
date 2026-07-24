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
<link rel="stylesheet" href="/assets/admin.css">
</head>
<body>
<main class="tela-admin">
<div class="cartao-admin">
<p class="cabecalho-admin">Área do professor</p>
<?php if ($erro !== null): ?>
<p class="erro-campo"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>
<form method="post">
<label class="rotulo" for="senha_admin">Senha</label>
<input class="campo-admin" type="password" id="senha_admin" name="senha_admin" autofocus>
<button type="submit" class="botao-acao">Entrar</button>
</form>
</div>
</main>
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
