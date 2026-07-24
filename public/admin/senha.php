<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/admin_layout.php';

exigirAdmin();

// Pedido do usuario: trocar a senha unica do admin (arquitetura.md secao 9
// continua valendo - um professor so, sem conceito de conta/usuario) em vez
// de depender so da senha aleatoria gerada por bin/init-db.php em
// db/admin.senha. Minimo de 6 caracteres porque exigirAdmin() nao tem
// throttling de tentativas - a senha aleatoria original (16 hex) resistia
// a isso por tamanho; uma senha curta escolhida a mao precisa de um piso.
const SENHA_MINIMA = 6;

$arquivoSenha = __DIR__ . '/../../db/admin.senha';
$senhaAtualEsperada = is_file($arquivoSenha) ? trim((string) file_get_contents($arquivoSenha)) : '';

$erro = null;
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();

    $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
    $novaSenha = (string) ($_POST['nova_senha'] ?? '');
    $confirmacao = (string) ($_POST['confirmar_senha'] ?? '');

    if (!hash_equals($senhaAtualEsperada, $senhaAtual)) {
        $erro = 'Senha atual incorreta.';
    } elseif (mb_strlen($novaSenha) < SENHA_MINIMA) {
        $erro = "A nova senha precisa ter pelo menos " . SENHA_MINIMA . " caracteres.";
    } elseif ($novaSenha !== $confirmacao) {
        $erro = 'As duas senhas não são iguais.';
    } else {
        file_put_contents($arquivoSenha, $novaSenha);
        chmod($arquivoSenha, 0600); // no-op inofensivo no Windows, restringe no Linux/Mac
        $sucesso = true;
    }
}

$csrf = tokenCsrf();
abrirLayoutAdmin('Trocar senha', 'senha');
?>
<div class="cartao-admin">
<h1 class="titulo-pagina">Trocar senha do admin</h1>

<?php if ($sucesso): ?>
<p class="mensagem-sucesso">Senha alterada — use a nova senha no próximo login.</p>
<?php endif; ?>
<?php if ($erro !== null): ?>
<p class="erro-campo"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>

<form method="post" class="form-prova">
<label class="rotulo" for="senha_atual">Senha atual</label>
<input class="campo-admin" type="password" id="senha_atual" name="senha_atual" autocomplete="current-password" required>

<label class="rotulo" for="nova_senha">Nova senha</label>
<input class="campo-admin" type="password" id="nova_senha" name="nova_senha" autocomplete="new-password" minlength="<?= SENHA_MINIMA ?>" required>

<label class="rotulo" for="confirmar_senha">Repetir a nova senha</label>
<input class="campo-admin" type="password" id="confirmar_senha" name="confirmar_senha" autocomplete="new-password" minlength="<?= SENHA_MINIMA ?>" required>

<input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
<button type="submit" class="botao-acao">Salvar nova senha</button>
</form>
</div>
<?php fecharLayoutAdmin(); ?>
