param(
    [switch] $Yes,
    [switch] $NoXampp,
    [switch] $KeepTabs
)

$ErrorActionPreference = 'Stop'

$script:ProjectRoot = Split-Path -Parent $PSCommandPath
$script:ArtisanPath = Join-Path $script:ProjectRoot 'artisan'
$script:XamppRoot = 'C:\xampp'
$script:StatePath = Join-Path $script:ProjectRoot 'storage\framework\system-processes.json'
$script:LockPath = Join-Path $script:ProjectRoot 'storage\framework\system-processes.lock'
$script:AppPort = 8000
$script:PhpPath = ''
$script:XamppFailed = $false
$script:BrowserProcessId = 0
$script:BrowserWindowHandle = [int64] 0
$script:BrowserProfilePath = ''
$lock = $null

function Get-ProcessById {
    param([int] $ProcessId)

    Get-CimInstance Win32_Process -Filter "ProcessId = $ProcessId" -ErrorAction SilentlyContinue
}

function Get-XamppProcesses {
    param([string[]] $Names = @('httpd.exe', 'mysqld.exe'))

    $xamppRoot = [System.IO.Path]::GetFullPath($script:XamppRoot).TrimEnd('\') + '\'

    Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
        Where-Object {
            $_.ExecutablePath -and
            $_.ExecutablePath.StartsWith(
                $xamppRoot,
                [System.StringComparison]::OrdinalIgnoreCase
            ) -and
            $_.Name -in $Names
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

function Get-EnvValue {
    param(
        [string] $Name,
        [string] $DefaultValue
    )

    $envPath = Join-Path $script:ProjectRoot '.env'

    if (-not (Test-Path -LiteralPath $envPath)) {
        return $DefaultValue
    }

    $match = Select-String `
        -LiteralPath $envPath `
        -Pattern ("^{0}=(.*)$" -f [regex]::Escape($Name)) |
        Select-Object -First 1

    if ($null -eq $match) {
        return $DefaultValue
    }

    $value = $match.Matches[0].Groups[1].Value.Trim()

    if ($value.Length -ge 2) {
        $isDoubleQuoted = $value.StartsWith('"') -and $value.EndsWith('"')
        $isSingleQuoted = $value.StartsWith("'") -and $value.EndsWith("'")

        if ($isDoubleQuoted -or $isSingleQuoted) {
            $value = $value.Substring(1, $value.Length - 2)
        }
    }

    return $value
}

function Get-ProcessRecords {
    param([int] $Port)

    $records = @()

    if (Test-Path -LiteralPath $script:StatePath) {
        try {
            $state = Get-Content -LiteralPath $script:StatePath -Raw | ConvertFrom-Json

            if ($state.projectRoot -eq $script:ProjectRoot) {
                foreach ($record in @($state.processes)) {
                    if ($null -eq $record) {
                        continue
                    }

                    $name = [string] $record.name
                    $processId = [int] $record.pid

                    if (
                        $name -in @('http-listener', 'serve-parent', 'serve', 'queue', 'schedule') -and
                        $processId -gt 0
                    ) {
                        $records += [pscustomobject]@{
                            name = $name
                            pid  = $processId
                        }
                    }
                }
            }
        } catch {
            Write-Warning "No se pudo leer el estado guardado: $($_.Exception.Message)"
        }
    }

    $listenerPattern = '(?i)\s-S\s+(?:[^\s:]+:)?{0}\b' -f $Port
    $phpProcesses = @(Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue)

    foreach ($process in $phpProcesses) {
        $commandLine = [string] $process.CommandLine

        if ([string]::IsNullOrWhiteSpace($commandLine)) {
            continue
        }

        if ($commandLine -match $listenerPattern) {
            $records += [pscustomobject]@{
                name = 'http-listener'
                pid  = [int] $process.ProcessId
            }

            continue
        }

        $containsProjectPath = $commandLine.IndexOf(
            $script:ArtisanPath,
            [System.StringComparison]::OrdinalIgnoreCase
        ) -ge 0

        if (-not $containsProjectPath) {
            continue
        }

        if ($commandLine -match '(?i)\sserve(?:\s|$)') {
            $records += [pscustomobject]@{
                name = 'serve'
                pid  = [int] $process.ProcessId
            }
        } elseif ($commandLine -match '(?i)\squeue:listen(?:\s|$)') {
            $records += [pscustomobject]@{
                name = 'queue'
                pid  = [int] $process.ProcessId
            }
        } elseif ($commandLine -match '(?i)\sschedule:work(?:\s|$)') {
            $records += [pscustomobject]@{
                name = 'schedule'
                pid  = [int] $process.ProcessId
            }
        }
    }

    $queueScheduleParentIds = @(
        $records |
            Where-Object { $_.name -in @('queue', 'schedule') } |
            ForEach-Object { [int] $_.pid }
    )

    if ($queueScheduleParentIds.Count -gt 0) {
        $allProcesses = @(Get-CimInstance Win32_Process -ErrorAction SilentlyContinue)
        $parentByPid = @{}

        foreach ($process in $allProcesses) {
            $parentByPid[[int] $process.ProcessId] = [int] $process.ParentProcessId
        }

        foreach ($process in $allProcesses) {
            if ($process.Name -ine 'php.exe') {
                continue
            }

            $commandLine = [string] $process.CommandLine
            $isWorker = $commandLine -match '(?i)\squeue:work(?:\s|$)'
            $isScheduleRunner = $commandLine -match '(?i)\sschedule:run(?:\s|$)'

            if (-not $isWorker -and -not $isScheduleRunner) {
                continue
            }

            $ancestorId = [int] $process.ParentProcessId
            $depth = 0
            $isDescendant = $false

            while ($ancestorId -gt 0 -and $depth -lt 6) {
                if ($queueScheduleParentIds -contains $ancestorId) {
                    $isDescendant = $true
                    break
                }

                if (-not $parentByPid.ContainsKey($ancestorId)) {
                    break
                }

                $ancestorId = [int] $parentByPid[$ancestorId]
                $depth++
            }

            if (-not $isDescendant) {
                continue
            }

            $workerName = if ($isWorker) { 'queue-worker' } else { 'schedule-run' }

            $records += [pscustomobject]@{
                name = $workerName
                pid  = [int] $process.ProcessId
            }
        }
    }

    foreach ($listener in @(Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)) {
        $process = Get-ProcessById -ProcessId ([int] $listener.OwningProcess)

        if ($null -eq $process) {
            continue
        }

        if ([string] $process.CommandLine -match $listenerPattern) {
            $records += [pscustomobject]@{
                name = 'http-listener'
                pid  = [int] $process.ProcessId
            }
        }
    }

    $recordsByPid = @{}

    foreach ($record in $records) {
        if (-not $recordsByPid.ContainsKey($record.pid)) {
            $recordsByPid[$record.pid] = $record
        } elseif ($record.name -eq 'http-listener') {
            $recordsByPid[$record.pid] = $record
        }
    }

    $processOrder = @('http-listener', 'serve', 'queue', 'queue-worker', 'schedule', 'schedule-run')

    $recordsByPid.Values |
        Sort-Object -Property @{ Expression = { [array]::IndexOf($processOrder, $_.name) } }, @{ Expression = { $_.pid } }
}

function Get-KillableProcess {
    param($Record, [int] $Port)

    $process = Get-ProcessById -ProcessId ([int] $Record.pid)

    if ($null -eq $process -or $process.ExecutablePath -ine $script:PhpPath) {
        return $null
    }

    $commandLine = [string] $process.CommandLine
    $listenerPattern = '(?i)\s-S\s+(?:[^\s:]+:)?{0}\b' -f $Port

    if ($Record.name -eq 'http-listener') {
        if ($commandLine -match $listenerPattern) {
            return $process
        }

        return $null
    }

    if ($Record.name -eq 'queue-worker') {
        if ($commandLine -match '(?i)\squeue:work(?:\s|$)') {
            return $process
        }

        return $null
    }

    if ($Record.name -eq 'schedule-run') {
        if ($commandLine -match '(?i)\sschedule:run(?:\s|$)') {
            return $process
        }

        return $null
    }

    $containsProjectPath = $commandLine.IndexOf(
        $script:ArtisanPath,
        [System.StringComparison]::OrdinalIgnoreCase
    ) -ge 0

    if (-not $containsProjectPath) {
        return $null
    }

    if ($Record.name -in @('serve', 'serve-parent')) {
        if ($commandLine -match '(?i)\sserve(?:\s|$)') {
            return $process
        }
    } elseif ($Record.name -eq 'queue') {
        if ($commandLine -match '(?i)\squeue:listen(?:\s|$)') {
            return $process
        }
    } elseif ($Record.name -eq 'schedule') {
        if ($commandLine -match '(?i)\sschedule:work(?:\s|$)') {
            return $process
        }
    }

    return $null
}

function Get-RemainingRecords {
    param($Records, [int] $Port)

    foreach ($record in $Records) {
        $process = Get-KillableProcess -Record $record -Port $Port

        if ($null -ne $process) {
            $process
        }
    }
}

function Stop-LaravelProcesses {
    param([int] $Port)

    $records = @(Get-ProcessRecords -Port $Port)

    if ($records.Count -eq 0) {
        Write-Host 'No se encontraron procesos de Laravel en ejecucion.'
        return
    }

    foreach ($record in $records) {
        $process = Get-KillableProcess -Record $record -Port $Port

        if ($null -eq $process) {
            continue
        }

        $processId = [int] $process.ProcessId

        try {
            Stop-Process -Id $processId -Force -ErrorAction Stop
            Write-Host "Proceso $($record.name) detenido (PID $processId)."
        } catch {
            if ($null -ne (Get-ProcessById -ProcessId $processId)) {
                Write-Warning "No se pudo detener $($record.name) (PID $processId): $($_.Exception.Message)"
            }
        }
    }

    Start-Sleep -Seconds 2

    $remaining = @(Get-RemainingRecords -Records $records -Port $Port)

    if ($remaining.Count -gt 0) {
        foreach ($process in $remaining) {
            try {
                Stop-Process -Id $process.ProcessId -Force -ErrorAction Stop
                Write-Host "Proceso php.exe detenido en el segundo intento (PID $($process.ProcessId))."
            } catch {
                if ($null -ne (Get-ProcessById -ProcessId ([int] $process.ProcessId))) {
                    Write-Warning "Sigue activo el PID $($process.ProcessId): $($_.Exception.Message)"
                }
            }
        }

        Start-Sleep -Seconds 2
        $remaining = @(Get-RemainingRecords -Records $records -Port $Port)
    }

    if ($remaining.Count -gt 0) {
        foreach ($process in $remaining) {
            Write-Warning "Sigue activo: PID $($process.ProcessId) ($([string] $process.CommandLine))"
        }

        throw 'Algunos procesos de Laravel siguen activos. Se conserva el estado para volver a intentarlo.'
    }

    $listenerPattern = '(?i)\s-S\s+(?:[^\s:]+:)?{0}\b' -f $Port
    $portListeners = @(Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)
    $ghostListeners = @()
    $liveListeners = @()

    foreach ($listener in $portListeners) {
        $process = Get-ProcessById -ProcessId ([int] $listener.OwningProcess)

        if ($null -eq $process) {
            $ghostListeners += $listener
            continue
        }

        $liveListeners += $listener
    }

    if ($liveListeners.Count -gt 0) {
        foreach ($listener in $liveListeners) {
            $process = Get-ProcessById -ProcessId ([int] $listener.OwningProcess)
            $processName = if ($process.Name) { $process.Name } else { 'desconocido' }

            Write-Warning "El puerto $Port sigue ocupado por el PID $($listener.OwningProcess) ($processName)."
        }

        throw "El puerto $Port sigue ocupado por un proceso vivo que no se pudo detener."
    }

    if ($ghostListeners.Count -gt 0) {
        Write-Warning "El puerto $Port conserva un socket fantasma (PID inexistente). Reinicia Windows cuando puedas para liberarlo; el iniciador ya sabe convivir con el."
    }
}

function Stop-XamppServices {
    $mysqlProcesses = @(Get-XamppProcesses -Names @('mysqld.exe'))

    if ($mysqlProcesses.Count -gt 0) {
        $mysqladmin = Join-Path $script:XamppRoot 'mysql\bin\mysqladmin.exe'

        if (-not (Test-Path -LiteralPath $mysqladmin)) {
            throw "No se encontro mysqladmin en $mysqladmin."
        }

        $dbUser = Get-EnvValue -Name 'DB_USERNAME' -DefaultValue 'root'

        if ([string]::IsNullOrWhiteSpace($dbUser)) {
            $dbUser = 'root'
        }

        $dbPassword = Get-EnvValue -Name 'DB_PASSWORD' -DefaultValue ''
        $dbHost = Get-EnvValue -Name 'DB_HOST' -DefaultValue '127.0.0.1'

        if ([string]::IsNullOrWhiteSpace($dbHost) -or $dbHost -eq 'localhost') {
            $dbHost = '127.0.0.1'
        }

        $dbPort = Get-EnvValue -Name 'DB_PORT' -DefaultValue '3306'

        if ($dbPort -notmatch '^\d+$') {
            $dbPort = '3306'
        }

        $arguments = @(
            "--user=$dbUser",
            "--host=$dbHost",
            "--port=$dbPort",
            '--connect-timeout=5',
            'shutdown'
        )

        if (-not [string]::IsNullOrEmpty($dbPassword)) {
            $arguments = @(
                "--user=$dbUser",
                "--password=$dbPassword",
                "--host=$dbHost",
                "--port=$dbPort",
                '--connect-timeout=5',
                'shutdown'
            )
        }

        Write-Host 'Deteniendo MySQL de XAMPP de forma segura...'

        $output = & $mysqladmin @arguments 2>&1
        $exitCode = $LASTEXITCODE

        $timer = [System.Diagnostics.Stopwatch]::StartNew()

        while (
            $timer.Elapsed.TotalSeconds -lt 30 -and
            @(Get-XamppProcesses -Names @('mysqld.exe')).Count -gt 0
        ) {
            Start-Sleep -Seconds 1
        }

        if (@(Get-XamppProcesses -Names @('mysqld.exe')).Count -gt 0) {
            if ($exitCode -ne 0) {
                Write-Warning "mysqladmin no pudo apagar MySQL (codigo $exitCode)."

                foreach ($line in @($output)) {
                    Write-Warning ([string] $line)
                }

                Write-Warning 'No se forzo el cierre para no corromper la base de datos.'
            } else {
                Write-Warning 'MySQL sigue activo despues de 30 segundos.'
            }

            $script:XamppFailed = $true
        } elseif ($exitCode -ne 0) {
            Write-Host 'MySQL de XAMPP detenido; mysqladmin reporto una desconexion durante el apagado.'
        } else {
            Write-Host 'MySQL de XAMPP detenido.'
        }
    } else {
        Write-Host 'MySQL de XAMPP ya estaba detenido.'
    }

    $apacheProcesses = @(Get-XamppProcesses -Names @('httpd.exe'))

    if ($apacheProcesses.Count -gt 0) {
        Write-Host 'Deteniendo Apache de XAMPP...'

        foreach ($process in ($apacheProcesses | Sort-Object -Property ProcessId -Descending)) {
            try {
                Stop-Process -Id $process.ProcessId -Force -ErrorAction Stop
            } catch {
                if ($null -ne (Get-ProcessById -ProcessId ([int] $process.ProcessId))) {
                    Write-Warning "No se pudo detener Apache PID $($process.ProcessId): $($_.Exception.Message)"
                    $script:XamppFailed = $true
                }
            }
        }

        $timer = [System.Diagnostics.Stopwatch]::StartNew()

        while (
            $timer.Elapsed.TotalSeconds -lt 15 -and
            @(Get-XamppProcesses -Names @('httpd.exe')).Count -gt 0
        ) {
            Start-Sleep -Seconds 1
        }

        if (@(Get-XamppProcesses -Names @('httpd.exe')).Count -gt 0) {
            Write-Warning 'Apache sigue activo.'
            $script:XamppFailed = $true
        } else {
            Write-Host 'Apache de XAMPP detenido.'
        }
    } else {
        Write-Host 'Apache de XAMPP ya estaba detenido.'
    }
}

function Test-SystemBrowserTabTitle {
    param(
        [string] $Title,
        [string[]] $Patterns
    )

    if ([string]::IsNullOrWhiteSpace($Title)) {
        return $false
    }

    foreach ($pattern in $Patterns) {
        if ($Title -match $pattern) {
            return $true
        }
    }

    return $false
}

function Test-SystemBrowserUrl {
    param(
        [string] $Url,
        [int] $Port
    )

    if ([string]::IsNullOrWhiteSpace($Url) -or $Port -le 0) {
        return $false
    }

    $portPattern = [regex]::Escape([string] $Port)
    $pattern = ':{0}(?:/|$|\?|#)' -f $portPattern

    return $Url -match $pattern
}

function Get-BrowserAddressValue {
    param($Window)

    $editCondition = New-Object System.Windows.Automation.PropertyCondition(
        [System.Windows.Automation.AutomationElement]::ControlTypeProperty,
        [System.Windows.Automation.ControlType]::Edit
    )

    $editElements = @($Window.FindAll([System.Windows.Automation.TreeScope]::Descendants, $editCondition))

    foreach ($editElement in $editElements) {
        try {
            $className = [string] $editElement.Current.ClassName
            $elementName = [string] $editElement.Current.Name

            if ($className -notmatch '(?i)omnibox' -and $elementName -notmatch '(?i)direcci|address') {
                continue
            }

            $valuePattern = $null

            if ($editElement.TryGetCurrentPattern([System.Windows.Automation.ValuePattern]::Pattern, [ref] $valuePattern)) {
                return [string] $valuePattern.Current.Value
            }
        } catch {
        }
    }

    return $null
}

function Test-BrowserTabElementPresent {
    param(
        [IntPtr] $WindowHandle,
        [string] $RuntimeIdentity,
        [string] $TabName
    )

    if (-not [SystemBrowserUiaNativeMethods]::IsWindow($WindowHandle)) {
        return $false
    }

    try {
        $window = [System.Windows.Automation.AutomationElement]::FromHandle($WindowHandle)
        $tabCondition = New-Object System.Windows.Automation.PropertyCondition(
            [System.Windows.Automation.AutomationElement]::ControlTypeProperty,
            [System.Windows.Automation.ControlType]::TabItem
        )
        $tabs = @($window.FindAll([System.Windows.Automation.TreeScope]::Descendants, $tabCondition))

        foreach ($tab in $tabs) {
            try {
                if (
                    $RuntimeIdentity -ne '' -and
                    [string]::Join(',', $tab.GetRuntimeId()) -eq $RuntimeIdentity
                ) {
                    return $true
                }

                if ([string] $tab.Current.Name -eq $TabName) {
                    return $true
                }
            } catch {
            }
        }
    } catch {
        return $true
    }

    return $false
}

function Close-BrowserTabElement {
    param(
        $Tab,
        $Window
    )

    $tabName = [string] $Tab.Current.Name
    $runtimeIdentity = ''

    try {
        $runtimeIdentity = [string]::Join(',', $Tab.GetRuntimeId())
    } catch {
    }

    $windowHandle = [IntPtr] $Window.Current.NativeWindowHandle
    $invokedCloseButton = $false

    try {
        $buttonCondition = New-Object System.Windows.Automation.PropertyCondition(
            [System.Windows.Automation.AutomationElement]::ControlTypeProperty,
            [System.Windows.Automation.ControlType]::Button
        )
        $buttonElements = @($Tab.FindAll([System.Windows.Automation.TreeScope]::Descendants, $buttonCondition))

        foreach ($buttonElement in $buttonElements) {
            try {
                $className = [string] $buttonElement.Current.ClassName
                $elementName = [string] $buttonElement.Current.Name

                if ($className -notmatch '(?i)close' -and $elementName -notmatch '(?i)^cerrar|^close') {
                    continue
                }

                $invokePattern = $null

                if ($buttonElement.TryGetCurrentPattern([System.Windows.Automation.InvokePattern]::Pattern, [ref] $invokePattern)) {
                    $invokePattern.Invoke()
                    $invokedCloseButton = $true
                    break
                }
            } catch {
            }
        }
    } catch {
    }

    if ($invokedCloseButton) {
        for ($attempt = 0; $attempt -lt 15; $attempt++) {
            if (-not (Test-BrowserTabElementPresent `
                -WindowHandle $windowHandle `
                -RuntimeIdentity $runtimeIdentity `
                -TabName $tabName
            )) {
                return $true
            }

            Start-Sleep -Milliseconds 100
        }

        return $false
    }

    try {
        $selectionPattern = $null

        if ($Tab.TryGetCurrentPattern([System.Windows.Automation.SelectionItemPattern]::Pattern, [ref] $selectionPattern)) {
            $selectionPattern.Select()
            Start-Sleep -Milliseconds 200
        }

        if (
            $windowHandle -eq [IntPtr]::Zero -or
            -not [SystemBrowserUiaNativeMethods]::SetForegroundWindow($windowHandle)
        ) {
            return $false
        }

        Start-Sleep -Milliseconds 200

        if ([SystemBrowserUiaNativeMethods]::GetForegroundWindow() -ne $windowHandle) {
            return $false
        }

        [System.Windows.Forms.SendKeys]::SendWait('^w')
        for ($attempt = 0; $attempt -lt 15; $attempt++) {
            if (-not (Test-BrowserTabElementPresent `
                -WindowHandle $windowHandle `
                -RuntimeIdentity $runtimeIdentity `
                -TabName $tabName
            )) {
                return $true
            }

            Start-Sleep -Milliseconds 100
        }

        return $false
    } catch {
        return $false
    }
}

function Close-BrowserSelectedSystemTab {
    param(
        [IntPtr] $WindowHandle,
        [int] $Port,
        [string[]] $TitlePatterns
    )

    if (-not [SystemBrowserUiaNativeMethods]::IsWindow($WindowHandle)) {
        return $true
    }

    try {
        $window = [System.Windows.Automation.AutomationElement]::FromHandle($WindowHandle)
        $windowTitle = [string] $window.Current.Name
        $addressValue = Get-BrowserAddressValue -Window $window
        $isSystemPage = (Test-SystemBrowserUrl -Url $addressValue -Port $Port) -or
            (Test-SystemBrowserTabTitle -Title $windowTitle -Patterns $TitlePatterns)

        if (-not $isSystemPage) {
            return $false
        }

        if (
            -not [SystemBrowserUiaNativeMethods]::SetForegroundWindow($WindowHandle)
        ) {
            return $false
        }

        Start-Sleep -Milliseconds 200

        if ([SystemBrowserUiaNativeMethods]::GetForegroundWindow() -ne $WindowHandle) {
            return $false
        }

        [System.Windows.Forms.SendKeys]::SendWait('^w')

        for ($attempt = 0; $attempt -lt 20; $attempt++) {
            if (-not [SystemBrowserUiaNativeMethods]::IsWindow($WindowHandle)) {
                return $true
            }

            $currentWindow = [System.Windows.Automation.AutomationElement]::FromHandle($WindowHandle)
            $currentTitle = [string] $currentWindow.Current.Name
            $currentAddress = Get-BrowserAddressValue -Window $currentWindow
            $systemTabStillPresent = (Test-SystemBrowserUrl -Url $currentAddress -Port $Port) -or
                (Test-SystemBrowserTabTitle -Title $currentTitle -Patterns $TitlePatterns)

            if (-not $systemTabStillPresent) {
                return $true
            }

            Start-Sleep -Milliseconds 100
        }

        return $false
    } catch {
        return $false
    }
}

function Close-BrowserWindow {
    param([IntPtr] $WindowHandle)

    if (-not [SystemBrowserUiaNativeMethods]::IsWindow($WindowHandle)) {
        return $true
    }

    try {
        $messageResult = [IntPtr]::Zero
        $closeMessageSent = [SystemBrowserUiaNativeMethods]::SendMessageTimeout(
            $WindowHandle,
            0x0010,
            [IntPtr]::Zero,
            [IntPtr]::Zero,
            0x0002,
            1000,
            [ref] $messageResult
        )

        if (-not $closeMessageSent) {
            [SystemBrowserUiaNativeMethods]::PostMessage(
                $WindowHandle,
                0x0010,
                [IntPtr]::Zero,
                [IntPtr]::Zero
            ) | Out-Null
        }

        for ($attempt = 0; $attempt -lt 20; $attempt++) {
            if (-not [SystemBrowserUiaNativeMethods]::IsWindow($WindowHandle)) {
                return $true
            }

            Start-Sleep -Milliseconds 100
        }

        return $false
    } catch {
        return -not [SystemBrowserUiaNativeMethods]::IsWindow($WindowHandle)
    }
}

function Close-SystemBrowserTabs {
    param(
        [int] $Port,
        [int] $BrowserProcessId = 0,
        [int64] $BrowserWindowHandle = 0,
        [string] $BrowserProfilePath = ''
    )

    try {
        Add-Type -AssemblyName UIAutomationClient -ErrorAction Stop
        Add-Type -AssemblyName UIAutomationTypes -ErrorAction Stop
        Add-Type -AssemblyName System.Windows.Forms -ErrorAction Stop

        if (-not ('SystemBrowserUiaNativeMethods' -as [type])) {
            Add-Type @'
using System;
using System.Runtime.InteropServices;

public static class SystemBrowserUiaNativeMethods
{
    [DllImport("user32.dll", SetLastError = true)]
    [return: MarshalAs(UnmanagedType.Bool)]
    public static extern bool PostMessage(IntPtr hWnd, uint message, IntPtr wParam, IntPtr lParam);

    [DllImport("user32.dll", SetLastError = true)]
    [return: MarshalAs(UnmanagedType.Bool)]
    public static extern bool SetForegroundWindow(IntPtr hWnd);

    [DllImport("user32.dll", SetLastError = true)]
    [return: MarshalAs(UnmanagedType.Bool)]
    public static extern bool SendMessageTimeout(
        IntPtr hWnd,
        uint message,
        IntPtr wParam,
        IntPtr lParam,
        uint flags,
        uint timeout,
        out IntPtr result
    );

    [DllImport("user32.dll")]
    public static extern IntPtr GetForegroundWindow();

    [DllImport("user32.dll")]
    [return: MarshalAs(UnmanagedType.Bool)]
    public static extern bool IsWindow(IntPtr hWnd);
}
'@
        }
    } catch {
        Write-Warning "No se pudo preparar la busqueda de pestanas del navegador: $($_.Exception.Message)"
        return $false
    }

    $browserProcessNames = @(
        'msedge', 'chrome', 'brave', 'opera', 'vivaldi', 'chromium',
        'firefox', 'firefoxdeveloperedition', 'librewolf', 'waterfox'
    )

    $appName = Get-EnvValue -Name 'APP_NAME' -DefaultValue 'Sistema de Equipos'

    if ([string]::IsNullOrWhiteSpace($appName)) {
        $appName = 'Sistema de Equipos'
    }

    $titlePatterns = @(
        '(?i)' + [regex]::Escape($appName),
        '(?i)Sistema de Equipos',
        '(?i)ITIBB',
        '(?i)Portal del estudiante',
        '(?i)Mi seguimiento',
        '(?i)Ficha del activo',
        '(?i)Ficha del equipo',
        '(?i)Acceso estudiantil'
    )

    $ambiguousTitlePatterns = @(
        '(?i)^Resumen\s',
        '(?i)^Antecedente de usuario\s*\|',
        '(?i)^Notificaciones\b'
    )

    $tabCondition = New-Object System.Windows.Automation.PropertyCondition(
        [System.Windows.Automation.AutomationElement]::ControlTypeProperty,
        [System.Windows.Automation.ControlType]::TabItem
    )

    $foundSystemTab = $false
    $allSystemTabsClosed = $true

    if (
        $BrowserProcessId -gt 0 -and
        $BrowserWindowHandle -ne 0 -and
        -not [string]::IsNullOrWhiteSpace($BrowserProfilePath)
    ) {
        $browserProcessRecord = Get-ProcessById -ProcessId $BrowserProcessId
        $browserProcess = Get-Process -Id $BrowserProcessId -ErrorAction SilentlyContinue
        $ownsDedicatedWindow = $false

        if (
            $null -ne $browserProcessRecord -and
            $browserProcessRecord.Name -eq 'msedge.exe' -and
            [string] $browserProcessRecord.CommandLine -and
            $browserProcessRecord.CommandLine.IndexOf(
                $BrowserProfilePath,
                [System.StringComparison]::OrdinalIgnoreCase
            ) -ge 0 -and
            $null -ne $browserProcess
        ) {
            $browserProcess.Refresh()
            $ownsDedicatedWindow = [int64] $browserProcess.MainWindowHandle -eq $BrowserWindowHandle
        }

        if ($ownsDedicatedWindow) {
            $foundSystemTab = $true

            if (Close-BrowserWindow -WindowHandle ([IntPtr] $BrowserWindowHandle)) {
                Write-Host 'Ventana dedicada de Edge del sistema cerrada.'
            } else {
                Write-Warning 'La ventana dedicada de Edge sigue abierta.'
                $allSystemTabsClosed = $false
            }
        }
    }

    foreach ($processName in $browserProcessNames) {
        foreach ($browserProcess in @(Get-Process -Name $processName -ErrorAction SilentlyContinue)) {
            $windowTitle = [string] $browserProcess.MainWindowTitle
            $windowHandle = [IntPtr] $browserProcess.MainWindowHandle

            if (
                $windowHandle -eq [IntPtr]::Zero -or
                -not (Test-SystemBrowserTabTitle -Title $windowTitle -Patterns $titlePatterns)
            ) {
                continue
            }

            $foundSystemTab = $true

            if (Close-BrowserSelectedSystemTab `
                -WindowHandle $windowHandle `
                -Port $Port `
                -TitlePatterns $titlePatterns
            ) {
                Write-Host "Pestana del sistema cerrada: $windowTitle"
            } else {
                Write-Warning "Edge no confirmo el cierre de la pestana del sistema: $windowTitle"
                $allSystemTabsClosed = $false
            }
        }
    }

    $rootElement = [System.Windows.Automation.AutomationElement]::RootElement
    $rootChildren = @($rootElement.FindAll(
        [System.Windows.Automation.TreeScope]::Children,
        [System.Windows.Automation.Condition]::TrueCondition
    ))

    foreach ($windowElement in $rootChildren) {
        try {
            $processId = [int] $windowElement.Current.ProcessId
        } catch {
            continue
        }

        $process = Get-Process -Id $processId -ErrorAction SilentlyContinue

        if ($null -eq $process -or $browserProcessNames -notcontains $process.ProcessName.ToLowerInvariant()) {
            continue
        }

        try {
            $windowTitle = [string] $windowElement.Current.Name
        } catch {
            continue
        }

        try {
            $tabElements = @($windowElement.FindAll([System.Windows.Automation.TreeScope]::Descendants, $tabCondition))
        } catch {
            if (Test-SystemBrowserTabTitle -Title $windowTitle -Patterns $titlePatterns) {
                Write-Warning "No se pudo inspeccionar la ventana del sistema: $windowTitle"
                $allSystemTabsClosed = $false
            }

            continue
        }

        if ($tabElements.Count -eq 0) {
            $addressValue = Get-BrowserAddressValue -Window $windowElement
            $isSystemPage = (Test-SystemBrowserUrl -Url $addressValue -Port $Port) -or
                (Test-SystemBrowserTabTitle -Title $windowTitle -Patterns $titlePatterns)

            if ($isSystemPage) {
                $foundSystemTab = $true
                $windowHandle = [IntPtr] $windowElement.Current.NativeWindowHandle

                if (Close-BrowserSelectedSystemTab `
                    -WindowHandle $windowHandle `
                    -Port $Port `
                    -TitlePatterns $titlePatterns
                ) {
                    Write-Host "Pestana del sistema cerrada: $windowTitle"
                } else {
                    Write-Warning "No se pudo cerrar la pestana del sistema: $windowTitle"
                    $allSystemTabsClosed = $false
                }
            }

            continue
        }

        $systemTabElements = @()
        $ambiguousTabElements = @()
        $selectedTabElement = $null

        foreach ($tabElement in $tabElements) {
            try {
                $tabName = [string] $tabElement.Current.Name
                $selectionPattern = $null

                if ($tabElement.TryGetCurrentPattern([System.Windows.Automation.SelectionItemPattern]::Pattern, [ref] $selectionPattern)) {
                    if ($selectionPattern.Current.IsSelected) {
                        $selectedTabElement = $tabElement
                    }
                }

                if (Test-SystemBrowserTabTitle -Title $tabName -Patterns $titlePatterns) {
                    $systemTabElements += $tabElement
                } elseif (Test-SystemBrowserTabTitle -Title $tabName -Patterns $ambiguousTitlePatterns) {
                    $ambiguousTabElements += $tabElement
                }
            } catch {
            }
        }

        foreach ($tabElement in $ambiguousTabElements) {
            try {
                $selectionPattern = $null

                if (-not $tabElement.TryGetCurrentPattern([System.Windows.Automation.SelectionItemPattern]::Pattern, [ref] $selectionPattern)) {
                    continue
                }

                $selectionPattern.Select()
                Start-Sleep -Milliseconds 250

                $addressValue = Get-BrowserAddressValue -Window $windowElement

                if (Test-SystemBrowserUrl -Url $addressValue -Port $Port) {
                    $systemTabElements += $tabElement
                }
            } catch {
            }
        }

        if ($systemTabElements.Count -eq 0) {
            if ($null -ne $selectedTabElement) {
                try {
                    $selectionPattern = $null

                    if ($selectedTabElement.TryGetCurrentPattern([System.Windows.Automation.SelectionItemPattern]::Pattern, [ref] $selectionPattern)) {
                        $selectionPattern.Select()
                    }
                } catch {
                }
            }

            continue
        }

        $foundSystemTab = $true

        if ($systemTabElements.Count -eq $tabElements.Count) {
            $windowTitle = [string] $windowElement.Current.Name
            $windowHandle = [IntPtr] $windowElement.Current.NativeWindowHandle

            if (Close-BrowserWindow -WindowHandle $windowHandle) {
                Write-Host "Ventana del sistema cerrada: $windowTitle"
            } else {
                Write-Warning "No se pudo cerrar la ventana del sistema: $windowTitle"
                $allSystemTabsClosed = $false
            }

            continue
        }

        foreach ($tabElement in $systemTabElements) {
            $tabName = ''

            try {
                $tabName = [string] $tabElement.Current.Name
            } catch {
            }

            if (Close-BrowserTabElement -Tab $tabElement -Window $windowElement) {
                Write-Host "Pestana del sistema cerrada: $tabName"
            } else {
                Write-Warning "No se pudo cerrar la pestana del sistema: $tabName"
                $allSystemTabsClosed = $false
            }
        }

        if ($null -ne $selectedTabElement) {
            try {
                $selectionPattern = $null

                if ($selectedTabElement.TryGetCurrentPattern([System.Windows.Automation.SelectionItemPattern]::Pattern, [ref] $selectionPattern)) {
                    $selectionPattern.Select()
                }
            } catch {
            }
        }
    }

    if (-not $foundSystemTab) {
        Write-Host 'No se encontraron pestanas del sistema abiertas.'
    }

    return $allSystemTabsClosed
}

function Close-SystemBrowserTabsAndWait {
    param(
        [int] $Port,
        [int] $BrowserProcessId = 0,
        [int64] $BrowserWindowHandle = 0,
        [string] $BrowserProfilePath = '',
        [int] $TimeoutSeconds = 20
    )

    $timer = [System.Diagnostics.Stopwatch]::StartNew()

    do {
        if (
            Close-SystemBrowserTabs `
                -Port $Port `
                -BrowserProcessId $BrowserProcessId `
                -BrowserWindowHandle $BrowserWindowHandle `
                -BrowserProfilePath $BrowserProfilePath
        ) {
            return $true
        }

        Start-Sleep -Milliseconds 500
    } while ($timer.Elapsed.TotalSeconds -lt $TimeoutSeconds)

    return $false
}

try {
    try {
        $lock = [System.IO.File]::Open(
            $script:LockPath,
            [System.IO.FileMode]::OpenOrCreate,
            [System.IO.FileAccess]::ReadWrite,
            [System.IO.FileShare]::None
        )
    } catch {
        throw "No se pudo bloquear el estado del sistema. Puede que se este iniciando o finalizando. Detalle: $($_.Exception.Message)"
    }

    $script:PhpPath = Get-UsablePhpExecutable

    if (Test-Path -LiteralPath $script:StatePath) {
        try {
            $state = Get-Content -LiteralPath $script:StatePath -Raw | ConvertFrom-Json

            if ($state.appPort -and [int] $state.appPort -gt 0) {
                $script:AppPort = [int] $state.appPort
            }

            if ($state.browser) {
                $script:BrowserProcessId = [int] $state.browser.processId
                $script:BrowserWindowHandle = [int64] $state.browser.windowHandle
                $script:BrowserProfilePath = [string] $state.browser.profilePath
            }
        } catch {
        }
    }

    if (-not $Yes) {
        $tabNotice = if ($KeepTabs) {
            ''
        } else {
            ' Tambien se cerraran las pestanas del sistema.'
        }

        $prompt = if ($NoXampp) {
            'Se detendra Laravel, la cola y las tareas programadas.' + $tabNotice + ' Continuar? (S/N)'
        } else {
            'Se detendra Laravel, la cola, las tareas y Apache/MySQL de XAMPP.' + $tabNotice + ' Continuar? (S/N)'
        }

        $confirmation = Read-Host $prompt

        if ($confirmation -notmatch '^(s|si|sí)$') {
            Write-Host 'No se realizaron cambios.'
            exit 0
        }
    }

    if (-not $KeepTabs) {
        Write-Host 'Cerrando la ventana o pestanas del sistema...'

        if (-not (Close-SystemBrowserTabsAndWait `
            -Port $script:AppPort `
            -BrowserProcessId $script:BrowserProcessId `
            -BrowserWindowHandle $script:BrowserWindowHandle `
            -BrowserProfilePath $script:BrowserProfilePath
        )) {
            throw 'No se pudo confirmar el cierre de la ventana del sistema. Laravel y XAMPP siguen activos para que puedas volver a intentarlo.'
        }

        Write-Host 'Cierre de la ventana del sistema confirmado.'
    } else {
        Write-Host 'Las pestanas del navegador no se tocaron (-KeepTabs).'
    }

    Stop-LaravelProcesses -Port $script:AppPort

    if (Test-Path -LiteralPath $script:StatePath) {
        Remove-Item -LiteralPath $script:StatePath -Force
        Write-Host 'Estado de procesos eliminado.'
    }

    if ($NoXampp) {
        Write-Host 'Apache/MySQL de XAMPP no se tocaron (-NoXampp).'
    } else {
        Stop-XamppServices
    }

    if ($script:XamppFailed) {
        Write-Host ''
        Write-Host 'Laravel quedo detenido, pero XAMPP no se apago del todo.' -ForegroundColor Yellow
        Write-Host 'Puedes volver a iniciar el sistema; el iniciador detecta que MySQL/Apache sigan activos.' -ForegroundColor Yellow
        exit 1
    }

    Write-Host ''
    Write-Host 'Sistema finalizado. Windows sigue encendida; Cloudflare no se modifico.' -ForegroundColor Green
} catch {
    Write-Host ''
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
} finally {
    if ($null -ne $lock) {
        $lock.Dispose()
    }
}
