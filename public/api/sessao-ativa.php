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
//
// T25: nao e mais "a mais recente nao-encerrada" - e literalmente
// "ativa = 1", marcada a mao pelo professor em admin/index.php ("Ativar
// no projetor"). Pedido do usuario: o servidor nunca deve "chutar" uma
// sessao sozinho, nem no primeiro poll depois de o servidor subir. Sem
// nenhuma sessao ativada ainda, devolve null - tela.php mostra "Aguardando
// o inicio da sessao" ate o professor escolher.
$pdo = Db::conexao();

$codigo = $pdo->query('SELECT codigo FROM sessoes WHERE ativa = 1 LIMIT 1')->fetchColumn();

jsonResponder(['codigo' => $codigo !== false ? $codigo : null]);
