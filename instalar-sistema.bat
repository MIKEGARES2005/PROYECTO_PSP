@echo off
setlocal
title Instalador del Sistema de Equipos ITIBB

set "INSTALLER_URL=https://raw.githubusercontent.com/MIKEGARES2005/PROYECTO_PSP/main/instalar-sistema.ps1"
set "INSTALLER_FILE=%TEMP%\sistema-equipos-installer-%RANDOM%-%RANDOM%.ps1"

powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference = 'Stop'; [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -UseBasicParsing -Uri $env:INSTALLER_URL -OutFile $env:INSTALLER_FILE"
if errorlevel 1 (
    echo No se pudo descargar el instalador desde GitHub.
    pause
    exit /b 1
)

set "XAMPP_INSTALLER_FILE=%~dp0xampp-windows-x64-8.5.12-0-VS17-installer.exe"
if exist "%XAMPP_INSTALLER_FILE%" (
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%INSTALLER_FILE%" -XamppInstallerPath "%XAMPP_INSTALLER_FILE%"
) else (
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%INSTALLER_FILE%"
)
set "INSTALLER_EXIT_CODE=%ERRORLEVEL%"

del "%INSTALLER_FILE%" >nul 2>&1

if not "%INSTALLER_EXIT_CODE%"=="0" (
    echo.
    echo La instalacion no termino correctamente. Revisa el error anterior.
    pause
)

exit /b %INSTALLER_EXIT_CODE%
