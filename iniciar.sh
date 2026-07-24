#!/usr/bin/env bash
# QuizSala - sobe o servidor local, garante o banco pronto e abre o
# projetor no navegador. Equivalente a iniciar.ps1/iniciar.bat (Windows)
# pra quem roda o QuizSala num notebook Linux.
set -e

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$RAIZ"

echo "QuizSala - verificando requisitos..."

# Sem PHP no PATH nada funciona, e o erro que aparece se pular essa checagem
# (comando nao encontrado, silencioso) confunde muito mais que uma mensagem
# direta aqui.
if ! command -v php > /dev/null 2>&1; then
    echo ""
    echo "[ERRO] PHP nao encontrado no PATH."
    echo "Instale o PHP 8.2 ou mais novo (ex.: sudo apt install php-cli php-sqlite3 php-gd)."
    echo ""
    exit 1
fi

VERSAO_PHP=$(php -r 'echo PHP_VERSION;')
if ! php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'; then
    echo ""
    echo "[ERRO] PHP $VERSAO_PHP encontrado, mas o QuizSala precisa do 8.2 ou mais novo."
    echo ""
    exit 1
fi
echo "PHP $VERSAO_PHP - ok"

# Em muitas distros o php-cli vem sem pdo_sqlite/gd por padrao (pacotes
# separados) - preferi checar aqui a deixar o erro estourar so quando o
# aluno ja estiver tentando entrar.
if ! php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);' > /dev/null 2>&1; then
    echo ""
    echo "[ERRO] extensao pdo_sqlite do PHP nao esta habilitada (ex.: sudo apt install php-sqlite3)."
    echo ""
    exit 1
fi
if ! php -r 'exit(extension_loaded("gd") ? 0 : 1);' > /dev/null 2>&1; then
    echo ""
    echo "[ERRO] extensao gd do PHP nao esta habilitada - o QR Code (T15) depende dela (ex.: sudo apt install php-gd)."
    echo ""
    exit 1
fi

if [ ! -f "db/quizsala.sqlite" ]; then
    echo "Banco nao encontrado - criando pela primeira vez (prova de exemplo + sala AULA01)..."
    php bin/init-db.php
else
    echo "Banco ja existe - mantendo provas/sessoes salvas."
fi

# Primeiro IPv4 privado que nao seja loopback - o mais provavel de ser a
# rede real que os celulares enxergam. Heuristica, nao garantia: em maquina
# com varias interfaces (Docker, VPN), confira o IP mostrado abaixo antes
# de projetar.
IP=$(hostname -I 2>/dev/null | tr ' ' '\n' | grep -v '^127\.' | grep -v '^$' | head -1)
if [ -z "$IP" ]; then
    echo "[AVISO] Nao encontrei um IP de rede - usando 127.0.0.1 (so funciona nesta maquina)."
    IP="127.0.0.1"
fi

echo ""
echo "==================================================="
echo " QuizSala rodando em http://$IP:8080"
echo " Aluno entra em:   http://$IP:8080/index.php"
echo " Projetor:         http://$IP:8080/tela.php?codigo=AULA01"
echo " Admin de provas:  http://$IP:8080/admin/index.php"
echo "==================================================="
echo ""
echo "Deixe este terminal aberto - Ctrl+C encerra o servidor."
echo "(Ou rode ./parar.sh de outro terminal antes de desligar a maquina.)"
echo ""

if command -v xdg-open > /dev/null 2>&1; then
    xdg-open "http://$IP:8080/tela.php?codigo=AULA01" > /dev/null 2>&1 &
fi

cd public
exec php -S 0.0.0.0:8080
