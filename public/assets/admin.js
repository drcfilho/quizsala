// QuizSala - controle do professor. Reaproveita api/painel.php pro estado
// (T07, sem duplicar consulta); api/comando.php pras transicoes, com a
// guarda de toque duplo do lado do servidor (T06) - aqui so desabilita o
// botao durante o envio, a garantia de verdade e o WHERE versao=? do PHP.
var INTERVALO_POLL_MS = 2000;
var params = new URLSearchParams(location.search);
var codigo = params.get('codigo');
var versaoAtual = 0;
var enviando = false;
var contextoRenderizado = null;

function prefereReduzirMovimento() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

// T21: mesmo helper de tela.js (arquivos sem modulo/bundler - cada .js
// deste projeto e autocontido de proposito, sem import entre eles).
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

// T-novo: cronometro por questao - mesmo padrao de tela.js (setInterval
// local de 1s recalibrado a cada poll de 2s), duplicado aqui de proposito:
// cada .js deste projeto e autocontido, sem modulo compartilhado entre eles.
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

// T07: "todos responderam" ja destaca o botao Revelar - tempo esgotado
// ganha o mesmo destaque, mesma logica de "so sinaliza, nunca dispara
// sozinho" (design.md D6).
function atualizarDestaqueRevelar(container) {
    var botao = container.querySelector('.botao-acao');
    if (!botao) {
        return;
    }
    var presenca = container.querySelector('.presenca-admin');
    var completo = false;
    if (presenca) {
        var online = Number(presenca.dataset.online || 0);
        var responderam = Number(presenca.dataset.responderam || 0);
        completo = online > 0 && responderam >= online;
    }
    var esgotado = false;
    if (temporizadorEstado) {
        var decorrido = Math.floor((Date.now() - temporizadorEstado.capturadoEm) / 1000);
        esgotado = temporizadorEstado.restante - decorrido <= 0;
    }
    botao.classList.toggle('em-destaque', completo || esgotado);
}

function atualizarExibicaoTemporizador(elemento, container) {
    if (!temporizadorEstado) {
        return;
    }
    var decorrido = Math.floor((Date.now() - temporizadorEstado.capturadoEm) / 1000);
    var restanteAtual = Math.max(0, temporizadorEstado.restante - decorrido);
    var esgotado = restanteAtual <= 0;

    var numero = elemento.querySelector('.numero-temporizador-admin');
    if (numero) {
        numero.textContent = formatarTempo(restanteAtual);
    }
    var rotulo = elemento.querySelector('.rotulo-temporizador-admin');
    if (rotulo) {
        rotulo.textContent = esgotado ? 'Tempo esgotado' : 'Tempo restante';
    }
    elemento.classList.toggle('esgotado', esgotado);

    atualizarDestaqueRevelar(container);

    if (esgotado && temporizadorIntervalo) {
        clearInterval(temporizadorIntervalo);
        temporizadorIntervalo = null;
    }
}

function iniciarTemporizadorLocal(elemento, dadosTemporizador, container) {
    temporizadorEstado = { restante: dadosTemporizador.restante, capturadoEm: Date.now() };
    atualizarExibicaoTemporizador(elemento, container);
    temporizadorIntervalo = setInterval(function () {
        atualizarExibicaoTemporizador(elemento, container);
    }, 1000);
}

function recalibrarTemporizadorLocal(elemento, dadosTemporizador, container) {
    if (!temporizadorEstado) {
        iniciarTemporizadorLocal(elemento, dadosTemporizador, container);
        return;
    }
    temporizadorEstado.restante = dadosTemporizador.restante;
    temporizadorEstado.capturadoEm = Date.now();
    atualizarExibicaoTemporizador(elemento, container);
}

function renderizarTemporizadorAdmin(cartao, dadosTemporizador, container) {
    var painel = document.createElement('p');
    painel.className = 'temporizador-admin';

    var rotulo = document.createElement('span');
    rotulo.className = 'rotulo-temporizador-admin';
    rotulo.textContent = 'Tempo restante';
    painel.appendChild(rotulo);

    var numero = document.createElement('span');
    numero.className = 'numero-temporizador-admin';
    painel.appendChild(numero);

    cartao.appendChild(painel);
    iniciarTemporizadorLocal(painel, dadosTemporizador, container);
}

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

// Rotulo do botao "encerrar" e "Parar prova", nao "Encerrar" - pedido do
// usuario pra deixar claro que a acao e so parar, sem soar mais definitiva/
// destrutiva do que realmente e (isso continua sendo o "Limpar", que apaga
// dados - fica so no admin desktop, ver T18). A acao no servidor continua
// se chamando "encerrar" (api/comando.php, fase "encerrada") - so o texto
// que o professor ve mudou.
var ROTULOS_ACAO = {
    iniciar: 'Iniciar prova',
    revelar: 'Revelar',
    proxima: 'Próxima questão',
    encerrar: 'Parar prova',
    desativar: 'Tirar do projetor',
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
    if (acao === 'encerrar' && !confirm('Parar a prova agora? Não dá pra continuar depois.')) {
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

// Ideia adaptada do QuizLive (layout, nao cor - telas/ e so referencia
// visual externa): acao destrutiva isolada numa area separada, com rotulo
// proprio, em vez de so mudar a cor do botao na mesma pilha dos outros -
// reforco estrutural, nao so visual, da Regra do Sinal Duplo.
function criarZonaRisco(acoes) {
    var zona = document.createElement('div');
    zona.className = 'zona-risco';

    var rotulo = document.createElement('p');
    rotulo.className = 'rotulo-zona-risco';
    rotulo.textContent = 'Zona de risco';
    zona.appendChild(rotulo);

    zona.appendChild(criarBotoes(acoes, null));

    return zona;
}

function mensagem(container, texto) {
    var p = document.createElement('p');
    p.className = 'mensagem-admin';
    p.textContent = texto;
    container.appendChild(p);
}

// T21: mesma questao, mesma fase "respondendo" - so o placar mudou (o
// online/responderam de "revelado" ja fica congelado por natureza, api/
// responder.php fecha a questao - nao precisa desse caminho la).
function atualizarPresencaAdmin(container, dados) {
    var presenca = container.querySelector('.presenca-admin');
    if (!presenca) {
        return;
    }

    var completo = dados.online > 0 && dados.responderam >= dados.online;
    presenca.classList.toggle('completo', completo);

    var antigos = [Number(presenca.dataset.online || 0), Number(presenca.dataset.responderam || 0)];
    var novos = [dados.online, dados.responderam];
    animarContador(presenca, antigos, novos, function (v) {
        return v[0] + ' online · ' + v[1] + ' responderam';
    });
    presenca.dataset.online = String(dados.online);
    presenca.dataset.responderam = String(dados.responderam);

    var temporizadorEl = container.querySelector('.temporizador-admin');
    if (dados.temporizador && temporizadorEl) {
        recalibrarTemporizadorLocal(temporizadorEl, dados.temporizador, container);
    } else {
        atualizarDestaqueRevelar(container);
    }
}

function renderizar(dados) {
    var container = document.getElementById('conteudo-admin');

    var chaveAtual = !dados.erro && dados.questao ? (dados.fase + ':' + dados.questao.ordem) : null;

    if (dados.fase === 'respondendo' && chaveAtual !== null && chaveAtual === contextoRenderizado) {
        versaoAtual = dados.versao;
        atualizarPresencaAdmin(container, dados);
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
        mensagem(container, 'Código de sala não encontrado.');
        return;
    }

    versaoAtual = dados.versao;
    contextoRenderizado = chaveAtual;

    var cartao = document.createElement('div');
    cartao.className = 'cartao-admin';

    var cabecalho = document.createElement('p');
    cabecalho.className = 'cabecalho-admin';
    cabecalho.textContent = 'Sala ' + codigo;
    cartao.appendChild(cabecalho);

    if (dados.fase === 'aguardando') {
        mensagem(cartao, 'Sala aberta, aguardando você iniciar.');
        cartao.appendChild(criarBotoes(['iniciar']));
        // "Encerrar" e o escape hatch de qualquer fase (design.md D6) - inclui
        // aguardando, pro professor conseguir fechar uma sessao aberta por
        // engano (prova errada, turma errada) antes mesmo de iniciar, sem
        // precisar deixar ela solta pra alguem entrar.
        cartao.appendChild(criarZonaRisco(['encerrar']));
        container.appendChild(cartao);
        return;
    }

    if (dados.fase === 'encerrada') {
        // T18/T23: "Limpar" saiu daqui (controle ao vivo, mobile) e foi pro
        // admin desktop (admin/index.php) - e arrumacao feita depois, na
        // mesa, nao no meio da aula. Aqui so avisa onde ir.
        mensagem(cartao, 'Prova encerrada.');
        // O resumo fica fixo no projetor ate esse clique - "desativar" so
        // tira a sessao do ar la, nao apaga nada (isso continua sendo so
        // "Limpar", no admin desktop, de proposito).
        if (dados.ativa) {
            cartao.appendChild(criarBotoes(['desativar']));
        }
        var dica = document.createElement('p');
        dica.className = 'dica-limpar-admin';
        dica.textContent = 'Pra limpar os dados desta sessão, use o admin no computador.';
        cartao.appendChild(dica);
        container.appendChild(cartao);
        return;
    }

    var completo = false;

    if (dados.questao) {
        completo = dados.online > 0 && dados.responderam >= dados.online;

        if (dados.temporizador) {
            renderizarTemporizadorAdmin(cartao, dados.temporizador, container);
        }

        var presenca = document.createElement('p');
        presenca.className = 'presenca-admin' + (completo ? ' completo' : '');
        presenca.dataset.online = String(dados.online);
        presenca.dataset.responderam = String(dados.responderam);
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

    // T07: ao bater 100% (ou o cronometro zerar) destaca o botao Revelar -
    // so sinaliza, nunca dispara sozinho (design.md D6).
    var esgotadoNoRender = dados.fase === 'respondendo' && !!temporizadorEstado && temporizadorEstado.restante <= 0;
    var acoesPrimarias = dados.fase === 'respondendo' ? ['revelar'] : ['proxima'];
    var destaque = (dados.fase === 'respondendo' && (completo || esgotadoNoRender)) ? 'revelar' : null;
    cartao.appendChild(criarBotoes(acoesPrimarias, destaque));
    cartao.appendChild(criarZonaRisco(['encerrar']));

    container.appendChild(cartao);
}

function poll() {
    fetch('../api/painel.php?codigo=' + encodeURIComponent(codigo) + '&admin=1')
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
