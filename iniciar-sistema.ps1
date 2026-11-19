param(
    [int] $Port = 8000,
    [string] $StartupSignalPath
)

$ErrorActionPreference = 'Stop'

$script:ProjectRoot = Split-Path -Parent $PSCommandPath
$script:ArtisanPath = Join-Path $script:ProjectRoot 'artisan'
$script:XamppRoot = 'C:\xampp'
$script:StatePath = Join-Path $script:ProjectRoot 'storage\framework\system-processes.json'
$script:LockPath = Join-Path $script:ProjectRoot 'storage\framework\system-processes.lock'
$script:ShutdownAgentPath = Join-Path $script:ProjectRoot 'vigilar-finalizacion.ps1'
$script:BrowserProfilePath = Join-Path $env:LOCALAPPDATA 'SistemaEquiposITIBB\EdgeProfile'
$script:ShutdownRequestPath = Join-Path $script:ProjectRoot 'storage\framework\system-shutdown.request'
$script:ShutdownProcessingPath = Join-Path $script:ProjectRoot 'storage\framework\system-shutdown.request.processing'
$script:ShutdownHeartbeatPath = Join-Path $script:ProjectRoot 'storage\framework\system-shutdown-agent.heartbeat'
$script:AppPort = $Port
$script:BindHost = '0.0.0.0'
$script:ManagedProcesses = @()
$script:StartedProcessIds = @()
$script:ServeProcessId = $null
$script:BrowserProcessId = $null
$script:BrowserWindowHandle = 0
$lock = $null

function Set-StartupSignal {
    param(
        [ValidateSet('ready', 'error')]
        [string] $Status,
        [string] $Message = ''
    )

    if ([string]::IsNullOrWhiteSpace($StartupSignalPath)) {
        return
    }

    $temporaryPath = "$StartupSignalPath.tmp"
    $signal = [pscustomobject]@{
        status  = $Status
        message = $Message
    }

    try {
        $signal | ConvertTo-Json -Compress | Set-Content -LiteralPath $temporaryPath -Encoding UTF8
        Move-Item -LiteralPath $temporaryPath -Destination $StartupSignalPath -Force
    } catch {
        Write-Warning "No se pudo actualizar el estado visual del inicio: $($_.Exception.Message)"
    }
}

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

function Get-UsablePhpExecutable {
    $candidates = @()
    $xamppPhp = Join-Path $script:XamppRoot 'php\php.exe'

    if (Test-Path -LiteralPath $xamppPhp -PathType Leaf) {
        $candidates += $xamppPhp
    }

    $pathPhp = Get-Command 'php.exe' -ErrorAction SilentlyContinue
    if ($null -ne $pathPhp -and $pathPhp.Source -notin $candidates) {
        $candidates += $pathPhp.Source
    }

    foreach ($candidate in $candidates) {
        $versionText = & $candidate -r 'echo PHP_VERSION;' 2>$null

        if ($LASTEXITCODE -eq 0) {
            try {
                if ([version] $versionText -ge [version] '8.3.0') {
                    return $candidate
                }
            } catch {
            }
        }
    }

    throw 'No se encontro PHP 8.3 o superior. Instala una version compatible dentro de C:\xampp o en el PATH.'
}

function Wait-ForPort {
    param(
        [int] $Port,
        [int] $TimeoutSeconds = 60
    )

    $timer = [System.Diagnostics.Stopwatch]::StartNew()

    while ($timer.Elapsed.TotalSeconds -lt $TimeoutSeconds) {
        if (Test-LocalPort -Port $Port) {
            return $true
        }

        Start-Sleep -Milliseconds 500
    }

    return $false
}

function Test-AppHealth {
    try {
        $response = Invoke-WebRequest `
            -Uri "http://127.0.0.1:$($script:AppPort)/up" `
            -UseBasicParsing `
            -TimeoutSec 5

        return $response.StatusCode -eq 200
    } catch {
        return $false
    }
}

function Open-LoginPage {
    if ([string]::IsNullOrWhiteSpace($env:LOCALAPPDATA)) {
        throw 'No se pudo determinar la carpeta local del perfil dedicado de Edge.'
    }

    $loginUrl = "http://127.0.0.1:$($script:AppPort)/login"
    $edgeCandidates = @()

    if ($env:ProgramFiles) {
        $edgeCandidates += Join-Path $env:ProgramFiles 'Microsoft\Edge\Application\msedge.exe'
    }

    if (${env:ProgramFiles(x86)}) {
        $edgeCandidates += Join-Path ${env:ProgramFiles(x86)} 'Microsoft\Edge\Application\msedge.exe'
    }

    $edgePath = $edgeCandidates |
        Where-Object { Test-Path -LiteralPath $_ } |
        Select-Object -First 1

    if ($null -eq $edgePath) {
        throw 'No se encontro Microsoft Edge para abrir la ventana dedicada del sistema.'
    }

    if (-not (Test-Path -LiteralPath $script:BrowserProfilePath)) {
        New-Item -ItemType Directory -Path $script:BrowserProfilePath -Force | Out-Null
    }

    $existingWindows = @(Get-CimInstance Win32_Process -Filter "Name='msedge.exe'" -ErrorAction Stop |
        Where-Object {
            $_.CommandLine -and
            $_.CommandLine.IndexOf($script:BrowserProfilePath, [System.StringComparison]::OrdinalIgnoreCase) -ge 0
        } |
        ForEach-Object {
            $browserProcess = Get-Process -Id $_.ProcessId -ErrorAction SilentlyContinue

            if ($null -ne $browserProcess) {
                $browserProcess.Refresh()

                if ($browserProcess.MainWindowHandle -ne [IntPtr]::Zero) {
                    [pscustomobject]@{
                        ProcessId = [int] $browserProcess.Id
                        Handle    = [int64] $browserProcess.MainWindowHandle
                    }
                }
            }
        })

    if ($existingWindows.Count -gt 0) {
        $script:BrowserProcessId = $existingWindows[0].ProcessId
        $script:BrowserWindowHandle = $existingWindows[0].Handle
    } else {
        $argumentLine = '--user-data-dir="{0}" --no-first-run --no-default-browser-check --new-window "{1}"' -f `
            $script:BrowserProfilePath, $loginUrl
        Start-Process `
            -FilePath $edgePath `
            -ArgumentList $argumentLine `
            -PassThru | Out-Null

        $windowTimer = [System.Diagnostics.Stopwatch]::StartNew()

        while ($windowTimer.Elapsed.TotalSeconds -lt 15) {
            $browserProcesses = Get-CimInstance Win32_Process -Filter "Name='msedge.exe'" -ErrorAction SilentlyContinue |
                Where-Object {
                    $_.CommandLine -and
                    $_.CommandLine.IndexOf($script:BrowserProfilePath, [System.StringComparison]::OrdinalIgnoreCase) -ge 0
                }

            foreach ($browserProcessRecord in $browserProcesses) {
                $browserProcess = Get-Process -Id $browserProcessRecord.ProcessId -ErrorAction SilentlyContinue

                if ($null -eq $browserProcess) {
                    continue
                }

                $browserProcess.Refresh()

                if ($browserProcess.MainWindowHandle -ne [IntPtr]::Zero) {
                    $script:BrowserProcessId = [int] $browserProcess.Id
                    $script:BrowserWindowHandle = [int64] $browserProcess.MainWindowHandle
                    break
                }
            }

            if ($script:BrowserWindowHandle -ne 0) {
                break
            }

            Start-Sleep -Milliseconds 250
        }
    }

    if ($script:BrowserWindowHandle -eq 0) {
        throw 'Microsoft Edge no confirmo la apertura de la ventana dedicada del sistema.'
    }

    Save-ManagedProcessState
}

function Save-ManagedProcessState {
    $state = [pscustomobject]@{
        projectRoot = $script:ProjectRoot
        appPort     = $script:AppPort
        bindHost    = $script:BindHost
        browser     = [pscustomobject]@{
            processId  = $script:BrowserProcessId
            windowHandle = $script:BrowserWindowHandle
            profilePath = $script:BrowserProfilePath
        }
        processes   = @($script:ManagedProcesses)
    }

    $state | ConvertTo-Json -Depth 3 | Set-Content -LiteralPath $script:StatePath -Encoding UTF8
}

function Add-ManagedProcess {
    param(
        [string] $Name,
        [int] $ProcessId
    )

    $script:ManagedProcesses += [pscustomobject]@{
        name = $Name
        pid  = $ProcessId
    }

    Save-ManagedProcessState
}

function Start-HiddenPhpProcess {
    param(
        [string] $Name,
        [string] $Arguments
    )

    $argumentLine = '"{0}" {1}' -f $script:ArtisanPath, $Arguments
    $process = Start-Process `
        -FilePath $script:PhpPath `
        -ArgumentList $argumentLine `
        -WorkingDirectory $script:ProjectRoot `
        -PassThru `
        -WindowStyle Hidden

    $script:StartedProcessIds += $process.Id
    Add-ManagedProcess -Name $Name -ProcessId $process.Id

    return $process
}

function Start-LocalShutdownAgent {
    param([int] $Port)

    if (-not (Test-Path -LiteralPath $script:ShutdownAgentPath)) {
        throw "No se encontro el ayudante local $($script:ShutdownAgentPath)."
    }

    if (Test-Path -LiteralPath $script:ShutdownHeartbeatPath) {
        $heartbeatAge = (Get-Date) - (Get-Item -LiteralPath $script:ShutdownHeartbeatPath).LastWriteTime

        if ($heartbeatAge.TotalSeconds -le 5) {
            return
        }
    }

    foreach ($stalePath in @(
        $script:ShutdownRequestPath,
        $script:ShutdownProcessingPath,
        $script:ShutdownHeartbeatPath
    )) {
        if (Test-Path -LiteralPath $stalePath) {
            Remove-Item -LiteralPath $stalePath -Force
        }
    }

    $powerShellPath = (Get-Command 'powershell.exe' -ErrorAction Stop).Source
    $argumentLine = '-NoProfile -STA -ExecutionPolicy Bypass -File "{0}" -Port {1}' -f $script:ShutdownAgentPath, $Port
    $agent = Start-Process `
        -FilePath $powerShellPath `
        -ArgumentList $argumentLine `
        -WorkingDirectory $script:ProjectRoot `
        -PassThru `
        -WindowStyle Hidden
    $timer = [System.Diagnostics.Stopwatch]::StartNew()

    while ($timer.Elapsed.TotalSeconds -lt 10) {
        $agent.Refresh()

        if ($agent.HasExited) {
            break
        }

        if (Test-Path -LiteralPath $script:ShutdownHeartbeatPath) {
            $heartbeatAge = (Get-Date) - (Get-Item -LiteralPath $script:ShutdownHeartbeatPath).LastWriteTime

            if ($heartbeatAge.TotalSeconds -le 5) {
                return
            }
        }

        Start-Sleep -Milliseconds 250
    }

    $agent.Refresh()

    if (-not $agent.HasExited) {
        Stop-Process -Id $agent.Id -Force -ErrorAction SilentlyContinue
    }

    throw 'El ayudante local de finalizacion no confirmo su inicio.'
}

function Start-XamppBat {
    param([string] $BatName)

    $batPath = Join-Path $script:XamppRoot $BatName

    if (-not (Test-Path -LiteralPath $batPath)) {
        throw "No se encontro $batPath."
    }

    Start-Process `
        -FilePath 'cmd.exe' `
        -ArgumentList '/c', ('"{0}"' -f $batPath) `
        -WorkingDirectory $script:XamppRoot `
        -WindowStyle Hidden | Out-Null
}

function Get-ApachePort {
    $configPath = Join-Path $script:XamppRoot 'apache\conf\httpd.conf'

    if (Test-Path -LiteralPath $configPath) {
        foreach ($line in Get-Content -LiteralPath $configPath) {
            if ($line -match '^\s*Listen\s+(?:\[[^\]]*\]:)?(?:[0-9.]+:)?(\d+)\s*$') {
                return [int] $Matches[1]
            }
        }
    }

    return 80
}

function Get-LanIpAddress {
    try {
        $defaultRoute = Get-NetRoute -DestinationPrefix '0.0.0.0/0' -ErrorAction Stop |
            Sort-Object -Property RouteMetric |
            Select-Object -First 1

        $address = Get-NetIPAddress -AddressFamily IPv4 -InterfaceIndex $defaultRoute.InterfaceIndex -ErrorAction Stop |
            Where-Object {
                $_.IPAddress -notlike '127.*' -and
                $_.IPAddress -notlike '169.254.*'
            } |
            Select-Object -First 1

        if ($null -ne $address) {
            return $address.IPAddress
        }
    } catch {
    }

    return $null
}

function Get-AppUrlHost {
    $envPath = Join-Path $script:ProjectRoot '.env'

    if (-not (Test-Path -LiteralPath $envPath)) {
        return $null
    }

    $match = Select-String -LiteralPath $envPath -Pattern '^APP_URL=(.+)$' | Select-Object -First 1

    if ($null -eq $match) {
        return $null
    }

    $appUrl = $match.Matches[0].Groups[1].Value.Trim()

    try {
        return ([System.Uri] $appUrl).Host
    } catch {
        return $null
    }
}

function Write-SystemUrls {
    Write-Host "  Login local : http://127.0.0.1:$($script:AppPort)/login"

    $lanIp = Get-LanIpAddress

    if ($script:BindHost -ne '0.0.0.0') {
        Write-Host '  Red local   : no disponible hasta reiniciar Windows (socket fantasma en 0.0.0.0).' -ForegroundColor Yellow
    } elseif ($null -ne $lanIp) {
        Write-Host "  Red local   : http://${lanIp}:$($script:AppPort)/login"
    }

    $appUrlHost = Get-AppUrlHost

    if ($null -ne $appUrlHost -and $null -ne $lanIp -and $appUrlHost -ne $lanIp) {
        Write-Host "  Aviso: APP_URL (.env) apunta a $appUrlHost y tu IP actual es $lanIp." -ForegroundColor Yellow
    }
}

try {
    $frameworkDir = Split-Path -Parent $script:StatePath

    if (-not (Test-Path -LiteralPath $frameworkDir)) {
        New-Item -ItemType Directory -Path $frameworkDir -Force | Out-Null
    }

    $lockTimer = [System.Diagnostics.Stopwatch]::StartNew()

    while ($null -eq $lock) {
        try {
            $lock = [System.IO.File]::Open(
                $script:LockPath,
                [System.IO.FileMode]::OpenOrCreate,
                [System.IO.FileAccess]::ReadWrite,
                [System.IO.FileShare]::None
            )
        } catch [System.IO.IOException] {
            if ($lockTimer.Elapsed.TotalSeconds -ge 90) {
                throw 'Otro proceso sigue iniciando o finalizando el sistema. Espera a que termine y vuelve a intentarlo.'
            }

            Start-Sleep -Milliseconds 500
        }
    }

    $script:PhpPath = Get-UsablePhpExecutable

    if (-not (Test-Path -LiteralPath $script:ArtisanPath)) {
        throw "No se encontro el archivo artisan en $script:ProjectRoot."
    }

    if (-not (Test-Path -LiteralPath $script:XamppRoot)) {
        throw "No se encontro XAMPP en $script:XamppRoot."
    }

    if ((Test-LocalPort -Port $script:AppPort) -and (Test-AppHealth)) {
        throw "El sistema ya esta activo en el puerto $($script:AppPort). No se abrio otra ventana."
    }

    if (Test-LocalPort -Port $script:AppPort) {
        $listener = Get-NetTCPConnection -LocalPort $script:AppPort -State Listen -ErrorAction SilentlyContinue |
            Select-Object -First 1

        if ($null -eq $listener) {
            throw "El puerto $($script:AppPort) no responde y no se pudo identificar el proceso que lo ocupa."
        }

        $listenerProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$($listener.OwningProcess)" -ErrorAction SilentlyContinue

        if ($null -eq $listenerProcess) {
            $script:BindHost = '127.0.0.1'
            Write-Warning "El puerto $($script:AppPort) tiene un socket fantasma (PID $($listener.OwningProcess) ya no existe). Se usara 127.0.0.1 para el sistema; reinicia Windows cuando puedas para liberarlo del todo."
        } else {
            $listenerName = if ($listenerProcess.Name) { $listenerProcess.Name } else { 'desconocido' }
            throw "El puerto $($script:AppPort) esta ocupado por el proceso $($listener.OwningProcess) ($listenerName) y no responde. Cierralo y vuelve a intentarlo."
        }
    }

    if (Test-Path -LiteralPath $script:StatePath) {
        Remove-Item -LiteralPath $script:StatePath -Force
    }

    if (-not (Test-LocalPort -Port 3306)) {
        Write-Host 'Iniciando MySQL de XAMPP...'
        Start-XamppBat -BatName 'mysql_start.bat'

        if (-not (Wait-ForPort -Port 3306 -TimeoutSeconds 90)) {
            throw 'MySQL no respondio en el puerto 3306. Revisa el log C:\xampp\mysql\data\mysql_error.log.'
        }
    } else {
        Write-Host 'MySQL ya estaba activo.'
    }

    $apachePort = Get-ApachePort

    if (-not (Test-LocalPort -Port $apachePort)) {
        Write-Host "Iniciando Apache de XAMPP (puerto $apachePort)..."
        Start-XamppBat -BatName 'apache_start.bat'

        if (-not (Wait-ForPort -Port $apachePort -TimeoutSeconds 60)) {
            Write-Warning "Apache no respondio en el puerto $apachePort. El sistema Laravel funciona igual."
        }
    } else {
        Write-Host 'Apache ya estaba activo.'
    }

    Write-Host 'Iniciando Laravel...'
    $serve = Start-HiddenPhpProcess -Name 'serve' -Arguments "serve --host=$($script:BindHost) --port=$($script:AppPort)"
    $script:ServeProcessId = $serve.Id

    $listener = $null
    $listenerTimer = [System.Diagnostics.Stopwatch]::StartNew()

    while ($listenerTimer.Elapsed.TotalSeconds -lt 60) {
        $candidate = Get-NetTCPConnection -LocalPort $script:AppPort -State Listen -ErrorAction SilentlyContinue |
            Where-Object { $_.LocalAddress -eq $script:BindHost } |
            Select-Object -First 1

        if ($null -ne $candidate) {
            $candidateProcess = Get-CimInstance `
                -Class Win32_Process `
                -Filter "ProcessId=$($candidate.OwningProcess)" `
                -ErrorAction SilentlyContinue

            if (
                $null -ne $candidateProcess -and
                [string] $candidateProcess.CommandLine -match '(?i)\s-S\s+'
            ) {
                $listener = $candidate
                break
            }
        }

        Start-Sleep -Milliseconds 500
    }

    if ($null -eq $listener) {
        throw "Laravel no abrio el puerto $($script:AppPort) en $($script:BindHost)."
    }

    Add-ManagedProcess -Name 'http-listener' -ProcessId $listener.OwningProcess

    $healthCheckSucceeded = $false
    $healthCheckTimer = [System.Diagnostics.Stopwatch]::StartNew()

    while ($healthCheckTimer.Elapsed.TotalSeconds -lt 45) {
        if (Test-AppHealth) {
            $healthCheckSucceeded = $true
            break
        }

        Start-Sleep -Seconds 1
    }

    if (-not $healthCheckSucceeded) {
        throw "Laravel no respondio correctamente en http://127.0.0.1:$($script:AppPort)/up."
    }

    Write-Host 'Iniciando cola y tareas programadas...'
    Start-HiddenPhpProcess -Name 'queue' -Arguments 'queue:listen --tries=1 --timeout=0' | Out-Null
    Start-HiddenPhpProcess -Name 'schedule' -Arguments 'schedule:work' | Out-Null

    try {
        Start-LocalShutdownAgent -Port $script:AppPort
        Write-Host 'Ayudante local para finalizar desde el panel iniciado.'
    } catch {
        Write-Warning $_.Exception.Message
    }

    Open-LoginPage
    Set-StartupSignal -Status 'ready'

    Write-Host ''
    Write-Host 'Sistema iniciado.' -ForegroundColor Green
    Write-SystemUrls
    Write-Host '  Cloudflare no se inicio ni se modifico.'
} catch {
    Set-StartupSignal -Status 'error' -Message $_.Exception.Message

    Write-Host ''
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red

    if ($null -ne $script:ServeProcessId) {
        $serveAlive = Get-Process -Id $script:ServeProcessId -ErrorAction SilentlyContinue

        if ($null -ne $serveAlive) {
            $listener = Get-NetTCPConnection -LocalPort $script:AppPort -State Listen -ErrorAction SilentlyContinue |
                Where-Object { $_.LocalAddress -eq $script:BindHost } |
                Select-Object -First 1

            if ($null -ne $listener) {
                Stop-Process -Id $listener.OwningProcess -Force -ErrorAction SilentlyContinue
            }
        }
    }

    foreach ($startedId in $script:StartedProcessIds) {
        Stop-Process -Id $startedId -Force -ErrorAction SilentlyContinue
    }

    if (Test-Path -LiteralPath $script:StatePath) {
        Remove-Item -LiteralPath $script:StatePath -Force -ErrorAction SilentlyContinue
    }

    exit 1
} finally {
    if ($null -ne $lock) {
        $lock.Dispose()
    }
}
