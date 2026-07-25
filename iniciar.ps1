# QuizSala - sobe o servidor local, garante o banco pronto e abre o
# projetor no navegador. Um clique (via iniciar.bat) ou "botao direito >
# executar com PowerShell".

$ErrorActionPreference = 'Stop'
$raiz = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $raiz

Write-Host "QuizSala - verificando requisitos..." -ForegroundColor Cyan

function Test-VCRedistInstalado {
    $chaves = @(
        'HKLM:\SOFTWARE\WOW6432Node\Microsoft\VisualStudio\14.0\VC\Runtimes\X64',
        'HKLM:\SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\X64'
    )
    return [bool]($chaves | ForEach-Object { Get-ItemProperty $_ -ErrorAction SilentlyContinue } |
        Where-Object { $_.Installed -eq 1 } | Select-Object -First 1)
}

$phpSistema = Get-Command php -ErrorAction SilentlyContinue
$temVCRedist = Test-VCRedistInstalado

$phpExe = $null
if ($phpSistema -and $temVCRedist) {
    # Caminho comum: PHP e VC++ Redist ja instalados no sistema - usa direto,
    # sem mexer na pasta php\ portatil.
    $phpExe = $phpSistema.Source
}

:menu while (-not $phpExe) {
    $faltando = @()
    if (-not (Get-Command php -ErrorAction SilentlyContinue)) { $faltando += 'PHP' }
    if (-not (Test-VCRedistInstalado)) { $faltando += 'Visual C++ Redistributable' }
    Write-Host ""
    Write-Host "Nao encontrado no sistema: $($faltando -join ', ')." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "O que fazer?"
    Write-Host " [1] Instalar via winget (recomendado, precisa de internet)"
    Write-Host " [2] Instalar so o Visual C++ Redistributable (utils\vc_redist.x64.exe, embutido no release)"
    Write-Host " [3] Usar o PHP portatil incluso (pasta php\), sem instalar nada"
    Write-Host " [4] Sair e instalar manualmente"
    $escolha = Read-Host "Escolha (1-4)"

    switch ($escolha) {
        '1' {
            $winget = Get-Command winget -ErrorAction SilentlyContinue
            if (-not $winget) {
                Write-Host "[AVISO] winget nao encontrado neste sistema." -ForegroundColor Yellow
                continue menu
            }
            if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
                Write-Host "Instalando PHP via winget..."
                winget install --id PHP.PHP.8.3 -e --accept-package-agreements --accept-source-agreements
            }
            if (-not (Test-VCRedistInstalado)) {
                Write-Host "Instalando Visual C++ Redistributable via winget..."
                winget install --id Microsoft.VCRedist.2015+.x64 -e --accept-package-agreements --accept-source-agreements
            }
            Write-Host ""
            Write-Host "Instalacao concluida. Feche esta janela e rode o iniciar.bat de novo" -ForegroundColor Green
            Write-Host "(o PATH so atualiza num terminal novo)."
            Read-Host "Pressione Enter para sair"
            exit 0
        }
        '2' {
            $vcInstalador = Join-Path $raiz 'utils\vc_redist.x64.exe'
            if (-not (Test-Path $vcInstalador)) {
                Write-Host "[ERRO] utils\vc_redist.x64.exe nao existe neste release." -ForegroundColor Red
                continue menu
            }
            Write-Host "Instalando Visual C++ Redistributable..."
            Start-Process -FilePath $vcInstalador -ArgumentList '/install', '/quiet', '/norestart' -Wait
            Write-Host "Visual C++ Redistributable instalado."
            # DLL fica disponivel na hora - nao precisa reiniciar terminal.
            if ((Get-Command php -ErrorAction SilentlyContinue) -and (Test-VCRedistInstalado)) {
                $phpExe = (Get-Command php).Source
            }
        }
        '3' {
            $phpPortatil = Join-Path $raiz 'php\php.exe'
            if (-not (Test-Path $phpPortatil)) {
                Write-Host "[ERRO] Pasta php\ nao encontrada neste release." -ForegroundColor Red
                continue menu
            }
            & $phpPortatil -v *> $null
            if ($LASTEXITCODE -ne 0) {
                Write-Host "[ERRO] php\php.exe nao rodou - falta o Visual C++ Redistributable (tente a opcao 1 ou 2)." -ForegroundColor Red
                continue menu
            }
            Write-Host "PHP portatil - ok"
            $phpExe = $phpPortatil
        }
        '4' {
            Write-Host ""
            Write-Host "PHP: https://windows.php.net/download/"
            Write-Host "Visual C++ Redistributable: https://aka.ms/vs/17/release/vc_redist.x64.exe"
            Write-Host ""
            Read-Host "Pressione Enter para sair"
            exit 1
        }
        default {
            Write-Host "Opcao invalida." -ForegroundColor Yellow
        }
    }
}

$versaoPhp = (& $phpExe -r "echo PHP_VERSION;")
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
    & $phpExe bin\init-db.php
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
& $phpExe -S 0.0.0.0:8080
