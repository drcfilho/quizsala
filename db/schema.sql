-- QuizSala - schema SQLite (design.md secao 4)
PRAGMA foreign_keys = ON;

-- publicada: prova recem-criada comeca como rascunho (0), invisivel pro
-- seletor de nova sessao - so aparece pro aluno/projetor/professor depois
-- que o professor clica "Publicar" em provas.php.
CREATE TABLE provas (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    titulo     TEXT    NOT NULL,
    publicada  INTEGER NOT NULL DEFAULT 0 CHECK (publicada IN (0, 1)),
    criada_em  INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);

-- explicacao: por que a alternativa correta esta certa - opcional, exibido
-- so no editor (campo fica oculto ate o professor clicar pra abrir).
CREATE TABLE questoes (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    prova_id   INTEGER NOT NULL REFERENCES provas(id) ON DELETE CASCADE,
    enunciado  TEXT    NOT NULL,
    explicacao TEXT,
    ordem      INTEGER NOT NULL
);
CREATE INDEX idx_questoes_prova ON questoes(prova_id, ordem);

CREATE TABLE alternativas (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    questao_id  INTEGER NOT NULL REFERENCES questoes(id) ON DELETE CASCADE,
    texto       TEXT    NOT NULL,
    correta     INTEGER NOT NULL DEFAULT 0 CHECK (correta IN (0, 1)),
    ordem       INTEGER NOT NULL
);
CREATE INDEX idx_alternativas_questao ON alternativas(questao_id, ordem);

-- modo/identificacao: schema comporta sincrono+assincrono e anonimo+nome
-- desde a v1 (design.md D8/D5), mesmo a v1 so implementando sincrono.
-- token_professor: capacidade separada do "codigo" publico (D-seguranca,
-- achado por revisao automatica). O codigo e dado a todo aluno pra entrar -
-- sem um segredo a parte, qualquer aluno que soubesse o codigo conseguia
-- chamar api/comando.php direto e revelar o gabarito, pular questao ou
-- encerrar a prova, derrubando a propria protecao do D7 (gabarito so sai
-- apos revelacao). Nao e um login (D explicito contra isso, rede local
-- fechada) - e um token opaco, mesmo padrao ja usado pro aluno (D4).
CREATE TABLE sessoes (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    prova_id         INTEGER NOT NULL REFERENCES provas(id) ON DELETE CASCADE,
    codigo           TEXT    NOT NULL UNIQUE,
    token_professor  TEXT    NOT NULL UNIQUE,
    modo             TEXT    NOT NULL DEFAULT 'sincrono' CHECK (modo IN ('sincrono', 'assincrono')),
    identificacao    TEXT    NOT NULL DEFAULT 'anonimo'  CHECK (identificacao IN ('anonimo', 'nome')),
    questao_atual    INTEGER NOT NULL DEFAULT 1,
    fase             TEXT    NOT NULL DEFAULT 'aguardando' CHECK (fase IN ('aguardando', 'respondendo', 'revelado', 'encerrada')),
    versao           INTEGER NOT NULL DEFAULT 0,
    criada_em        INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);

CREATE TABLE participantes (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    sessao_id     INTEGER NOT NULL REFERENCES sessoes(id) ON DELETE CASCADE,
    token         TEXT    NOT NULL UNIQUE,
    nome          TEXT,
    indice_atual  INTEGER NOT NULL DEFAULT 1,
    last_seen     INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);
CREATE INDEX idx_participantes_sessao ON participantes(sessao_id, last_seen);

CREATE TABLE respostas (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    sessao_id        INTEGER NOT NULL REFERENCES sessoes(id) ON DELETE CASCADE,
    participante_id  INTEGER NOT NULL REFERENCES participantes(id) ON DELETE CASCADE,
    questao_id       INTEGER NOT NULL REFERENCES questoes(id) ON DELETE CASCADE,
    alternativa_id   INTEGER NOT NULL REFERENCES alternativas(id) ON DELETE CASCADE,
    criada_em        INTEGER NOT NULL DEFAULT (strftime('%s','now')),
    UNIQUE (participante_id, questao_id)
);
CREATE INDEX idx_respostas_questao ON respostas(sessao_id, questao_id);
