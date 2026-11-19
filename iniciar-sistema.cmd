@echo off
setlocal
title Sistema Equipos
cd /d "%~dp0"

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0iniciar-sistema.ps1"
set "EXITCODE=%ERRORLEVEL%"

if not "%EXITCODE%"=="0" (
    echo.
    echo No se pudo iniciar el sistema. Revisa el mensaje de arriba.
    echo.
    pause
) else (
    ping -n 2 127.0.0.1 >nul
)

exit /b %EXITCODE%
