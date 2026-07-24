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

        // D7: distribuicao (e a letra "correta") so existe a partir da
        // revelacao - antes disso o campo nem aparece no payload.
        if ($fase === 'revelado') {
            $alternativas = alternativasDaQuestao($pdo, (int) $questaoAtual['id']);
            $stmtContagem = $pdo->prepare(
                'SELECT COUNT(*) FROM respostas WHERE questao_id = ? AND alternativa_id = ?'
            );

            $distribuicao = [];
            $acertos = 0;
            $erros = 0;

            foreach ($alternativas as $i => $alternativa) {
                $stmtContagem->execute([$questaoAtual['id'], $alternativa['id']]);
                $n = (int) $stmtContagem->fetchColumn();
                $ehCorreta = (int) $alternativa['correta'] === 1;

                $distribuicao[] = [
                    'letra' => letraAlternativa($i),
                    'texto' => $alternativa['texto'],
                    'n' => $n,
                    'correta' => $ehCorreta,
                ];

                if ($ehCorreta) {
                    $acertos += $n;
                } else {
                    $erros += $n;
                }
            }

            $payload['distribuicao'] = $distribuicao;
            $payload['acertos'] = $acertos;
            $payload['erros'] = $erros;
            $payload['naoResponderam'] = max(0, $payload['online'] - $payload['responderam']);
        }
    }
}

jsonResponder($payload);
