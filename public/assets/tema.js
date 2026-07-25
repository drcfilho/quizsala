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

    function aplicar(tema) {
        document.documentElement.dataset.theme = tema;
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
