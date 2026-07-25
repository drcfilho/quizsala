// QuizSala - alterna claro/escuro manualmente, por cima do automatico do
// Bulma (prefers-color-scheme, ja embutido no bulma.css vendorizado).
// So escreve o atributo data-theme que o CSS do Bulma ja sabe interpretar
// e lembra a escolha entre sessoes - sem isso, o aluno/professor fica
// preso no tema que o sistema operacional escolheu.
(function () {
    var CHAVE = 'quizsala_tema';

    function temaDoSistema() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    }

    function temaAtual() {
        return localStorage.getItem(CHAVE) || temaDoSistema();
    }

    // Icone mostra o destino do clique, nao o estado atual: sol enquanto
    // esta escuro (clicar leva pro claro), lua enquanto esta claro (clicar
    // leva pro escuro) - pedido do usuario.
    var ICONE_SOL = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg>';
    var ICONE_LUA = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path></svg>';

    function aplicar(tema) {
        document.documentElement.dataset.theme = tema;

        var icone = tema === 'dark' ? ICONE_SOL : ICONE_LUA;
        document.querySelectorAll('[data-alternar-tema] .icon').forEach(function (span) {
            span.innerHTML = icone;
        });
    }

    aplicar(temaAtual());

    document.addEventListener('click', function (evento) {
        var botao = evento.target.closest('[data-alternar-tema]');
        if (!botao) {
            return;
        }
        var novo = temaAtual() === 'dark' ? 'light' : 'dark';
        localStorage.setItem(CHAVE, novo);
        aplicar(novo);
    });
})();
