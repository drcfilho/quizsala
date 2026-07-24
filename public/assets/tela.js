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

function renderizarResultado(container, dados) {
    var resumo = document.createElement('p');
    resumo.className = 'resumo-resultado';
    resumo.textContent = dados.acertos + ' acertos · ' + dados.erros + ' erros · ' + dados.naoResponderam + ' não responderam';
    container.appendChild(resumo);

    var maximo = Math.max.apply(null, dados.distribuicao.map(function (d) { return d.n; }).concat([1]));

    var barras = document.createElement('div');
    barras.className = 'barras-distribuicao';
    var barrasPreenchidas = [];

    dados.distribuicao.forEach(function (d) {
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

    container.appendChild(barras);

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
    var chaveAtual = !dados.erro && dados.questao ? (dados.fase + ':' + dados.questao.ordem) : null;

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

    while (container.firstChild) {
        container.removeChild(container.firstChild);
    }

    if (dados.erro) {
        painel.dataset.fase = 'erro';
        contextoRenderizado = null;
        mensagem(container, 'Código de sala não encontrado.');
        return;
    }

    painel.dataset.fase = dados.fase;
    contextoRenderizado = chaveAtual;

    if (dados.fase === 'aguardando') {
        renderizarEspera(container, dados);
        return;
    }

    if (dados.fase === 'encerrada') {
        mensagem(container, 'Prova encerrada.');
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
        renderizarContador(container, dados);
    } else if (dados.fase === 'revelado') {
        renderizarResultado(container, dados);
    }
}

function poll() {
    fetch('api/painel.php?codigo=' + encodeURIComponent(codigo))
        .then(function (resp) { return resp.json(); })
        .then(renderizar)
        .catch(function () {
            // rede instavel: so tenta de novo no proximo poll
        });
}

if (!codigo) {
    mensagem(document.getElementById('conteudo-painel'), 'Informe o código da sala na URL (?codigo=AULA01).');
} else {
    poll();
    setInterval(poll, INTERVALO_POLL_MS);
}
