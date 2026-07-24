# QuizSala - sobe o servidor local, garante o banco pronto e abre o
# projetor no navegador. Um clique (via iniciar.bat) ou "botao direito >
# executar com PowerShell".

$ErrorActionPreference = 'Stop'
$raiz = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $raiz

Write-Host "QuizSala - verificando requisitos..." -ForegroundColor Cyan

# Teste de requisito: sem PHP no PATH nada funciona, e o erro que aparece
# se pular essa checagem (comando nao encontrado, silencioso) confunde
# muito mais que uma mensagem direta aqui.
$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) {
    Write-Host ""
    Write-Host "[ERRO] PHP nao encontrado no PATH." -ForegroundColor Red
    Write-Host "Instale o PHP 8.2 ou mais novo: https://windows.php.net/download/"
    Write-Host "Depois de instalar, abra um terminal NOVO e confirme com 'php -v'."
    Write-Host "(Precisa reiniciar o terminal/PowerShell pra pegar o PATH atualizado.)"
    Write-Host ""
    Read-Host "Pressione Enter para sair"
    exit 1
}

$versaoPhp = (php -r "echo PHP_VERSION;")
$versaoMinima = [version]"8.2.0"
$versaoAtual = [version]($versaoPhp -replace '-.*$', '')
if ($versaoAtual -lt $versaoMinima) {
    Write-Host ""
    Write-Host "[ERRO] PHP $versaoPhp encontrado, mas o QuizSala precisa do 8.2 ou mais novo." -ForegroundColor Red
    Write-Host ""
    Read-Host "Pressione Enter para sair"
    exit 1
}
Write-Host "PHP $versaoPhp - ok"

if (-not (Test-Path "db\quizsala.sqlite")) {
    Write-Host "Banco nao encontrado - criando pela primeira vez (prova de exemplo + sala AULA01)..."
    php bin\init-db.php
} else {
    Write-Host "Banco ja existe - mantendo provas/sessoes salvas."
}

# Primeiro IPv4 privado que nao seja loopback/adaptador virtual (Hyper-V,
# WSL) - o mais provavel de ser a rede real que os celulares enxergam.
# Heuristica, nao garantia: em maquina com varias placas, confira o IP
# mostrado abaixo antes de projetar.
$ip = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object {
        $_.IPAddress -notmatch '^(127\.|169\.254\.)' -and
        $_.InterfaceAlias -notmatch 'Loopback|vEthernet|WSL'
    } |
    Select-Object -First 1 -ExpandProperty IPAddress

if (-not $ip) {
    Write-Host "[AVISO] Nao encontrei um IP de rede - usando 127.0.0.1 (so funciona neste computador)." -ForegroundColor Yellow
    $ip = '127.0.0.1'
}

Write-Host ""
Write-Host "===================================================" -ForegroundColor Green
Write-Host " QuizSala rodando em http://${ip}:8080"
Write-Host " Aluno entra em:   http://${ip}:8080/index.php"
Write-Host " Projetor:         http://${ip}:8080/tela.php  (acha a sessao ativa sozinho)"
Write-Host " Admin de provas:  http://${ip}:8080/admin/index.php"
Write-Host " ---------------------------------------------------"
Write-Host " O projetor abre mostrando a tela de espera (QR Code) ate"
Write-Host " voce clicar 'Iniciar prova' no controle do professor -"
Write-Host " o link com o codigo e o token fica em admin/index.php."
Write-Host "===================================================" -ForegroundColor Green
Write-Host ""
Write-Host "Deixe esta janela aberta - fechar encerra o servidor." -ForegroundColor Yellow
Write-Host ""

# Sem "?codigo=" fixo de proposito (achado testando de verdade): o codigo
# da sessao e gerado na hora (T08) e muda toda vez - abrir sempre um valor
# fixo (ex.: AULA01) quebrava assim que essa sessao semente sumia. tela.php
# sem codigo descobre sozinho qual sessao mostrar (api/sessao-ativa.php).
Start-Process "http://${ip}:8080/tela.php"

Set-Location public
php -S 0.0.0.0:8080
