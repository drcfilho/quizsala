// QuizSala - painel do projetor. Sem interacao, sem gate de versao
// (design.md D3): os contadores mudam por acao do aluno, redesenha tudo
// a cada poll - custo irrelevante numa tela so. T21 abre uma excecao a
// isso: redesenhar do zero a cada 2s destroi o elemento antigo antes de
// poder animar dele ate o valor novo - "respondendo" e "revelado" agora
// atualizam o que ja existe em vez de recriar, do mesmo jeito que a fase
// "aguardando" (T16) ja fazia pelo QR.
var INTERVALO_POLL_MS = 2000;
var params = new URLSearchParams(location.search);
var codigo = params.get('codigo');
var contextoRenderizado = null;

// Achado pelo usuario testando de verdade: iniciar.bat sempre abria um
// codigo fixo (AULA01), que quebra assim que essa sessao semente for
// limpa (T18) ou nunca tiver existido - a tela travava num "codigo nao
// encontrado" seco, exigindo editar a URL na mao. Sem "?codigo=" na URL,
// a tela descobre sozinha qual sessao mostrar (api/sessao-ativa.php) e
// volta a procurar se a sessao que estava mostrando sumir no meio do
// caminho. Um link com ?codigo= explicito continua estrito - erro de
// verdade se nao existir (comportamento previsível pra teste manual, ver
// mapa-urls-teste.html).
var codigoExplicito = !!codigo;

function prefereReduzirMovimento() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

// T21: anima um conjunto de numeros do valor anterior ate o novo (~450ms).
// "formatar" monta o texto final a partir do array de valores correntes -
// serve tanto pra "12" quanto pra "3 de 12" ou "4 online · 3 responderam".
function animarContador(elemento, valoresAntigos, valoresNovos, formatar) {
    if (prefereReduzirMovimento() || valoresAntigos.join(',') === valoresNovos.join(',')) {
        elemento.textContent = formatar(valoresNovos);
        return;
    }

    var inicio = null;
    var duracao = 450;

    function passo(agora) {
        if (inicio === null) {
            inicio = agora;
        }
        var progresso = Math.min((agora - inicio) / duracao, 1);
        var atuais = valoresAntigos.map(function (v, i) {
            return Math.round(v + (valoresNovos[i] - v) * progresso);
        });
        elemento.textContent = formatar(atuais);
        if (progresso < 1) {
            requestAnimationFrame(passo);
        }
    }

    requestAnimationFrame(passo);
}

// T-novo: cronometro por questao. O poll de 2s so traz um "restante" novo
// a cada 2s, mas queremos contar segundo a segundo - um setInterval local
// de 1s reinterpola a partir do ultimo valor do servidor, sem nunca tocar
// o DOM alem do proprio texto/classe do cronometro. Nunca mexe em
// "container" nem em "contextoRenderizado" - por isso nao briga com a
// otimizacao do T21 (fast path so recalibra o estado, nunca redesenha).
var temporizadorEstado = null;
var temporizadorIntervalo = null;

function formatarTempo(segundos) {
    var m = Math.floor(segundos / 60);
    var s = segundos % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
}

function pararTemporizadorLocal() {
    if (temporizadorIntervalo) {
        clearInterval(temporizadorIntervalo);
        temporizadorIntervalo = null;
    }
    temporizadorEstado = null;
}

function atualizarExibicaoTemporizador(elemento) {
    if (!temporizadorEstado) {
        return;
    }
    var decorrido = Math.floor((Date.now() - temporizadorEstado.capturadoEm) / 1000);
    var restanteAtual = Math.max(0, temporizadorEstado.restante - decorrido);
    var esgotado = restanteAtual <= 0;

    var numero = elemento.querySelector('.numero-temporizador');
    if (numero) {
        numero.textContent = formatarTempo(restanteAtual);
    }
    var rotulo = elemento.querySelector('.rotulo-temporizador');
    if (rotulo) {
        // Sinal Duplo tambem aqui: o rotulo muda de texto, nao so a cor da
        // borda (.esgotado no CSS) - "so avisa", nunca revela sozinho.
        rotulo.textContent = esgotado ? 'Tempo esgotado' : 'Tempo restante';
    }
    elemento.classList.toggle('esgotado', esgotado);

    if (esgotado && temporizadorIntervalo) {
        clearInterval(temporizadorIntervalo);
        temporizadorIntervalo = null;
    }
}

function iniciarTemporizadorLocal(elemento, dadosTemporizador) {
    temporizadorEstado = { restante: dadosTemporizador.restante, capturadoEm: Date.now() };
    atualizarExibicaoTemporizador(elemento);
    temporizadorIntervalo = setInterval(function () {
        atualizarExibicaoTemporizador(elemento);
    }, 1000);
}

// Fast path (mesma questao, so o placar mudou): so recalibra o ponto de
// referencia a partir do valor fresco do servidor - nunca recria o
// elemento nem reinicia o intervalo.
function recalibrarTemporizadorLocal(elemento, dadosTemporizador) {
    if (!temporizadorEstado) {
        iniciarTemporizadorLocal(elemento, dadosTemporizador);
        return;
    }
    temporizadorEstado.restante = dadosTemporizador.restante;
    temporizadorEstado.capturadoEm = Date.now();
    atualizarExibicaoTemporizador(elemento);
}

function renderizarTemporizador(container, dadosTemporizador) {
    var painel = document.createElement('p');
    painel.className = 'temporizador-painel';

    var rotulo = document.createElement('span');
    rotulo.className = 'rotulo-temporizador';
    rotulo.textContent = 'Tempo restante';
    painel.appendChild(rotulo);

    var numero = document.createElement('span');
    numero.className = 'numero-temporizador';
    painel.appendChild(numero);

    container.appendChild(painel);
    iniciarTemporizadorLocal(painel, dadosTemporizador);
}

function mensagem(container, texto) {
    var p = document.createElement('p');
    p.className = 'mensagem-painel';
    p.textContent = texto;
    container.appendChild(p);
}

function renderizarContador(container, dados) {
    var bloco = document.createElement('div');
    bloco.className = 'bloco-contador';
    if (dados.online > 0 && dados.responderam >= dados.online) {
        bloco.classList.add('completo');
    }

    var numero = document.createElement('p');
    numero.className = 'numero-contador';
    numero.dataset.responderam = String(dados.responderam);
    numero.dataset.online = String(dados.online);
    numero.textContent = dados.responderam + ' de ' + dados.online;
    bloco.appendChild(numero);

    var rotulo = document.createElement('p');
    rotulo.className = 'rotulo-contador';
    rotulo.textContent = 'responderam';
    bloco.appendChild(rotulo);

    container.appendChild(bloco);
}

// T21: mesma questao, mesma fase "respondendo" - so o placar mudou.
function atualizarContadorRespondendo(container, dados) {
    var bloco = container.querySelector('.bloco-contador');
    var numero = container.querySelector('.numero-contador');
    if (!bloco || !numero) {
        return;
    }

    bloco.classList.toggle('completo', dados.online > 0 && dados.responderam >= dados.online);

    var antigos = [Number(numero.dataset.responderam || 0), Number(numero.dataset.online || 0)];
    var novos = [dados.responderam, dados.online];
    animarContador(numero, antigos, novos, function (v) {
        return v[0] + ' de ' + v[1];
    });
    numero.dataset.responderam = String(dados.responderam);
    numero.dataset.online = String(dados.online);

    var temporizadorEl = container.querySelector('.temporizador-painel');
    if (dados.temporizador && temporizadorEl) {
        recalibrarTemporizadorLocal(temporizadorEl, dados.temporizador);
    }
}

// T16: tela de espera - QR grande (api/qr.php ja aponta pro index.php do
// aluno) com o codigo em monoespaçada gigante como alternativa pra quem
// nao conseguir escanear, e quantos ja entraram enquanto o professor nao
// inicia.
function textoContadorEntrada(online) {
    return online + (online === 1 ? ' pessoa entrou' : ' pessoas entraram');
}

function renderizarEspera(container, dados) {
    var titulo = document.createElement('p');
    titulo.className = 'titulo-espera';
    // T21: pulso sutil sinalizando que a pagina esta viva/atualizando - so
    // opacidade, sem cor nova, discreto ao lado do QR que ja domina a tela.
    var pulso = document.createElement('span');
    pulso.className = 'pulso-ao-vivo';
    pulso.setAttribute('aria-hidden', 'true');
    titulo.appendChild(pulso);
    titulo.appendChild(document.createTextNode('Aguardando o professor'));
    container.appendChild(titulo);

    var qr = document.createElement('img');
    qr.className = 'qr-espera';
    qr.src = 'api/qr.php?codigo=' + encodeURIComponent(codigo);
    qr.alt = 'QR Code para entrar na prova';
    container.appendChild(qr);

    var codigoGrande = document.createElement('p');
    codigoGrande.className = 'codigo-espera';
    codigoGrande.textContent = codigo;
    container.appendChild(codigoGrande);

    var entrada = document.createElement('p');
    entrada.className = 'contador-entrada';
    entrada.textContent = textoContadorEntrada(dados.online || 0);
    container.appendChild(entrada);
}

// T25: essa tela nao e mais so um estado transitorio de "ainda procurando" -
// e a tela de repouso padrao sempre que nenhuma sessao foi marcada "Ativar
// no projetor" (admin/index.php). O servidor nunca escolhe uma sozinho
// (nem a mais recente, nem a semente do bin/init-db.php), entao e normal
// ficar aqui por bastante tempo logo depois do servidor subir.
function renderizarProcurando(container) {
    var titulo = document.createElement('p');
    titulo.className = 'titulo-espera';
    var pulso = document.createElement('span');
    pulso.className = 'pulso-ao-vivo';
    pulso.setAttribute('aria-hidden', 'true');
    titulo.appendChild(pulso);
    titulo.appendChild(document.createTextNode('Aguardando o início da sessão'));
    container.appendChild(titulo);
}

// Extraida de renderizarResultado() pra ser reaproveitada no resumo final
// (uma vez por questao, nao so pra questao atual).
function criarBarrasDistribuicao(distribuicao) {
    var maximo = Math.max.apply(null, distribuicao.map(function (d) { return d.n; }).concat([1]));

    var barras = document.createElement('div');
    barras.className = 'barras-distribuicao';
    var barrasPreenchidas = [];

    distribuicao.forEach(function (d) {
        var linha = document.createElement('div');
        linha.className = 'linha-barra' + (d.correta ? ' linha-correta' : '');

        var letra = document.createElement('span');
        letra.className = 'letra-barra';
        letra.textContent = d.letra;
        linha.appendChild(letra);

        var texto = document.createElement('span');
        texto.className = 'texto-barra';
        texto.textContent = d.texto;
        linha.appendChild(texto);

        var trilha = document.createElement('div');
        trilha.className = 'trilha-barra';
        var preenchida = document.createElement('div');
        preenchida.className = 'barra-preenchida-painel';
        // T21: largura comeca em 0 e so vai pro valor real depois de inserida
        // no DOM (dois rAF - garante que o navegador ja pintou o 0% antes de
        // mudar, senao a transicao de width no CSS nunca dispara).
        preenchida.style.width = '0%';
        trilha.appendChild(preenchida);
        linha.appendChild(trilha);
        barrasPreenchidas.push({ elemento: preenchida, largura: Math.round((d.n / maximo) * 100) });

        var contagem = document.createElement('span');
        contagem.className = 'contagem-barra';
        contagem.textContent = String(d.n);
        linha.appendChild(contagem);

        barras.appendChild(linha);
    });

    if (prefereReduzirMovimento()) {
        barrasPreenchidas.forEach(function (item) {
            item.elemento.style.width = item.largura + '%';
        });
    } else {
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                barrasPreenchidas.forEach(function (item) {
                    item.elemento.style.width = item.largura + '%';
                });
            });
        });
    }

    return barras;
}

function renderizarResultado(container, dados) {
    var resumo = document.createElement('p');
    resumo.className = 'resumo-resultado';
    resumo.textContent = dados.acertos + ' acertos · ' + dados.erros + ' erros · ' + dados.naoResponderam + ' não responderam';
    container.appendChild(resumo);

    container.appendChild(criarBarrasDistribuicao(dados.distribuicao));

    // T09c: so aparece se o professor preencheu explicacao no editor -
    // mesmo idioma de campo opcional que "temporizador"/"distribuicao" ja
    // usam (o campo simplesmente nao vem no payload quando nao se aplica).
    if (dados.explicacao) {
        var callout = document.createElement('div');
        callout.className = 'callout-explicacao';

        var rotuloExp = document.createElement('p');
        rotuloExp.className = 'rotulo-explicacao';
        rotuloExp.textContent = 'Por que essa é a resposta certa';
        callout.appendChild(rotuloExp);

        var textoExp = document.createElement('p');
        textoExp.className = 'texto-explicacao';
        textoExp.textContent = dados.explicacao;
        callout.appendChild(textoExp);

        container.appendChild(callout);
    }
}

// Fase "encerrada": participantes+questoes no topo, depois uma lista por
// questao (acertos/erros/nao-responderam + as mesmas barras da revelacao,
// reaproveitadas via criarBarrasDistribuicao).
function renderizarResumoFinal(container, dados) {
    var resumo = dados.resumo;

    var titulo = document.createElement('p');
    titulo.className = 'titulo-espera';
    titulo.textContent = 'Prova encerrada';
    container.appendChild(titulo);

    var totalP = document.createElement('p');
    totalP.className = 'resumo-resultado';
    totalP.textContent = resumo.totalParticipantes + (resumo.totalParticipantes === 1 ? ' participante' : ' participantes') +
        ' · ' + resumo.totalQuestoes + (resumo.totalQuestoes === 1 ? ' questão' : ' questões');
    container.appendChild(totalP);

    var lista = document.createElement('div');
    lista.className = 'lista-resumo-questoes';

    resumo.questoes.forEach(function (q) {
        var bloco = document.createElement('div');
        bloco.className = 'bloco-questao-resumo';

        var cabecalho = document.createElement('p');
        cabecalho.className = 'cabecalho-questao-resumo';
        cabecalho.textContent = q.ordem + '. ' + q.enunciado;
        bloco.appendChild(cabecalho);

        var mini = document.createElement('p');
        mini.className = 'mini-resumo-questao';
        mini.textContent = q.acertos + ' acertos · ' + q.erros + ' erros · ' + q.naoResponderam + ' não responderam';
        bloco.appendChild(mini);

        bloco.appendChild(criarBarrasDistribuicao(q.distribuicao));
        lista.appendChild(bloco);
    });

    container.appendChild(lista);
}

function renderizar(dados) {
    var painel = document.getElementById('painel');
    var container = document.getElementById('conteudo-painel');

    // T16: continua esperando -> so atualiza o contador. Recriar o <img> do
    // QR a cada poll (2s) faria ele recarregar - e piscar na tela - toda
    // hora, sem necessidade: o QR nao muda enquanto a fase nao muda.
    if (!dados.erro && dados.fase === 'aguardando' && painel.dataset.fase === 'aguardando') {
        var entradaAtual = container.querySelector('.contador-entrada');
        if (entradaAtual) {
            entradaAtual.textContent = textoContadorEntrada(dados.online || 0);
        }
        return;
    }

    // T21: chave da questao+fase atual - usada pra saber se e so o placar
    // mudando (atualiza no lugar) ou uma transicao de verdade (redesenha).
    // "encerrada" tambem precisa de uma chave estavel (nunca muda depois de
    // calculada) - sem isso o resumo final seria redesenhado do zero
    // (barras animadas incluidas) a cada poll de 2s, pra sempre, ja que
    // "dados.questao" nunca existe nessa fase.
    // "encerrada" carrega "ativa" na chave (nao so o nome da fase) - sem
    // isso o professor mandar "Tirar do projetor" (ativa true -> false, fase
    // continua 'encerrada') nunca desmarcaria o "ja redesenhado" abaixo, e o
    // resumo ficaria preso na tela pra sempre em vez de voltar a procurar.
    var chaveAtual = !dados.erro && dados.questao
        ? (dados.fase + ':' + dados.questao.ordem)
        : (!dados.erro && dados.fase === 'encerrada' ? 'encerrada:' + (dados.ativa ? '1' : '0') : null);

    if (dados.fase === 'respondendo' && chaveAtual !== null && chaveAtual === contextoRenderizado) {
        atualizarContadorRespondendo(container, dados);
        return;
    }

    // "revelado" nao aceita resposta nova (api/responder.php fecha a
    // questao) - os numeros ja sao definitivos, redesenhar nada muda e so
    // reiniciaria a animacao das barras a cada poll.
    if (dados.fase === 'revelado' && chaveAtual !== null && chaveAtual === contextoRenderizado) {
        return;
    }

    if (dados.fase === 'encerrada' && chaveAtual !== null && chaveAtual === contextoRenderizado) {
        return;
    }

    // Transicao de verdade: qualquer cronometro da questao anterior para
    // aqui, antes de limpar o container - senao vaza um setInterval batendo
    // num no que ja saiu do DOM.
    pararTemporizadorLocal();

    while (container.firstChild) {
        container.removeChild(container.firstChild);
    }

    if (dados.erro) {
        contextoRenderizado = null;
        if (!codigoExplicito) {
            // sessao que estava sendo mostrada sumiu (ex.: "Encerrar e
            // limpar", T18) - volta a procurar em vez de travar num erro.
            codigo = null;
            painel.dataset.fase = 'procurando';
            renderizarProcurando(container);
            return;
        }
        painel.dataset.fase = 'erro';
        mensagem(container, 'Código de sala não encontrado.');
        return;
    }

    // Professor mandou "Tirar do projetor" (comando "desativar") depois de
    // mostrar o resumo - sessao continua existindo (nada foi apagado), so
    // nao aparece mais aqui. So se aplica a descoberta automatica: um link
    // com "?codigo=" explicito continua mostrando o resumo (mesmo espirito
    // do "erro" acima - estrito, nunca some sozinho).
    if (dados.fase === 'encerrada' && dados.ativa === false && !codigoExplicito) {
        contextoRenderizado = null;
        codigo = null;
        painel.dataset.fase = 'procurando';
        renderizarProcurando(container);
        return;
    }

    painel.dataset.fase = dados.fase;
    contextoRenderizado = chaveAtual;

    if (dados.fase === 'aguardando') {
        renderizarEspera(container, dados);
        return;
    }

    if (dados.fase === 'encerrada') {
        if (dados.resumo) {
            renderizarResumoFinal(container, dados);
        } else {
            mensagem(container, 'Prova encerrada.');
        }
        return;
    }

    if (!dados.questao) {
        return;
    }

    var contadorTopo = document.createElement('p');
    contadorTopo.className = 'contador-topo';
    contadorTopo.textContent = dados.questao.ordem + ' / ' + dados.questao.total;
    container.appendChild(contadorTopo);

    var enunciado = document.createElement('h1');
    enunciado.className = 'enunciado-painel';
    enunciado.textContent = dados.questao.enunciado;
    container.appendChild(enunciado);

    if (dados.fase === 'respondendo') {
        if (dados.temporizador) {
            renderizarTemporizador(container, dados.temporizador);
        }
        renderizarContador(container, dados);
    } else if (dados.fase === 'revelado') {
        renderizarResultado(container, dados);
    }
}

function atualizarUrlComCodigo(novoCodigo) {
    if (!window.history || !window.history.replaceState) {
        return;
    }
    var url = new URL(location.href);
    url.searchParams.set('codigo', novoCodigo);
    window.history.replaceState(null, '', url);
}

function procurarSessao() {
    fetch('api/sessao-ativa.php')
        .then(function (resp) { return resp.json(); })
        .then(function (dados) {
            var painel = document.getElementById('painel');

            if (dados.codigo) {
                codigo = dados.codigo;
                atualizarUrlComCodigo(codigo);
                painel.dataset.fase = 'carregando';
                contextoRenderizado = null;
                poll();
                return;
            }

            // Ainda nada pra mostrar - so redesenha se acabou de entrar
            // nesse estado (senao pisca o pulso a cada poll a toa).
            if (painel.dataset.fase !== 'procurando') {
                var container = document.getElementById('conteudo-painel');
                while (container.firstChild) {
                    container.removeChild(container.firstChild);
                }
                painel.dataset.fase = 'procurando';
                renderizarProcurando(container);
            }
        })
        .catch(function () {
            // rede instavel: tenta de novo no proximo ciclo
        });
}

function poll() {
    if (!codigo) {
        procurarSessao();
        return;
    }

    fetch('api/painel.php?codigo=' + encodeURIComponent(codigo))
        .then(function (resp) { return resp.json(); })
        .then(renderizar)
        .catch(function () {
            // rede instavel: so tenta de novo no proximo poll
        });
}

poll();
setInterval(poll, INTERVALO_POLL_MS);
