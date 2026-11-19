$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
[System.Windows.Forms.Application]::EnableVisualStyles()

$projectRoot = Split-Path -Parent $PSCommandPath
$startupScript = Join-Path $projectRoot 'iniciar-sistema.ps1'
$iconPath = Join-Path $projectRoot 'public\favicon.ico'
$powerShellPath = (Get-Command 'powershell.exe' -ErrorAction Stop).Source
$logPrefix = Join-Path $env:TEMP ('sistema-equipos-inicio-' + [guid]::NewGuid().ToString('N'))
$stdoutPath = "$logPrefix.log"
$stderrPath = "$logPrefix.err"
$signalPath = "$logPrefix.json"
$singleInstanceMutex = New-Object System.Threading.Mutex($false, 'Local\SistemaEquiposITIBBStartup')
$ownsSingleInstanceMutex = $false
$state = [hashtable]::Synchronized(@{
    Process      = $null
    StartedAt    = Get-Date
    ExitCode     = $null
    ErrorMessage = $null
    IsReady      = $false
})
$icon = $null
$logoImage = $null

try {
    try {
        $ownsSingleInstanceMutex = $singleInstanceMutex.WaitOne(0)
    } catch [System.Threading.AbandonedMutexException] {
        $ownsSingleInstanceMutex = $true
    }

    if (-not $ownsSingleInstanceMutex) {
        [System.Windows.Forms.MessageBox]::Show(
            'El sistema ya se está iniciando. Espera a que termine antes de volver a intentarlo.',
            'Inicio ya en curso',
            [System.Windows.Forms.MessageBoxButtons]::OK,
            [System.Windows.Forms.MessageBoxIcon]::Information
        ) | Out-Null
        exit 2
    }

    $form = New-Object System.Windows.Forms.Form
    $form.Text = 'Sistema de Equipos ITIBB'
    $form.StartPosition = [System.Windows.Forms.FormStartPosition]::CenterScreen
    $form.FormBorderStyle = [System.Windows.Forms.FormBorderStyle]::FixedDialog
    $form.ClientSize = New-Object System.Drawing.Size(500, 270)
    $form.BackColor = [System.Drawing.Color]::FromArgb(11, 18, 38)
    $form.ForeColor = [System.Drawing.Color]::White
    $form.ShowInTaskbar = $false
    $form.TopMost = $true
    $form.ControlBox = $false

    $logo = New-Object System.Windows.Forms.PictureBox
    $logo.Location = New-Object System.Drawing.Point(34, 36)
    $logo.Size = New-Object System.Drawing.Size(76, 76)
    $logo.SizeMode = [System.Windows.Forms.PictureBoxSizeMode]::Zoom

    if (Test-Path -LiteralPath $iconPath) {
        try {
            $icon = New-Object System.Drawing.Icon($iconPath)
            $form.Icon = $icon
            $logoImage = $icon.ToBitmap()
            $logo.Image = $logoImage
        } catch {
            $logoImage = $null
        }
    }

    $title = New-Object System.Windows.Forms.Label
    $title.AutoSize = $true
    $title.Location = New-Object System.Drawing.Point(130, 42)
    $title.ForeColor = [System.Drawing.Color]::White
    $title.Font = New-Object System.Drawing.Font('Segoe UI', 19, [System.Drawing.FontStyle]::Bold)
    $title.Text = 'Sistema de Equipos'

    $subtitle = New-Object System.Windows.Forms.Label
    $subtitle.AutoSize = $true
    $subtitle.Location = New-Object System.Drawing.Point(133, 82)
    $subtitle.ForeColor = [System.Drawing.Color]::FromArgb(148, 163, 184)
    $subtitle.Font = New-Object System.Drawing.Font('Segoe UI', 10, [System.Drawing.FontStyle]::Regular)
    $subtitle.Text = 'ITIBB | Informatica Industrial'

    $status = New-Object System.Windows.Forms.Label
    $status.AutoSize = $false
    $status.Location = New-Object System.Drawing.Point(36, 145)
    $status.Size = New-Object System.Drawing.Size(428, 24)
    $status.ForeColor = [System.Drawing.Color]::FromArgb(203, 213, 225)
    $status.Font = New-Object System.Drawing.Font('Segoe UI', 9, [System.Drawing.FontStyle]::Regular)
    $status.Text = 'Iniciando servicios y verificando el servidor...'

    $progress = New-Object System.Windows.Forms.ProgressBar
    $progress.Location = New-Object System.Drawing.Point(36, 180)
    $progress.Size = New-Object System.Drawing.Size(428, 12)
    $progress.Style = [System.Windows.Forms.ProgressBarStyle]::Marquee
    $progress.MarqueeAnimationSpeed = 24

    $footer = New-Object System.Windows.Forms.Label
    $footer.AutoSize = $true
    $footer.Location = New-Object System.Drawing.Point(36, 218)
    $footer.ForeColor = [System.Drawing.Color]::FromArgb(100, 116, 139)
    $footer.Font = New-Object System.Drawing.Font('Segoe UI', 8, [System.Drawing.FontStyle]::Regular)
    $footer.Text = 'La ventana de acceso se abrira cuando el sistema este listo.'

    $form.Controls.AddRange(@($logo, $title, $subtitle, $status, $progress, $footer))

    $timer = New-Object System.Windows.Forms.Timer
    $timer.Interval = 250

    function Show-StartupError {
        param(
            [System.Windows.Forms.Form] $Window,
            [string] $Message
        )

        $details = @($Message)

        foreach ($logPath in @($stdoutPath, $stderrPath)) {
            if (Test-Path -LiteralPath $logPath) {
                $details += @(Get-Content -LiteralPath $logPath -Tail 12 -ErrorAction SilentlyContinue)
            }
        }

        $text = $details -join [Environment]::NewLine

        if ($text.Length -gt 2200) {
            $text = $text.Substring($text.Length - 2200)
        }

        [System.Windows.Forms.MessageBox]::Show(
            $Window,
            $text,
            'No se pudo iniciar el sistema',
            [System.Windows.Forms.MessageBoxButtons]::OK,
            [System.Windows.Forms.MessageBoxIcon]::Error
        ) | Out-Null
        $Window.Close()
    }

    $shownHandler = {
        $state.StartedAt = Get-Date

        try {
            if (Test-Path -LiteralPath $signalPath) {
                Remove-Item -LiteralPath $signalPath -Force
            }

            $argumentLine = '-NoProfile -ExecutionPolicy Bypass -File "{0}" -StartupSignalPath "{1}"' -f $startupScript, $signalPath
            $state.Process = Start-Process `
                -FilePath $powerShellPath `
                -ArgumentList $argumentLine `
                -WorkingDirectory $projectRoot `
                -PassThru `
                -WindowStyle Hidden `
                -RedirectStandardOutput $stdoutPath `
                -RedirectStandardError $stderrPath
        } catch {
            $state.ErrorMessage = $_.Exception.Message
        }

        $timer.Start()
    }.GetNewClosure()

    $tickHandler = {
        if ($null -ne $state.Process) {
            try {
                $state.Process.Refresh()

                if ($state.Process.HasExited -and $null -eq $state.ExitCode) {
                    $state.ExitCode = $state.Process.ExitCode
                }
            } catch {
                $state.ErrorMessage = $_.Exception.Message
            }
        }

        if (Test-Path -LiteralPath $signalPath) {
            try {
                $startupSignal = Get-Content -LiteralPath $signalPath -Raw | ConvertFrom-Json

                if ($startupSignal.status -eq 'ready') {
                    $state.IsReady = $true
                } elseif ($startupSignal.status -eq 'error') {
                    $state.ErrorMessage = if ([string]::IsNullOrWhiteSpace($startupSignal.message)) {
                        'El iniciador reportó un error sin detalles.'
                    } else {
                        [string] $startupSignal.message
                    }
                }
            } catch {
                $state.ErrorMessage = "No se pudo leer el estado del inicio: $($_.Exception.Message)"
            }
        }

        if ($null -ne $state.ErrorMessage) {
            $timer.Stop()
            Show-StartupError -Window $form -Message $state.ErrorMessage
            return
        }

        if ($state.IsReady) {
            $status.Text = 'Sistema listo. Abriendo el acceso...'
            $progress.Style = [System.Windows.Forms.ProgressBarStyle]::Continuous
            $progress.Value = 100

            if (((Get-Date) - $state.StartedAt).TotalSeconds -ge 5) {
                $timer.Stop()
                $form.Close()
            }

            return
        }

        if ($null -ne $state.ExitCode -and $state.ExitCode -ne 0) {
            $timer.Stop()
            $message = if ($null -ne $state.ExitCode) {
                'El iniciador termino con un error. Revisa la configuracion de PHP, XAMPP y el puerto del sistema.'
            } else {
                'El iniciador terminó con un error.'
            }
            Show-StartupError -Window $form -Message $message
            return
        }

        if ($state.ExitCode -eq 0) {
            $state.IsReady = $true
        }
    }.GetNewClosure()

    $form.add_Shown($shownHandler)
    $timer.add_Tick($tickHandler)
    [System.Windows.Forms.Application]::Run($form)
} catch {
    [System.Windows.Forms.MessageBox]::Show(
        $_.Exception.Message,
        'Error de inicio',
        [System.Windows.Forms.MessageBoxButtons]::OK,
        [System.Windows.Forms.MessageBoxIcon]::Error
    ) | Out-Null
    exit 1
} finally {
    if ($null -ne $icon) {
        $icon.Dispose()
    }

    if ($null -ne $logoImage) {
        $logoImage.Dispose()
    }

    foreach ($logPath in @($stdoutPath, $stderrPath)) {
        if (Test-Path -LiteralPath $logPath) {
            Remove-Item -LiteralPath $logPath -Force -ErrorAction SilentlyContinue
        }
    }

    if (Test-Path -LiteralPath $signalPath) {
        Remove-Item -LiteralPath $signalPath -Force -ErrorAction SilentlyContinue
    }

    if ($ownsSingleInstanceMutex) {
        $singleInstanceMutex.ReleaseMutex()
    }

    $singleInstanceMutex.Dispose()
}
