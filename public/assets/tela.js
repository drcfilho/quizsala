// QuizSala - painel do projetor. Sem interacao, sem gate de versao
// (design.md D3): os contadores mudam por acao do aluno, redesenha tudo
// a cada poll - custo irrelevante numa tela so.
var INTERVALO_POLL_MS = 2000;
var params = new URLSearchParams(location.search);
var codigo = params.get('codigo');

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
    numero.textContent = dados.responderam + ' de ' + dados.online;
    bloco.appendChild(numero);

    var rotulo = document.createElement('p');
    rotulo.className = 'rotulo-contador';
    rotulo.textContent = 'responderam';
    bloco.appendChild(rotulo);

    container.appendChild(bloco);
}

function renderizarResultado(container, dados) {
    var resumo = document.createElement('p');
    resumo.className = 'resumo-resultado';
    resumo.textContent = dados.acertos + ' acertos · ' + dados.erros + ' erros · ' + dados.naoResponderam + ' não responderam';
    container.appendChild(resumo);

    var maximo = Math.max.apply(null, dados.distribuicao.map(function (d) { return d.n; }).concat([1]));

    var barras = document.createElement('div');
    barras.className = 'barras-distribuicao';

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
        preenchida.style.width = Math.round((d.n / maximo) * 100) + '%';
        trilha.appendChild(preenchida);
        linha.appendChild(trilha);

        var contagem = document.createElement('span');
        contagem.className = 'contagem-barra';
        contagem.textContent = String(d.n);
        linha.appendChild(contagem);

        barras.appendChild(linha);
    });

    container.appendChild(barras);
}

function renderizar(dados) {
    var painel = document.getElementById('painel');
    var container = document.getElementById('conteudo-painel');

    while (container.firstChild) {
        container.removeChild(container.firstChild);
    }

    if (dados.erro) {
        painel.dataset.fase = 'erro';
        mensagem(container, 'Código de sala não encontrado.');
        return;
    }

    painel.dataset.fase = dados.fase;

    if (dados.fase === 'aguardando') {
        mensagem(container, 'Aguardando o professor...');
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
