// QuizSala - fluxo do aluno. Vanilla JS, sem build step (design.md D-restricoes).
var TOKEN_CHAVE = 'quizsala_token';
var INTERVALO_POLL_MS = 2000;
var versaoConhecida = 0;

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
    p.className = 'mensagem-estado';
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
        item.classList.add('marcada');
    }

    var bolha = document.createElement('span');
    bolha.className = 'bolha';
    bolha.textContent = alt.letra;
    item.appendChild(bolha);

    var texto = document.createElement('span');
    texto.className = 'texto-alternativa';
    texto.textContent = alt.letra + ') ' + alt.texto;
    item.appendChild(texto);

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
        mensagemEstado('Prova encerrada. Obrigado!');
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

// Marcacao otimista (design.md secao 7): preenche a bolha na hora, antes da
// resposta da rede. Se o servidor recusar (ja tinha resposta diferente,
// questao fechou), desfaz e explica.
function responder(alternativaId, elemento) {
    var lista = elemento.parentElement;

    Array.prototype.forEach.call(lista.children, function (el) {
        el.classList.remove('marcada');
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
                return;
            }

            Array.prototype.forEach.call(lista.children, function (el) {
                var ehEscolhida = r.dados.escolhida && Number(el.dataset.alternativaId) === r.dados.escolhida;
                el.classList.toggle('marcada', !!ehEscolhida);
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
