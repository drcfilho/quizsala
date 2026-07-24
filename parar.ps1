# QuizSala - encerra o servidor (php -S da porta 8080) de forma limpa antes
# de desligar o notebook. O SQLite em modo WAL ja resiste a um kill abrupto
# (arquitetura.md), mas parar pelo PID certo libera a porta e os arquivos
# -wal/-shm sem deixar um php.exe pendurado em segundo plano.

$ErrorActionPreference = 'Stop'
$porta = 8080

$conexao = Get-NetTCPConnection -LocalPort $porta -State Listen -ErrorAction SilentlyContinue |
    Select-Object -First 1

if (-not $conexao) {
    Write-Host "Nenhum servidor QuizSala rodando na porta $porta - nada a fazer." -ForegroundColor Yellow
    exit 0
}

$processo = Get-Process -Id $conexao.OwningProcess -ErrorAction SilentlyContinue

if (-not $processo -or $processo.ProcessName -notmatch '^php$') {
    $nome = if ($processo) { $processo.ProcessName } else { "desconhecido" }
    Write-Host "[AVISO] A porta $porta esta em uso por outro processo (nao php: '$nome') - nao mexi em nada." -ForegroundColor Yellow
    Write-Host "Feche manualmente se tiver certeza do que e." -ForegroundColor Yellow
    Read-Host "Pressione Enter para sair"
    exit 1
}

Stop-Process -Id $processo.Id -Force
Write-Host "Servidor QuizSala encerrado - porta $porta liberada." -ForegroundColor Green
