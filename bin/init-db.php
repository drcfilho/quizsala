<?php

declare(strict_types=1);

// Recria o banco do zero com uma prova de exemplo (3 questoes de redes) e
// uma sessao pronta pra testar (AULA01, ja em fase 'respondendo').

$dbDir = __DIR__ . '/../db';
$caminho = $dbDir . '/quizsala.sqlite';

foreach ([$caminho, $caminho . '-wal', $caminho . '-shm'] as $arquivo) {
    if (is_file($arquivo)) {
        unlink($arquivo);
    }
}

$pdo = new PDO('sqlite:' . $caminho);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec((string) file_get_contents($dbDir . '/schema.sql'));

$pdo->beginTransaction();

$pdo->prepare('INSERT INTO provas (titulo) VALUES (?)')->execute(['Redes de computadores']);
$provaId = (int) $pdo->lastInsertId();

$questoes = [
    [
        'enunciado' => 'Qual protocolo traduz nomes de dominio em enderecos IP?',
        'alternativas' => ['HTTP', 'DNS', 'FTP', 'SMTP'],
        'correta' => 1,
    ],
    [
        'enunciado' => 'Em qual camada do modelo OSI atua um switch tradicional?',
        'alternativas' => ['Fisica', 'Enlace', 'Rede', 'Transporte'],
        'correta' => 1,
    ],
    [
        'enunciado' => 'Qual desses e um endereco IP privado?',
        'alternativas' => ['8.8.8.8', '192.168.1.1', '1.1.1.1', '200.10.10.5'],
        'correta' => 1,
    ],
];

$stmtQuestao = $pdo->prepare('INSERT INTO questoes (prova_id, enunciado, ordem) VALUES (?, ?, ?)');
$stmtAlternativa = $pdo->prepare(
    'INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES (?, ?, ?, ?)'
);

foreach ($questoes as $indiceQuestao => $questao) {
    $stmtQuestao->execute([$provaId, $questao['enunciado'], $indiceQuestao + 1]);
    $questaoId = (int) $pdo->lastInsertId();

    foreach ($questao['alternativas'] as $indiceAlt => $texto) {
        $stmtAlternativa->execute([$questaoId, $texto, $indiceAlt === $questao['correta'] ? 1 : 0, $indiceAlt + 1]);
    }
}

$tokenProfessor = bin2hex(random_bytes(16));

$pdo->prepare(
    'INSERT INTO sessoes (prova_id, codigo, token_professor, modo, identificacao, questao_atual, fase, versao)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([$provaId, 'AULA01', $tokenProfessor, 'sincrono', 'anonimo', 1, 'respondendo', 1]);

$pdo->commit();

// Senha do admin de provas (T09-T14): gerada uma vez so, nao regenerada a
// cada reset do banco - senao o professor perderia a senha toda vez que
// recriasse o banco pra testar outra coisa.
$arquivoSenha = $dbDir . '/admin.senha';
if (!is_file($arquivoSenha)) {
    // 8 bytes (16 chars hex, 64 bits): mais forte que os 6 bytes originais,
    // mas ainda digitavel a mao no celular - diferente do token_professor
    // (16 bytes), que so trafega por URL/QR, nunca e digitado.
    file_put_contents($arquivoSenha, bin2hex(random_bytes(8)));
    chmod($arquivoSenha, 0600); // no-op inofensivo no Windows, restringe no Linux/Mac
}
$senhaAdmin = trim((string) file_get_contents($arquivoSenha));

echo "Banco recriado: prova '{$questoes[0]['enunciado']}...' (id {$provaId}), 3 questoes, sessao AULA01 pronta.\n";
echo "Aluno entra com o codigo: AULA01\n";
echo "Professor controla em: admin/sessao.php?codigo=AULA01&pt={$tokenProfessor}\n";
echo "Admin de provas em: admin/provas.php (senha: {$senhaAdmin})\n";
