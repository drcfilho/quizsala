@echo off
REM QuizSala - duplo clique pra subir tudo. So chama o iniciar.ps1 (mesma
REM logica dos dois lados seria duplicar a deteccao de IP em bat E
REM powershell - script principal fica so no ps1).
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0iniciar.ps1"
pause
