@echo off
REM QuizSala - duplo clique pra encerrar o servidor com seguranca antes de
REM desligar o notebook. So chama o parar.ps1 (mesma logica dos dois lados
REM seria duplicar aqui).
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0parar.ps1"
pause
