<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/util.php';

// Achado pelo usuario testando de verdade: iniciar.ps1/iniciar.sh nao tem
// como saber de antemao qual sera o codigo da proxima sessao (gerado na
// hora, aleatorio - T08), e abrir sempre um codigo fixo (ex.: AULA01)
// quebra assim que essa sessao semente for limpa (T18) ou nunca tiver
// existido. tela.php sem "?codigo=" usa este endpoint pra descobrir
// sozinho qual sessao mostrar, sem exigir editar a URL na mao. So
// devolve o codigo (publico por natureza, qualquer aluno ja tem) - nunca
// o token_professor.
$pdo = Db::conexao();

$codigo = $pdo->query(
    "SELECT codigo FROM sessoes WHERE fase != 'encerrada' ORDER BY id DESC LIMIT 1"
)->fetchColumn();

jsonResponder(['codigo' => $codigo !== false ? $codigo : null]);
