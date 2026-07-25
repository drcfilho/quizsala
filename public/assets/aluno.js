// QuizSala - fluxo do aluno. Vanilla JS, sem build step (design.md D-restricoes).
var TOKEN_CHAVE = 'quizsala_token';
var INTERVALO_POLL_MS = 2000;
// -1, nunca 0: toda sessao nasce com versao=0 (schema.sql), e o servidor so
// manda o estado completo quando a versao do cliente diverge da atual
// (api/estado.php) - com 0 aqui, o primeiro poll de uma sessao nova nunca
// bate "diferente" e a tela fica em branco pra sempre (achado no critique).
var versaoConhecida = -1;

function obterToken() {
    var m = location.hash.match(/t=([0-9a-f]+)/);
    if (m) {
        localStorage.setItem(TOKEN_CHAVE, m[1]);
        history.replaceState(null, '', location.pathname);
    }
    return localStorage.getItem(TOKEN_CHAVE);
}

var token = obterToken();
if (!token) {
    location.href = 'index.php';
}

function limparConteudo() {
    var container = document.getElementById('conteudo-prova');
    while (container.firstChild) {
        container.removeChild(container.firstChild);
    }
    return container;
}

function mensagemEstado(texto) {
    var container = limparConteudo();
    var p = document.createElement('p');
    p.className = 'has-text-centered has-text-grey mt-6';
    p.textContent = texto;
    container.appendChild(p);
}

function criarAlternativa(alt, dados) {
    var revelado = dados.fase === 'revelado';
    var marcada = dados.escolhida === alt.id;

    var item = document.createElement('button');
    item.type = 'button';
    item.className = 'alternativa';
    item.dataset.alternativaId = String(alt.id);
    item.setAttribute('aria-pressed', marcada ? 'true' : 'false');

    if (revelado) {
        item.disabled = true;
        if (alt.id === dados.correta) item.classList.add('correta');
        if (marcada && alt.id !== dados.correta) item.classList.add('escolhida-errada');
        if (marcada && alt.id === dados.correta) item.classList.add('escolhida-certa');
    } else if (marcada) {
        // Veio do servidor (poll), entao ja esta confirmada - nunca so
        // "marcada" (que tambem cobre o instante otimista, antes da
        // resposta da rede).
        item.classList.add('marcada', 'enviada');
    }

    var bolha = document.createElement('span');
    bolha.className = 'bolha';
    bolha.textContent = alt.letra;
    item.appendChild(bolha);

    var texto = document.createElement('span');
    texto.className = 'texto-alternativa';
    texto.textContent = alt.letra + ') ' + alt.texto;
    item.appendChild(texto);

    // Achado no critique: na tela, a revelacao so tinha cor+borda - o unico
    // "Acertou"/"Errou" em texto ficava so no comprovante impresso. Regra do
    // Sinal Duplo pede um sinal que nao dependa so de cor tambem aqui, no
    // momento em que mais importa.
    if (revelado && marcada) {
        var status = document.createElement('span');
        status.className = 'status-alternativa';
        status.textContent = alt.id === dados.correta ? '✓ Acertou' : '✕ Errou';
        item.appendChild(status);
    }

    if (!revelado) {
        item.addEventListener('click', function () {
            responder(alt.id, item);
        });
    }

    return item;
}

function renderizarEstado(dados) {
    document.getElementById('nome-aluno').textContent = dados.nome || '';
    document.getElementById('contador-questao').textContent = dados.questao
        ? (dados.questao.ordem + ' / ' + dados.questao.total)
        : '';

    if (dados.fase === 'aguardando') {
        mensagemEstado('Aguardando o professor iniciar...');
        return;
    }

    if (dados.fase === 'encerrada') {
        renderizarPlacar(dados);
        return;
    }

    if (!dados.questao) {
        return;
    }

    var container = limparConteudo();

    var enunciado = document.createElement('p');
    enunciado.className = 'enunciado';
    enunciado.textContent = dados.questao.enunciado;
    container.appendChild(enunciado);

    var lista = document.createElement('div');
    lista.className = 'lista-alternativas';
    lista.setAttribute('role', 'group');

    dados.questao.alternativas.forEach(function (alt) {
        lista.appendChild(criarAlternativa(alt, dados));
    });

    container.appendChild(lista);
}

// T-novo: se o aluno nao clicar em nenhum dos dois botoes do placar, a tela
// avanca sozinha pro agradecimento depois de 5min - sem isso a tela do
// placar fica aberta pra sempre esperando um toque que pode nunca vir.
var timeoutPlacar = null;

function limparTimeoutPlacar() {
    if (timeoutPlacar) {
        clearTimeout(timeoutPlacar);
        timeoutPlacar = null;
    }
}

function criarBotao(texto, classe, aoClicar) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = classe;
    btn.textContent = texto;
    btn.addEventListener('click', aoClicar);
    return btn;
}

// Tela final: placar + opcao de comprovante em PDF. Sem lib nenhuma - o
// dialogo nativo de impressao do navegador ja tem "salvar como PDF" em
// qualquer sistema, e funciona sem internet (design.md D-restricoes).
function renderizarPlacar(dados) {
    limparTimeoutPlacar();

    var container = limparConteudo();
    var resultado = dados.resultado;

    var placar = document.createElement('div');
    placar.className = 'placar-final';

    var numero = document.createElement('p');
    numero.className = 'numero-placar';
    numero.textContent = resultado.acertos + ' / ' + resultado.total;
    placar.appendChild(numero);

    var rotulo = document.createElement('p');
    rotulo.className = 'rotulo-placar';
    rotulo.textContent = 'respostas certas';
    placar.appendChild(rotulo);

    container.appendChild(placar);

    var acoes = document.createElement('div');
    acoes.className = 'mt-6';
    acoes.appendChild(criarBotao('Salvar comprovante em PDF', 'button is-primary is-fullwidth', function () {
        limparTimeoutPlacar();
        prepararComprovante(dados);
        window.print();
        renderizarAgradecimento();
    }));
    // Achado no critique: window.print() abre a folha de impressao/compartilhar
    // do sistema sem aviso nenhum - uma linha explicando evita a surpresa.
    var legenda = document.createElement('p');
    legenda.className = 'legenda-comprovante';
    legenda.textContent = 'Abre a tela de impressão do celular.';
    acoes.appendChild(legenda);
    acoes.appendChild(criarBotao('Concluir', 'button is-fullwidth mt-3', function () {
        limparTimeoutPlacar();
        renderizarAgradecimento();
    }));
    container.appendChild(acoes);

    timeoutPlacar = setTimeout(renderizarAgradecimento, 5 * 60 * 1000);
}

function renderizarAgradecimento() {
    limparTimeoutPlacar();
    var container = limparConteudo();
    var p = document.createElement('p');
    p.className = 'title is-4 has-text-centered mt-6';
    p.textContent = 'Obrigado por participar!';
    container.appendChild(p);
}

// Monta o conteudo so-de-impressao (fora de #conteudo-prova, escondido na
// tela via CSS, visivel so em @media print) com todas as questoes - o
// "comprovante" que o aluno pede pra guardar.
function prepararComprovante(dados) {
    var existente = document.getElementById('comprovante-impressao');
    if (existente) existente.remove();

    var doc = document.createElement('div');
    doc.id = 'comprovante-impressao';

    var titulo = document.createElement('h1');
    titulo.textContent = 'Comprovante de participação — QuizSala';
    doc.appendChild(titulo);

    var meta = document.createElement('p');
    meta.className = 'meta-comprovante';
    meta.textContent = (dados.nome || 'Aluno') + ' · ' + new Date().toLocaleString('pt-BR');
    doc.appendChild(meta);

    var placarTexto = document.createElement('p');
    placarTexto.className = 'placar-comprovante';
    placarTexto.textContent = 'Resultado: ' + dados.resultado.acertos + ' de ' + dados.resultado.total + ' certas';
    doc.appendChild(placarTexto);

    // Ideia adaptada do QuizLive (layout, nao cor - telas/ e so referencia
    // visual externa): comparacao "sua resposta x certa" como duas linhas
    // claras, com sinal em texto ("Acertou"/"Errou") alem da cor - a
    // impressora pode nao ter tinta colorida, o comprovante nao pode
    // depender so disso (Regra do Sinal Duplo vale pra papel tambem).
    var lista = document.createElement('ol');
    dados.resultado.questoes.forEach(function (q) {
        var li = document.createElement('li');
        li.className = q.acertou ? 'item-certo' : 'item-errado';

        var enun = document.createElement('p');
        enun.className = 'enunciado-comprovante';
        enun.textContent = q.enunciado;
        li.appendChild(enun);

        var status = document.createElement('p');
        status.className = 'status-comprovante';
        status.textContent = q.acertou ? '✓ Acertou' : '✕ Errou';
        li.appendChild(status);

        var resp = document.createElement('p');
        resp.className = 'resposta-comprovante';
        resp.textContent = 'Sua resposta: ' + (q.escolhida || 'não respondeu');
        li.appendChild(resp);

        if (!q.acertou) {
            var certa = document.createElement('p');
            certa.className = 'resposta-certa-comprovante';
            certa.textContent = 'Resposta certa: ' + q.correta;
            li.appendChild(certa);
        }

        lista.appendChild(li);
    });
    doc.appendChild(lista);

    document.body.appendChild(doc);
}

// Marcacao otimista (design.md secao 7): preenche a bolha na hora, antes da
// resposta da rede. Se o servidor recusar (ja tinha resposta diferente,
// questao fechou), desfaz e explica.
function responder(alternativaId, elemento) {
    var lista = elemento.parentElement;

    Array.prototype.forEach.call(lista.children, function (el) {
        el.classList.remove('marcada', 'enviada');
        el.setAttribute('aria-pressed', 'false');
    });
    elemento.classList.add('marcada');
    elemento.setAttribute('aria-pressed', 'true');

    fetch('api/responder.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: token, alternativa_id: alternativaId }),
    })
        .then(function (resp) {
            return resp.json().then(function (dados) {
                return { status: resp.status, dados: dados };
            });
        })
        .then(function (r) {
            if (r.status === 200 && r.dados.escolhida === alternativaId) {
                // Servidor confirmou - so agora vira "enviada" (distinto de
                // so "marcada", achado no critique: sem isso um toque que
                // falhou em silencio parece identico a um confirmado).
                elemento.classList.add('enviada');
                return;
            }

            Array.prototype.forEach.call(lista.children, function (el) {
                var ehEscolhida = r.dados.escolhida && Number(el.dataset.alternativaId) === r.dados.escolhida;
                el.classList.toggle('marcada', !!ehEscolhida);
                el.classList.toggle('enviada', !!ehEscolhida);
                el.setAttribute('aria-pressed', ehEscolhida ? 'true' : 'false');
            });

            if (r.status !== 200) {
                mostrarAviso(r.dados.erro === 'fechada' ? 'Essa questão já foi encerrada.' : 'Não foi possível registrar a resposta.');
            }
        })
        .catch(function () {
            mostrarAviso('Sem conexão. Tente de novo.');
        });
}

function mostrarAviso(texto) {
    var existente = document.querySelector('.aviso-flutuante');
    if (existente) existente.remove();

    var aviso = document.createElement('div');
    aviso.className = 'aviso-flutuante';
    aviso.textContent = texto;
    document.body.appendChild(aviso);

    setTimeout(function () {
        aviso.remove();
    }, 2500);
}

function poll() {
    fetch('api/estado.php?token=' + encodeURIComponent(token) + '&v=' + versaoConhecida)
        .then(function (resp) {
            if (resp.status === 404) {
                localStorage.removeItem(TOKEN_CHAVE);
                location.href = 'index.php';
                return null;
            }
            return resp.json();
        })
        .then(function (dados) {
            if (!dados || dados.v === undefined) return;
            if (dados.v !== versaoConhecida) {
                versaoConhecida = dados.v;
                renderizarEstado(dados);
            }
        })
        .catch(function () {
            // rede instavel: so tenta de novo no proximo poll, sem travar a tela
        });
}

poll();
setInterval(poll, INTERVALO_POLL_MS);
