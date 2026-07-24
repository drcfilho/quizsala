#!/usr/bin/env bash
# QuizSala - encerra o servidor (php -S da porta 8080) de forma limpa antes
# de desligar a maquina. O SQLite em modo WAL ja resiste a um kill abrupto
# (arquitetura.md), mas parar pelo PID certo libera a porta e os arquivos
# -wal/-shm sem deixar um php pendurado em segundo plano.
set -u

PORTA=8080

pid=""
if command -v ss > /dev/null 2>&1; then
    pid=$(ss -ltnp "( sport = :$PORTA )" 2>/dev/null | grep -oP 'pid=\K[0-9]+' | head -1)
fi
if [ -z "$pid" ] && command -v lsof > /dev/null 2>&1; then
    pid=$(lsof -tiTCP:$PORTA -sTCP:LISTEN 2>/dev/null | head -1)
fi
if [ -z "$pid" ] && command -v fuser > /dev/null 2>&1; then
    pid=$(fuser $PORTA/tcp 2>/dev/null | tr -d ' \t')
fi

if [ -z "$pid" ]; then
    echo "Nenhum servidor QuizSala rodando na porta $PORTA - nada a fazer."
    exit 0
fi

nome=$(ps -p "$pid" -o comm= 2>/dev/null)
if [[ "$nome" != php* ]]; then
    echo "[AVISO] A porta $PORTA esta em uso por outro processo (nao php: '$nome') - nao mexi em nada."
    echo "Feche manualmente se tiver certeza do que e."
    exit 1
fi

kill "$pid" 2>/dev/null
for _ in $(seq 1 10); do
    kill -0 "$pid" 2>/dev/null || break
    sleep 0.3
done
if kill -0 "$pid" 2>/dev/null; then
    kill -9 "$pid" 2>/dev/null
fi

echo "Servidor QuizSala encerrado - porta $PORTA liberada."
