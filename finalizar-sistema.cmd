@echo off
setlocal
title Finalizar Sistema de Equipos
cd /d "%~dp0"

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0finalizar-sistema.ps1"
set "EXITCODE=%ERRORLEVEL%"

if not "%EXITCODE%"=="0" (
    echo.
    echo No se pudo finalizar el sistema. Revisa el mensaje de arriba.
    echo.
    pause
    exit /b %EXITCODE%
)

ping -n 2 127.0.0.1 >nul
exit /b %EXITCODE%
