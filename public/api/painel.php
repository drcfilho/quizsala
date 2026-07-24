<?php

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/util.php';

// Sem gate de versao (design.md D3): e uma tela so, e os contadores mudam
// por acao do aluno, nao do professor - o custo de recalcular a cada poll
// e irrelevante numa unica tela.
$codigo = (string) ($_GET['codigo'] ?? '');
// admin/sessao.php manda ?admin=1 e continua vendo o estado real mesmo com
// a prova despublicada - o professor precisa disso pra decidir/republicar.
// tela.php (projetor) nao manda, entao cai no override abaixo.
$admin = isset($_GET['admin']);

$pdo = Db::conexao();
$sessao = sessaoPorCodigo($pdo, $codigo);

if ($sessao === null) {
    jsonResponder(['erro' => 'codigo'], 404);
    exit;
}

// Prova despublicada trava sessao ja em andamento - o professor precisa de
// um jeito de tirar a prova do ar na hora, nao so impedir abrir sessao nova
// (achado de teste, T09d). provas.php incrementa "versao" ao publicar/
// despublicar, entao o poll do projetor pega a mudanca sem esperar a
// proxima acao real do professor.
$fase = $sessao['fase'];
if (!$admin && $fase !== 'encerrada') {
    $stmt = $pdo->prepare('SELECT publicada FROM provas WHERE id = ?');
    $stmt->execute([(int) $sessao['prova_id']]);
    if (!$stmt->fetchColumn()) {
        $fase = 'aguardando';
    }
}

// "versao" viaja aqui tambem (nao so em estado.php) porque T07 reusa este
// endpoint no admin do professor, e o admin precisa saber a versao pra
// mandar em comando.php (guarda de toque duplo, T06) - nao duplica consulta.
$payload = ['fase' => $fase, 'versao' => (int) $sessao['versao']];

// T16: a fase "aguardando" tambem mostra quantos ja entraram (tela de
// espera com QR) - entrar.php nao bloqueia por fase, entao ja tem gente
// em "participantes" antes do professor iniciar.
if (in_array($fase, ['aguardando', 'respondendo', 'revelado'], true)) {
    $payload['online'] = contarOnline($pdo, (int) $sessao['id']);
}

if (in_array($fase, ['respondendo', 'revelado'], true)) {
    $questaoAtual = questaoPorOrdem($pdo, (int) $sessao['prova_id'], (int) $sessao['questao_atual']);

    if ($questaoAtual !== null) {
        $payload['questao'] = [
            'ordem' => (int) $sessao['questao_atual'],
            'total' => totalQuestoes($pdo, (int) $sessao['prova_id']),
            'enunciado' => $questaoAtual['enunciado'],
        ];
        $payload['responderam'] = contarResponderam($pdo, (int) $sessao['id'], (int) $questaoAtual['id']);

        // Cronometro (opcional por questao): so aparece no payload se a
        // questao tem duracao configurada - mesmo idioma do D7 abaixo (campo
        // nem existe quando nao se aplica). "restante" calculado no servidor
        // (fase_iniciada_em), nao no cliente, pra projetor e celular do
        // professor concordarem mesmo que um dos dois recarregue no meio.
        if ($fase === 'respondendo') {
            $duracao = (int) ($questaoAtual['duracao_segundos'] ?? 0);
            if ($duracao > 0) {
                $restante = $duracao - (time() - (int) $sessao['fase_iniciada_em']);
                $payload['temporizador'] = ['duracao' => $duracao, 'restante' => max(0, $restante)];
            }
        }

        // D7: distribuicao (e a letra "correta") so existe a partir da
        // revelacao - antes disso o campo nem aparece no payload.
        if ($fase === 'revelado') {
            $r = distribuicaoQuestao($pdo, $questaoAtual);

            $payload['distribuicao'] = $r['distribuicao'];
            $payload['acertos'] = $r['acertos'];
            $payload['erros'] = $r['erros'];
            $payload['naoResponderam'] = max(0, $payload['online'] - $payload['responderam']);

            // T09c: explicacao so existia pro professor no editor - agora
            // aparece no projetor no momento da revelacao. Opcional, so
            // entra no payload se o professor preencheu.
            if (($questaoAtual['explicacao'] ?? '') !== '') {
                $payload['explicacao'] = $questaoAtual['explicacao'];
            }
        }
    }
}

// Resumo por questao (acertos/erros/nao-responderam + distribuicao) - os
// dados ja estao congelados nesse ponto (responder.php bloqueia escrita
// fora de "respondendo"), so somem de verdade se o professor rodar
// "Limpar" depois (T18), la no admin desktop.
if ($fase === 'encerrada') {
    $payload['resumo'] = resumoSessaoEncerrada($pdo, (int) $sessao['id'], (int) $sessao['prova_id']);
}

jsonResponder($payload);
