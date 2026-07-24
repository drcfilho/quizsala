#!/usr/bin/env bash
# QuizSala - bateria de testes ponta a ponta (design.md secao 11).
# Sobe um servidor php -S de teste, recria o banco, roda os 11 casos.
set -u

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORTA=8099
BASE="http://127.0.0.1:$PORTA"

cd "$RAIZ"
php bin/init-db.php > /dev/null

cd public
php -S 127.0.0.1:$PORTA > /tmp/quizsala-teste.log 2>&1 &
SERVIDOR_PID=$!
cd "$RAIZ"

for i in $(seq 1 20); do
    if curl -s -o /dev/null "$BASE/index.php"; then break; fi
    sleep 0.2
done

# pkill -f nao mata de verdade um php.exe nativo do Windows via MSYS - o
# script travava esperando o processo morrer. Mata pelo PID direto e, no
# Windows, reforca com taskkill (kill sozinho as vezes so sinaliza o
# wrapper do bash, nao o php.exe).
encerrar() {
    kill "$SERVIDOR_PID" > /dev/null 2>&1
    if command -v taskkill > /dev/null 2>&1; then
        taskkill //F //PID "$SERVIDOR_PID" > /dev/null 2>&1
    fi
}
trap encerrar EXIT

PASSOU=0
FALHOU=0

checar() {
    local descricao="$1" esperado="$2" obtido="$3"
    if [ "$esperado" = "$obtido" ]; then
        PASSOU=$((PASSOU + 1))
        echo "  OK  - $descricao"
    else
        FALHOU=$((FALHOU + 1))
        echo "FALHA - $descricao (esperado: $esperado | obtido: $obtido)"
    fi
}

campo_json() {
    php -r '
        $d = json_decode($argv[1], true);
        $caminho = explode(".", $argv[2]);
        $v = $d;
        foreach ($caminho as $c) {
            $v = is_array($v) && array_key_exists($c, $v) ? $v[$c] : null;
        }
        echo is_bool($v) ? ($v ? "true" : "false") : (is_null($v) ? "" : $v);
    ' "$1" "$2"
}

sql() {
    php -r '
        $p = new PDO("sqlite:" . $argv[1]);
        $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        foreach ($p->query($argv[2]) as $r) { echo implode("|", $r), "\n"; }
    ' "$RAIZ/db/quizsala.sqlite" "$1"
}

sql_exec() {
    php -r '
        $p = new PDO("sqlite:" . $argv[1]);
        $p->exec($argv[2]);
    ' "$RAIZ/db/quizsala.sqlite" "$1"
}

echo "=== Caso 1: 3 alunos entram ==="
TOKENS=()
for i in 1 2 3; do
    LOC=$(curl -s -o /dev/null -D - -X POST -d "codigo=AULA01" "$BASE/api/entrar.php" | grep -i '^location:' | tr -d '\r')
    TOKEN=$(echo "$LOC" | sed -n 's/.*t=\([0-9a-f]*\).*/\1/p')
    TOKENS+=("$TOKEN")
done
NOMES=$(sql "SELECT nome FROM participantes ORDER BY id")
checar "3 apelidos sequenciais" "$(printf 'Aluno 01\nAluno 02\nAluno 03')" "$NOMES"

T1="${TOKENS[0]}"; T2="${TOKENS[1]}"; T3="${TOKENS[2]}"

echo "=== Caso 2: estado inicial v=0 -> payload completo ==="
RESP=$(curl -s "$BASE/api/estado.php?token=$T1&v=0")
checar "fase = respondendo" "respondendo" "$(campo_json "$RESP" fase)"
checar "questao.ordem = 1" "1" "$(campo_json "$RESP" questao.ordem)"

echo "=== Caso 3: poll sem mudanca v=1 -> {\"v\":1}, 7 bytes ==="
RESP=$(curl -s "$BASE/api/estado.php?token=$T1&v=1")
checar "corpo exato" '{"v":1}' "$RESP"
checar "tamanho em bytes" "7" "${#RESP}"

echo "=== Caso 4: resposta certa e errada -> gravadas ==="
# alternativa 2 = DNS (correta) na questao 1; alternativa 1 = HTTP (errada)
RESP=$(curl -s -X POST -H 'Content-Type: application/json' -d "{\"token\":\"$T1\",\"alternativa_id\":2}" "$BASE/api/responder.php")
checar "T1 gravou (correta)" "true" "$(campo_json "$RESP" gravou)"
RESP=$(curl -s -X POST -H 'Content-Type: application/json' -d "{\"token\":\"$T2\",\"alternativa_id\":1}" "$BASE/api/responder.php")
checar "T2 gravou (errada)" "true" "$(campo_json "$RESP" gravou)"

echo "=== Caso 5: aluno troca de resposta na mesma questao (enquanto no ar) ==="
RESP=$(curl -s -X POST -H 'Content-Type: application/json' -d "{\"token\":\"$T1\",\"alternativa_id\":3}" "$BASE/api/responder.php")
checar "T1 troca pra alternativa 3" "true" "$(campo_json "$RESP" gravou)"
checar "escolhida agora e 3" "3" "$(campo_json "$RESP" escolhida)"
QTD=$(sql "SELECT COUNT(*) FROM respostas WHERE participante_id = (SELECT id FROM participantes WHERE token = '$T1') AND questao_id = 1")
checar "continua 1 linha so (upsert, nao duplicou)" "1" "$QTD"
RESP=$(curl -s -X POST -H 'Content-Type: application/json' -d "{\"token\":\"$T1\",\"alternativa_id\":2}" "$BASE/api/responder.php")
checar "T1 troca de volta pra alternativa 2 (a certa)" "2" "$(campo_json "$RESP" escolhida)"

echo "=== Caso 6: contagem acerto/erro -> 1 acerto, 1 erro ==="
LINHA=$(sql "SELECT SUM(a.correta), COUNT(*) - SUM(a.correta) FROM respostas r JOIN alternativas a ON a.id = r.alternativa_id WHERE r.questao_id = 1")
checar "1 acerto | 1 erro" "1|1" "$LINHA"

echo "=== Caso 7: presenca -> 3 online ==="
ONLINE=$(sql "SELECT COUNT(*) FROM participantes WHERE sessao_id = 1 AND last_seen >= strftime('%s','now') - 6")
checar "3 online" "3" "$ONLINE"

echo "=== Caso 8: revelacao -> correta liberada, escolhida correta ==="
sql_exec "UPDATE sessoes SET fase='revelado', versao=versao+1 WHERE codigo='AULA01'"
RESP=$(curl -s "$BASE/api/estado.php?token=$T1&v=1")
checar "correta = 2 (DNS)" "2" "$(campo_json "$RESP" correta)"
checar "escolhida = 2" "2" "$(campo_json "$RESP" escolhida)"

echo "=== Caso 9: responder com questao fechada -> 409 fechada ==="
COD=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -d "{\"token\":\"$T3\",\"alternativa_id\":2}" "$BASE/api/responder.php")
checar "http 409" "409" "$COD"
RESP=$(curl -s -X POST -H 'Content-Type: application/json' -d "{\"token\":\"$T3\",\"alternativa_id\":2}" "$BASE/api/responder.php")
checar "erro = fechada" "fechada" "$(campo_json "$RESP" erro)"

echo "=== Caso 10: avanco de questao -> versao 3, questao 2 ==="
sql_exec "UPDATE sessoes SET questao_atual=2, fase='respondendo', versao=versao+1 WHERE codigo='AULA01'"
RESP=$(curl -s "$BASE/api/estado.php?token=$T1&v=1")
checar "v = 3" "3" "$(campo_json "$RESP" v)"
checar "questao.ordem = 2" "2" "$(campo_json "$RESP" questao.ordem)"

echo "=== Caso 11: alternativa de outra questao -> 422 alternativa ==="
COD=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -d "{\"token\":\"$T1\",\"alternativa_id\":2}" "$BASE/api/responder.php")
checar "http 422 (alternativa 2 pertence a questao 1, atual e 2)" "422" "$COD"

echo "=== Caso 12: comando.php sem token_professor -> 403 ==="
COD=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -d '{"codigo":"AULA01","acao":"encerrar","versao_esperada":3}' "$BASE/api/comando.php")
checar "http 403 sem token" "403" "$COD"

echo "=== Caso 13: comando.php com token_professor errado -> 403 ==="
COD=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -d '{"codigo":"AULA01","acao":"encerrar","versao_esperada":3,"token_professor":"errado"}' "$BASE/api/comando.php")
checar "http 403 com token errado" "403" "$COD"

echo "=== Caso 14: comando.php com o token_professor certo -> aplica ==="
PT=$(sql "SELECT token_professor FROM sessoes WHERE codigo='AULA01'")
RESP=$(curl -s -X POST -H 'Content-Type: application/json' -d "{\"codigo\":\"AULA01\",\"acao\":\"encerrar\",\"versao_esperada\":3,\"token_professor\":\"$PT\"}" "$BASE/api/comando.php")
checar "fase = encerrada com token certo" "encerrada" "$(campo_json "$RESP" fase)"

echo "=== Caso 15: estado.php na fase encerrada -> placar e comprovante do aluno ==="
RESP=$(curl -s "$BASE/api/estado.php?token=$T1&v=1")
checar "acertos = 1 (so respondeu a questao 1, certa)" "1" "$(campo_json "$RESP" resultado.acertos)"
checar "total = 3" "3" "$(campo_json "$RESP" resultado.total)"
checar "questao 3 sem resposta -> nao respondeu" "" "$(campo_json "$RESP" resultado.questoes.2.escolhida)"

echo "=== Caso 16: admin/provas.php sem sessao -> 401 (tela de login) ==="
COD=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/admin/provas.php")
checar "http 401 sem sessao" "401" "$COD"

echo "=== Caso 17: admin/provas.php com senha errada -> continua 401 ==="
COD=$(curl -s -c /tmp/quizsala-admin-errado.txt -o /dev/null -w '%{http_code}' -X POST -d "senha_admin=errada" "$BASE/admin/provas.php")
checar "http 401 com senha errada" "401" "$COD"

echo "=== Caso 18: admin/provas.php com senha certa -> autentica e libera ==="
SENHA_ADMIN=$(cat "$RAIZ/db/admin.senha")
COD=$(curl -s -c /tmp/quizsala-admin.txt -o /dev/null -w '%{http_code}' -X POST -d "senha_admin=$SENHA_ADMIN" "$BASE/admin/provas.php")
checar "http 302 (redireciona apos autenticar)" "302" "$COD"
COD=$(curl -s -b /tmp/quizsala-admin.txt -o /dev/null -w '%{http_code}' "$BASE/admin/provas.php")
checar "http 200 com sessao valida" "200" "$COD"

echo "=== Caso 19: admin/provas.php autenticado mas sem csrf -> 403 ==="
COD=$(curl -s -b /tmp/quizsala-admin.txt -o /dev/null -w '%{http_code}' -X POST -d "acao=criar&titulo=Sem CSRF" "$BASE/admin/provas.php")
checar "http 403 sem csrf" "403" "$COD"

echo "=== Caso 20: admin/provas.php autenticado com csrf certo -> cria ==="
CSRF=$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/provas.php" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="\([^"]*\)"/\1/')
COD=$(curl -s -b /tmp/quizsala-admin.txt -o /dev/null -w '%{http_code}' -X POST -d "acao=criar&titulo=Prova via teste&csrf=$CSRF" "$BASE/admin/provas.php")
checar "http 302 (criou)" "302" "$COD"
TITULO=$(sql "SELECT titulo FROM provas ORDER BY id DESC LIMIT 1")
checar "prova criada aparece no banco" "Prova via teste" "$TITULO"

echo "=== Caso 21: gerarCodigoSala nunca usa 0 O 1 I 5 S (100 amostras) ==="
ACHADO=$(php -r '
    require $argv[1] . "/src/db.php";
    require $argv[1] . "/src/util.php";
    $pdo = Db::conexao();
    for ($i = 0; $i < 100; $i++) {
        $c = gerarCodigoSala($pdo);
        if (preg_match("/[0O1I5S]/", $c)) { echo "achou:$c"; exit; }
    }
    echo "limpo";
' "$RAIZ")
checar "nenhum codigo com caractere ambiguo" "limpo" "$ACHADO"

echo "=== Caso 22: nova-sessao.php cria sessao com codigo unico e token proprio ==="
CSRF=$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/nova-sessao.php" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="\([^"]*\)"/\1/')
LOC=$(curl -s -b /tmp/quizsala-admin.txt -o /dev/null -D - -X POST -d "prova_id=1&identificacao=nome&csrf=$CSRF" "$BASE/admin/nova-sessao.php" | grep -i '^location:' | tr -d '\r')
CODIGO2=$(echo "$LOC" | sed -n 's/.*codigo=\([A-Z0-9]*\).*/\1/p')
PT2=$(echo "$LOC" | sed -n 's/.*pt=\([0-9a-f]*\).*/\1/p')
checar "codigo novo diferente de AULA01" "true" "$([ "$CODIGO2" != "AULA01" ] && echo true || echo false)"
checar "sessao nasce em fase aguardando" "aguardando" "$(sql "SELECT fase FROM sessoes WHERE codigo='$CODIGO2'")"

echo "=== Caso 23: respostas da sessao nova nao se misturam com AULA01 ==="
curl -s -X POST -H 'Content-Type: application/json' -d "{\"codigo\":\"$CODIGO2\",\"acao\":\"iniciar\",\"versao_esperada\":0,\"token_professor\":\"$PT2\"}" "$BASE/api/comando.php" > /dev/null
LOC2=$(curl -s -o /dev/null -D - -X POST -d "codigo=$CODIGO2&nome=Maria" "$BASE/api/entrar.php" | grep -i '^location:' | tr -d '\r')
T4=$(echo "$LOC2" | sed -n 's/.*t=\([0-9a-f]*\).*/\1/p')
curl -s -X POST -H 'Content-Type: application/json' -d "{\"token\":\"$T4\",\"alternativa_id\":2}" "$BASE/api/responder.php" > /dev/null
SESSOES_COM_RESPOSTA=$(sql "SELECT COUNT(DISTINCT sessao_id) FROM respostas")
checar "2 sessoes distintas com resposta, sem mistura" "2" "$SESSOES_COM_RESPOSTA"

echo "=== Caso 24: questoes.php renomeia a prova ==="
CSRF=$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/questoes.php?prova_id=1" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="\([^"]*\)"/\1/')
curl -s -b /tmp/quizsala-admin.txt -o /dev/null -X POST -d "acao=renomear&prova_id=1&titulo=Redes - Renomeada&csrf=$CSRF" "$BASE/admin/questoes.php"
checar "titulo atualizado no banco" "Redes - Renomeada" "$(sql "SELECT titulo FROM provas WHERE id=1")"

echo "=== Caso 25: questoes.php 'testar prova' cria sessao e redireciona pro painel ==="
CSRF=$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/questoes.php?prova_id=1" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="\([^"]*\)"/\1/')
LOC3=$(curl -s -b /tmp/quizsala-admin.txt -o /dev/null -D - -X POST -d "acao=testar&prova_id=1&csrf=$CSRF" "$BASE/admin/questoes.php" | grep -i '^location:' | tr -d '\r')
checar "redireciona pra sessao.php com codigo e pt" "true" "$(echo "$LOC3" | grep -q 'sessao.php?codigo=.*&pt=' && echo true || echo false)"

echo "=== Caso 26: provas.php publica e despublica ==="
# "Prova via teste" (Caso 20) nao tem sessao nenhuma - prova 1 ja tem uma
# sessao em 'respondendo' (Caso 22-23) e nao pode mais ser usada aqui por
# causa da regra nova do Caso 31 (nao despublica prova ja iniciada).
IDTESTE=$(sql "SELECT id FROM provas WHERE titulo='Prova via teste'")
CSRF=$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/provas.php" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="\([^"]*\)"/\1/')
curl -s -b /tmp/quizsala-admin.txt -o /dev/null -X POST -d "acao=publicar&prova_id=$IDTESTE&csrf=$CSRF" "$BASE/admin/provas.php"
checar "prova via teste publicada" "1" "$(sql "SELECT publicada FROM provas WHERE id=$IDTESTE")"
checar "aparece no seletor de nova-sessao" "1" "$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/nova-sessao.php" | grep -c "Prova via teste")"
CSRF=$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/provas.php" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="\([^"]*\)"/\1/')
curl -s -b /tmp/quizsala-admin.txt -o /dev/null -X POST -d "acao=despublicar&prova_id=$IDTESTE&csrf=$CSRF" "$BASE/admin/provas.php"
checar "prova via teste despublicada de novo" "0" "$(sql "SELECT publicada FROM provas WHERE id=$IDTESTE")"
checar "some do seletor de nova-sessao" "0" "$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/nova-sessao.php" | grep -c "Prova via teste")"

echo "=== Caso 27: provas.php exclui so com confirmacao = 'excluir' ==="
CSRF=$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/provas.php" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="\([^"]*\)"/\1/')
curl -s -b /tmp/quizsala-admin.txt -o /dev/null -X POST -d "acao=criar&titulo=Descartavel&csrf=$CSRF" "$BASE/admin/provas.php"
IDDESC=$(sql "SELECT id FROM provas WHERE titulo='Descartavel'")
CSRF=$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/provas.php" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="\([^"]*\)"/\1/')
curl -s -b /tmp/quizsala-admin.txt -o /dev/null -X POST -d "acao=excluir&prova_id=$IDDESC&confirmacao=&csrf=$CSRF" "$BASE/admin/provas.php"
checar "sem confirmacao, prova continua" "1" "$(sql "SELECT COUNT(*) FROM provas WHERE id=$IDDESC")"
CSRF=$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/provas.php" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="\([^"]*\)"/\1/')
curl -s -b /tmp/quizsala-admin.txt -o /dev/null -X POST -d "acao=excluir&prova_id=$IDDESC&confirmacao=excluir&csrf=$CSRF" "$BASE/admin/provas.php"
checar "com confirmacao certa, prova some" "0" "$(sql "SELECT COUNT(*) FROM provas WHERE id=$IDDESC")"

echo "=== Caso 28: questao.php salva e recarrega o campo de explicacao ==="
CSRF=$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/questao.php?prova_id=1&id=1" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="\([^"]*\)"/\1/')
curl -s -b /tmp/quizsala-admin.txt -o /dev/null -X POST -d "prova_id=1&id=1&enunciado=Pergunta&alt0=A&alt1=B&correta=1&explicacao=Porque sim&csrf=$CSRF" "$BASE/admin/questao.php"
checar "explicacao gravada" "Porque sim" "$(sql "SELECT explicacao FROM questoes WHERE id=1")"
checar "editor reabre o campo automaticamente" "1" "$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/questao.php?prova_id=1&id=1" | grep -c 'detalhe-explicacao" open')"

echo "=== Caso 29: importar-csv.php cria prova a partir do exemplo ==="
CSRF=$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/importar-csv.php" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="\([^"]*\)"/\1/')
curl -s -b /tmp/quizsala-admin.txt -o /dev/null -X POST -F "csrf=$CSRF" -F "titulo=Prova via CSV" -F "csv=@public/exemplos/exemplo-prova.csv;type=text/csv" "$BASE/admin/importar-csv.php"
IDCSV=$(sql "SELECT id FROM provas WHERE titulo='Prova via CSV'")
checar "3 questoes importadas" "3" "$(sql "SELECT COUNT(*) FROM questoes WHERE prova_id=$IDCSV")"
checar "prova importada nasce como rascunho" "0" "$(sql "SELECT publicada FROM provas WHERE id=$IDCSV")"

echo "=== Caso 30: importar-csv.php rejeita CSV com linha invalida (nada e criado) ==="
printf 'enunciado,alternativa_a,alternativa_b,alternativa_c,alternativa_d,alternativa_e,correta,explicacao\n,X,Y,,,,A,\n' > "bin/.tmp-csv-invalido.csv"
CSRF=$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/importar-csv.php" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="\([^"]*\)"/\1/')
curl -s -b /tmp/quizsala-admin.txt -o /dev/null -X POST -F "csrf=$CSRF" -F "titulo=CSV Invalido" -F "csv=@bin/.tmp-csv-invalido.csv;type=text/csv" "$BASE/admin/importar-csv.php"
checar "nenhuma prova criada com csv invalido" "0" "$(sql "SELECT COUNT(*) FROM provas WHERE titulo='CSV Invalido'")"
rm -f "bin/.tmp-csv-invalido.csv"

echo "=== Caso 31: nao deixa despublicar prova com sessao ja iniciada ==="
# Usa a sessao $CODIGO2 (Caso 22-23, prova_id=1, ainda em 'respondendo') -
# AULA01 ja foi encerrada la no Caso 14, nao serve mais pra este teste.
CSRF=$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/provas.php" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="\([^"]*\)"/\1/')
curl -s -b /tmp/quizsala-admin.txt -o /dev/null -X POST -d "acao=despublicar&prova_id=1&csrf=$CSRF" "$BASE/admin/provas.php"
checar "prova continua publicada (sessao respondendo bloqueia)" "1" "$(sql "SELECT publicada FROM provas WHERE id=1")"
checar "painel do projetor nao foi afetado" "respondendo" "$(campo_json "$(curl -s "$BASE/api/painel.php?codigo=$CODIGO2")" fase)"

echo "=== Caso 32: despublicar funciona normalmente quando nao ha sessao iniciada ==="
CSRF=$(curl -s -b /tmp/quizsala-admin.txt "$BASE/admin/provas.php" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="\([^"]*\)"/\1/')
curl -s -b /tmp/quizsala-admin.txt -o /dev/null -X POST -d "acao=despublicar&prova_id=$IDCSV&csrf=$CSRF" "$BASE/admin/provas.php"
checar "prova sem sessao iniciada despublica normalmente" "0" "$(sql "SELECT publicada FROM provas WHERE id=$IDCSV")"

rm -f /tmp/quizsala-admin.txt /tmp/quizsala-admin-errado.txt

echo ""
echo "================================"
echo "Passou: $PASSOU | Falhou: $FALHOU"
echo "================================"

[ "$FALHOU" -eq 0 ]
