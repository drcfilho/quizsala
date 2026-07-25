@echo off
REM Abre o phpLiteAdmin so nesta maquina (127.0.0.1) - nao aparece pra
REM ninguem mais na rede da sala, ao contrario do servidor principal.
setlocal
set "AQUI=%~dp0"
set "PHP=%AQUI%..\php\php.exe"
if not exist "%PHP%" set "PHP=php"

echo Abrindo dbadmin em http://127.0.0.1:8081 (so neste computador)...
echo Senha em dbadmin\phpliteadmin.config.php
start "" "http://127.0.0.1:8081/"
cd /d "%AQUI%"
"%PHP%" -S 127.0.0.1:8081
