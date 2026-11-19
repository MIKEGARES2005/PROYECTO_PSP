param(
    [int] $Port = 8000
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSCommandPath
$frameworkPath = Join-Path $projectRoot 'storage\framework'
$processStatePath = Join-Path $frameworkPath 'system-processes.json'
$requestPath = Join-Path $frameworkPath 'system-shutdown.request'
$processingPath = Join-Path $frameworkPath 'system-shutdown.request.processing'
$heartbeatPath = Join-Path $frameworkPath 'system-shutdown-agent.heartbeat'
$finalizerPath = Join-Path $projectRoot 'finalizar-sistema.ps1'
$logPrefix = Join-Path $env:TEMP ('sistema-equipos-finalizacion-' + [guid]::NewGuid().ToString('N'))
$stdoutPath = "$logPrefix.log"
$stderrPath = "$logPrefix.err"
$finalizerTimeoutSeconds = 90

function Test-LocalPort {
    param([int] $Port)

    $client = New-Object System.Net.Sockets.TcpClient
    $waitHandle = $null

    try {
        $connection = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        $waitHandle = $connection.AsyncWaitHandle

        if (-not $waitHandle.WaitOne(500)) {
            return $false
        }

        $client.EndConnect($connection)

        return $client.Connected
    } catch {
        return $false
    } finally {
        if ($null -ne $waitHandle) {
            $waitHandle.Close()
        }

        $client.Close()
    }
}

function Test-SystemShutdownComplete {
    if ((Test-Path -LiteralPath $processStatePath) -or (Test-LocalPort -Port $Port)) {
        return $false
    }

    $xamppRoot = [System.IO.Path]::GetFullPath('C:\xampp').TrimEnd('\') + '\'

    try {
        $remainingXamppProcesses = @(Get-CimInstance `
            -ClassName Win32_Process `
            -Filter "Name='httpd.exe' OR Name='mysqld.exe'" `
            -ErrorAction Stop |
            Where-Object {
                $_.ExecutablePath -and
                $_.ExecutablePath.StartsWith(
                    $xamppRoot,
                    [System.StringComparison]::OrdinalIgnoreCase
                )
            })

        return $remainingXamppProcesses.Count -eq 0
    } catch {
        return $false
    }
}

if (-not (Test-Path -LiteralPath $finalizerPath)) {
    throw "No se encontro el finalizador $finalizerPath."
}

while ((Test-Path -LiteralPath $processStatePath) -and (Test-LocalPort -Port $Port)) {
    Set-Content -LiteralPath $heartbeatPath -Value ([DateTimeOffset]::UtcNow.ToUnixTimeSeconds()) -Encoding ASCII -NoNewline

    if (Test-Path -LiteralPath $requestPath) {
        try {
            Move-Item -LiteralPath $requestPath -Destination $processingPath -ErrorAction Stop
        } catch {
            Start-Sleep -Milliseconds 500
            continue
        }

        Start-Sleep -Seconds 1

        $failureMessage = $null
        $finalizerExitCode = $null

        try {
            $powerShellPath = (Get-Command 'powershell.exe' -ErrorAction Stop).Source
            $argumentLine = '-NoProfile -ExecutionPolicy Bypass -File "{0}" -Yes' -f $finalizerPath
            $finalizer = Start-Process `
                -FilePath $powerShellPath `
                -ArgumentList $argumentLine `
                -WorkingDirectory $projectRoot `
                -PassThru `
                -WindowStyle Hidden `
                -RedirectStandardOutput $stdoutPath `
                -RedirectStandardError $stderrPath

            $finalizerTimer = [System.Diagnostics.Stopwatch]::StartNew()

            while (-not $finalizer.WaitForExit(1000)) {
                Set-Content -LiteralPath $heartbeatPath `
                    -Value ([DateTimeOffset]::UtcNow.ToUnixTimeSeconds()) `
                    -Encoding ASCII `
                    -NoNewline

                if ($finalizerTimer.Elapsed.TotalSeconds -ge $finalizerTimeoutSeconds) {
                    Stop-Process -Id $finalizer.Id -Force -ErrorAction SilentlyContinue
                    $failureMessage = "El proceso de finalizacion supero $finalizerTimeoutSeconds segundos y se cancelo para permitir otro intento."
                    break
                }
            }

            $finalizer.Refresh()
            $finalizerExitCode = $finalizer.ExitCode

            if ($null -eq $failureMessage -and $finalizerExitCode -ne 0) {
                $failureMessage = 'El finalizador termino con un error. Revisa los servicios de XAMPP y Laravel.'
            }
        } catch {
            $failureMessage = $_.Exception.Message
        }

        if ($null -ne $failureMessage) {
            if (Test-SystemShutdownComplete) {
                foreach ($controlPath in @($processingPath, $heartbeatPath)) {
                    if (Test-Path -LiteralPath $controlPath) {
                        Remove-Item -LiteralPath $controlPath -Force -ErrorAction SilentlyContinue
                    }
                }

                foreach ($logPath in @($stdoutPath, $stderrPath)) {
                    if (Test-Path -LiteralPath $logPath) {
                        Remove-Item -LiteralPath $logPath -Force -ErrorAction SilentlyContinue
                    }
                }

                exit 0
            }

            $details = @($failureMessage)

            foreach ($logPath in @($stderrPath, $stdoutPath)) {
                if (Test-Path -LiteralPath $logPath) {
                    $details += @(Get-Content -LiteralPath $logPath -Tail 12 -ErrorAction SilentlyContinue)
                }
            }

            $message = $details -join [Environment]::NewLine

            if ($message.Length -gt 2200) {
                $message = $message.Substring($message.Length - 2200)
            }

            $systemIsStillActive = (Test-Path -LiteralPath $processStatePath) -and (Test-LocalPort -Port $Port)

            if ($systemIsStillActive) {
                if (-not (Test-Path -LiteralPath $requestPath)) {
                    Move-Item -LiteralPath $processingPath -Destination $requestPath -Force
                } elseif (Test-Path -LiteralPath $processingPath) {
                    Remove-Item -LiteralPath $processingPath -Force
                }

                foreach ($logPath in @($stdoutPath, $stderrPath)) {
                    if (Test-Path -LiteralPath $logPath) {
                        Remove-Item -LiteralPath $logPath -Force -ErrorAction SilentlyContinue
                    }
                }

                Start-Sleep -Seconds 2
                continue
            }

            if (Test-Path -LiteralPath $processingPath) {
                Remove-Item -LiteralPath $processingPath -Force
            }

            if (Test-Path -LiteralPath $heartbeatPath) {
                Remove-Item -LiteralPath $heartbeatPath -Force
            }

            try {
                Add-Type -AssemblyName System.Windows.Forms
                [System.Windows.Forms.MessageBox]::Show(
                    $message,
                    'No se pudo finalizar el sistema',
                    [System.Windows.Forms.MessageBoxButtons]::OK,
                    [System.Windows.Forms.MessageBoxIcon]::Error
                ) | Out-Null
            } finally {
                foreach ($logPath in @($stdoutPath, $stderrPath)) {
                    if (Test-Path -LiteralPath $logPath) {
                        Remove-Item -LiteralPath $logPath -Force -ErrorAction SilentlyContinue
                    }
                }
            }

            exit 1
        }

        if (Test-Path -LiteralPath $processingPath) {
            Remove-Item -LiteralPath $processingPath -Force
        }

        if (Test-Path -LiteralPath $heartbeatPath) {
            Remove-Item -LiteralPath $heartbeatPath -Force
        }

        foreach ($logPath in @($stdoutPath, $stderrPath)) {
            if (Test-Path -LiteralPath $logPath) {
                Remove-Item -LiteralPath $logPath -Force
            }
        }

        exit 0
    }

    Start-Sleep -Seconds 1
}

if (Test-Path -LiteralPath $requestPath) {
    Remove-Item -LiteralPath $requestPath -Force
}

if (Test-Path -LiteralPath $processingPath) {
    Remove-Item -LiteralPath $processingPath -Force
}

if (Test-Path -LiteralPath $heartbeatPath) {
    Remove-Item -LiteralPath $heartbeatPath -Force
}
