// QuizSala - controle do professor. Reaproveita api/painel.php pro estado
// (T07, sem duplicar consulta); api/comando.php pras transicoes, com a
// guarda de toque duplo do lado do servidor (T06) - aqui so desabilita o
// botao durante o envio, a garantia de verdade e o WHERE versao=? do PHP.
var INTERVALO_POLL_MS = 2000;
var params = new URLSearchParams(location.search);
var codigo = params.get('codigo');
var versaoAtual = 0;
var enviando = false;

// Token de professor: capacidade separada do "codigo" publico. Sem isso,
// qualquer aluno que soubesse o codigo da sala conseguia chamar
// api/comando.php direto e controlar a prova (achado por revisao de
// seguranca). Vem da URL (?pt=...) na primeira visita, depois persiste em
// localStorage pra sobreviver a um F5 sem o parametro.
var TOKEN_PROFESSOR_CHAVE = 'quizsala_pt_' + codigo;
var tokenProfessor = params.get('pt');
if (tokenProfessor) {
    localStorage.setItem(TOKEN_PROFESSOR_CHAVE, tokenProfessor);
} else {
    tokenProfessor = localStorage.getItem(TOKEN_PROFESSOR_CHAVE) || '';
}

var ROTULOS_ACAO = {
    iniciar: 'Iniciar prova',
    revelar: 'Revelar',
    proxima: 'Próxima questão',
    encerrar: 'Encerrar',
};

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

function desabilitarBotoes(desabilitar) {
    document.querySelectorAll('.botao-acao, .botao-secundario').forEach(function (btn) {
        btn.disabled = desabilitar;
    });
}

function enviarComando(acao) {
    if (enviando) return;
    if (acao === 'encerrar' && !confirm('Encerrar a prova agora? Não dá pra continuar depois.')) {
        return;
    }

    enviando = true;
    desabilitarBotoes(true);

    fetch('../api/comando.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            codigo: codigo,
            acao: acao,
            versao_esperada: versaoAtual,
            token_professor: tokenProfessor,
        }),
    })
        .then(function (resp) {
            return resp.json().then(function (dados) {
                return { status: resp.status, dados: dados };
            });
        })
        .then(function (r) {
            if (r.status === 403) {
                mostrarAviso('Link sem autorização de professor. Peça o link completo de novo.');
            } else if (r.status !== 200) {
                mostrarAviso(r.dados.erro === 'versao' ? 'Já estava atualizado.' : 'Não foi possível.');
            }
        })
        .catch(function () {
            mostrarAviso('Sem conexão. Tente de novo.');
        })
        .then(function () {
            enviando = false;
            desabilitarBotoes(false);
            poll();
        });
}

function criarBotoes(acoes, acaoEmDestaque) {
    var wrap = document.createElement('div');
    wrap.className = 'botoes-admin';

    acoes.forEach(function (acao) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = acao === 'encerrar' ? 'botao-secundario' : 'botao-acao';
        if (acao === acaoEmDestaque) {
            btn.classList.add('em-destaque');
        }
        btn.textContent = ROTULOS_ACAO[acao];
        btn.addEventListener('click', function () {
            enviarComando(acao);
        });
        wrap.appendChild(btn);
    });

    return wrap;
}

function mensagem(container, texto) {
    var p = document.createElement('p');
    p.className = 'mensagem-admin';
    p.textContent = texto;
    container.appendChild(p);
}

function renderizar(dados) {
    var container = document.getElementById('conteudo-admin');
    while (container.firstChild) {
        container.removeChild(container.firstChild);
    }

    if (dados.erro) {
        mensagem(container, 'Código de sala não encontrado.');
        return;
    }

    versaoAtual = dados.versao;

    var cartao = document.createElement('div');
    cartao.className = 'cartao-admin';

    var cabecalho = document.createElement('p');
    cabecalho.className = 'cabecalho-admin';
    cabecalho.textContent = 'Sala ' + codigo;
    cartao.appendChild(cabecalho);

    if (dados.fase === 'aguardando') {
        mensagem(cartao, 'Sala aberta, aguardando você iniciar.');
        cartao.appendChild(criarBotoes(['iniciar']));
        container.appendChild(cartao);
        return;
    }

    if (dados.fase === 'encerrada') {
        mensagem(cartao, 'Prova encerrada.');
        container.appendChild(cartao);
        return;
    }

    var completo = false;

    if (dados.questao) {
        completo = dados.online > 0 && dados.responderam >= dados.online;

        var presenca = document.createElement('p');
        presenca.className = 'presenca-admin' + (completo ? ' completo' : '');
        presenca.textContent = dados.online + ' online · ' + dados.responderam + ' responderam';
        cartao.appendChild(presenca);

        var contadorQ = document.createElement('p');
        contadorQ.className = 'contador-questao-admin';
        contadorQ.textContent = 'Questão ' + dados.questao.ordem + ' de ' + dados.questao.total;
        cartao.appendChild(contadorQ);

        var pergunta = document.createElement('p');
        pergunta.className = 'pergunta-preview';
        pergunta.textContent = dados.questao.enunciado;
        cartao.appendChild(pergunta);
    }

    // T07: ao bater 100%, destaca o botao Revelar - so sinaliza, nunca
    // dispara sozinho (design.md D6).
    var acoesPrimarias = dados.fase === 'respondendo' ? ['revelar'] : ['proxima'];
    var destaque = (dados.fase === 'respondendo' && completo) ? 'revelar' : null;
    cartao.appendChild(criarBotoes(acoesPrimarias.concat(['encerrar']), destaque));

    container.appendChild(cartao);
}

function poll() {
    fetch('../api/painel.php?codigo=' + encodeURIComponent(codigo))
        .then(function (resp) { return resp.json(); })
        .then(renderizar)
        .catch(function () {
            // rede instavel: so tenta de novo no proximo poll
        });
}

if (!codigo) {
    mensagem(document.getElementById('conteudo-admin'), 'Informe o código da sala na URL (?codigo=AULA01).');
} else {
    poll();
    setInterval(poll, INTERVALO_POLL_MS);
}
