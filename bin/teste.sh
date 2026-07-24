#!/usr/bin/env bash
# QuizSala - bateria de testes ponta a ponta (design.md secao 11).
# Sobe um servidor php -S de teste, recria o banco, roda os 11 casos.
set -u

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORTA=8099
BASE="http://127.0.0.1:$PORTA"

cd "$RAIZ"
php bin/init-db.php > /dev/null

(cd public && php -S 127.0.0.1:$PORTA > /tmp/quizsala-teste.log 2>&1 &)
SERVIDOR_PID=""
for i in $(seq 1 20); do
    if curl -s -o /dev/null "$BASE/index.php"; then break; fi
    sleep 0.2
done

encerrar() {
    pkill -f "php -S 127.0.0.1:$PORTA" > /dev/null 2>&1
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

echo "=== Caso 5: resposta duplicada -> gravou:false, sem duplicata ==="
RESP=$(curl -s -X POST -H 'Content-Type: application/json' -d "{\"token\":\"$T1\",\"alternativa_id\":3}" "$BASE/api/responder.php")
checar "T1 segunda tentativa nao grava" "false" "$(campo_json "$RESP" gravou)"
checar "escolhida continua a original" "2" "$(campo_json "$RESP" escolhida)"
QTD=$(sql "SELECT COUNT(*) FROM respostas WHERE participante_id = (SELECT id FROM participantes WHERE token = '$T1') AND questao_id = 1")
checar "so 1 resposta gravada pra T1/questao1" "1" "$QTD"

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

echo ""
echo "================================"
echo "Passou: $PASSOU | Falhou: $FALHOU"
echo "================================"

[ "$FALHOU" -eq 0 ]
