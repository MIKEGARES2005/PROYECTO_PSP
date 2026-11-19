param(
    [switch] $PlanOnly,
    [string] $XamppInstallerPath
)

$ErrorActionPreference = 'Stop'

$script:ProjectRepository = 'MIKEGARES2005/PROYECTO_PSP'
$script:ProjectBranch = 'main'
$script:ProjectRoot = 'C:\proyecto psp\sistema_equipos'
$script:XamppRoot = 'C:\xampp'
$script:XamppVersion = '8.5.12'
$script:XamppInstallerUrl = 'https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/8.5.12/xampp-windows-x64-8.5.12-0-VS17-installer.exe/download'
$script:XamppInstallerSha256 = '54c4c492def0734fa156843b571036bddbb7020f89e9881ba9d8c02db124263c'
$script:VcRedistUrl = 'https://aka.ms/vs/17/release/vc_redist.x64.exe'
$script:DatabaseName = 'sistema_equipos'
$script:DatabaseUsername = 'sistema_equipos_app'
$script:DatabasePort = 3306
$script:DatabaseBackupPath = 'C:\proyecto psp\backup\sistema_equipos_backup.sql'
$script:ApplicationPort = 8000
$script:TemporaryRoot = Join-Path $env:TEMP ('sistema-equipos-' + [guid]::NewGuid().ToString('N'))
$script:StartedMySqlForSetup = $false
$script:MySqlStopAttempted = $false
$script:MySqlPath = Join-Path $script:XamppRoot 'mysql\bin\mysql.exe'
$script:MySqlAdminPath = Join-Path $script:XamppRoot 'mysql\bin\mysqladmin.exe'
$script:MySqlStartPath = Join-Path $script:XamppRoot 'mysql_start.bat'
$script:PhpPath = Join-Path $script:XamppRoot 'php\php.exe'
$script:ComposerPath = Join-Path $script:XamppRoot 'php\composer.bat'
$script:NodePath = ''
$script:CloudflaredPath = 'C:\cloudflared\cloudflared.exe'

function Write-InstallerStep {
    param([string] $Message)

    Write-Host "`n==> $Message" -ForegroundColor Cyan
}

function Invoke-CheckedCommand {
    param(
        [string] $FilePath,
        [string[]] $Arguments,
        [string] $WorkingDirectory = $script:ProjectRoot
    )

    Push-Location -LiteralPath $WorkingDirectory
    try {
        & $FilePath @Arguments
        $exitCode = $LASTEXITCODE
    } finally {
        Pop-Location
    }

    if ($exitCode -ne 0) {
        throw "El comando '$FilePath' termino con codigo $exitCode."
    }
}

function Get-PhpVersion {
    param([string] $PhpExecutable)

    if (-not (Test-Path -LiteralPath $PhpExecutable -PathType Leaf)) {
        return $null
    }

    $versionText = & $PhpExecutable -r 'echo PHP_VERSION;' 2>$null
    if ($LASTEXITCODE -ne 0) {
        return $null
    }

    try {
        return [version] $versionText
    } catch {
        return $null
    }
}

function Get-LanIPv4Address {
    $configurations = @(Get-NetIPConfiguration -ErrorAction SilentlyContinue |
        Where-Object {
            $_.NetAdapter.Status -eq 'Up' -and
            $_.IPv4DefaultGateway -and
            $_.IPv4Address
        })

    foreach ($configuration in $configurations) {
        $address = [string] $configuration.IPv4Address[0].IPAddress
        if ($address -match '^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)') {
            return $address
        }
    }

    if ($configurations.Count -gt 0) {
        return [string] $configurations[0].IPv4Address[0].IPAddress
    }

    return '127.0.0.1'
}

function Set-DotEnvValue {
    param(
        [string] $Path,
        [string] $Key,
        [string] $Value
    )

    $lines = [System.Collections.Generic.List[string]]::new()
    foreach ($line in [System.IO.File]::ReadAllLines($Path)) {
        $lines.Add($line)
    }

    $pattern = '^\s*#?\s*' + [regex]::Escape($Key) + '='
    $replacement = $Key + '=' + $Value
    $found = $false

    for ($index = 0; $index -lt $lines.Count; $index++) {
        if ($lines[$index] -match $pattern) {
            $lines[$index] = $replacement
            $found = $true
        }
    }

    if (-not $found) {
        $lines.Add($replacement)
    }

    [System.IO.File]::WriteAllLines($Path, $lines, [System.Text.UTF8Encoding]::new($false))
}

function Protect-EnvironmentFile {
    param([string] $Path)

    $acl = Get-Acl -LiteralPath $Path
    $acl.SetAccessRuleProtection($true, $false)
    $acl.SetOwner([System.Security.Principal.WindowsIdentity]::GetCurrent().User)

    $allowedSids = @(
        [System.Security.Principal.WindowsIdentity]::GetCurrent().User,
        [System.Security.Principal.SecurityIdentifier]::new('S-1-5-18'),
        [System.Security.Principal.SecurityIdentifier]::new('S-1-5-32-544')
    )

    foreach ($sid in $allowedSids) {
        $rule = [System.Security.AccessControl.FileSystemAccessRule]::new(
            $sid,
            [System.Security.AccessControl.FileSystemRights]::FullControl,
            [System.Security.AccessControl.AccessControlType]::Allow
        )
        $acl.AddAccessRule($rule)
    }

    Set-Acl -LiteralPath $Path -AclObject $acl
}

function Get-RandomDatabasePassword {
    return [guid]::NewGuid().ToString('N') + [guid]::NewGuid().ToString('N')
}

function Get-VcRedistVersion {
    $runtime = Get-ItemProperty `
        -LiteralPath 'HKLM:\SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64' `
        -ErrorAction SilentlyContinue

    if ($null -eq $runtime -or $runtime.Installed -ne 1 -or [string]::IsNullOrWhiteSpace($runtime.Version)) {
        return $null
    }

    try {
        return [version] ([string] $runtime.Version).TrimStart('v')
    } catch {
        return $null
    }
}

function Install-VcRedist {
    $installedVersion = Get-VcRedistVersion
    if ($null -ne $installedVersion -and $installedVersion -ge [version] '14.30.30704.0') {
        Write-Host "Microsoft Visual C++ Redistributable existente detectado ($installedVersion); se conservara."
        return
    }

    Write-InstallerStep 'Instalando el runtime oficial de Microsoft requerido por XAMPP'
    $installerPath = Join-Path $script:TemporaryRoot 'vc_redist.x64.exe'
    Invoke-WebRequest -UseBasicParsing -Uri $script:VcRedistUrl -OutFile $installerPath

    $signature = Get-AuthenticodeSignature -FilePath $installerPath
    if ($signature.Status -ne 'Valid' -or $signature.SignerCertificate.Subject -notmatch 'Microsoft Corporation') {
        throw 'El instalador de Visual C++ no tiene una firma digital valida de Microsoft.'
    }

    $process = Start-Process -FilePath $installerPath `
        -ArgumentList @('/install', '/quiet', '/norestart') `
        -Wait -PassThru

    if ($process.ExitCode -notin @(0, 3010) -and $null -eq (Get-VcRedistVersion)) {
        throw "La instalacion de Visual C++ termino con codigo $($process.ExitCode)."
    }

    $installedVersion = Get-VcRedistVersion
    if ($null -eq $installedVersion -or $installedVersion -lt [version] '14.30.30704.0') {
        throw 'No se pudo confirmar la instalacion de Microsoft Visual C++ Redistributable.'
    }
}

function Test-NodeVersionSupported {
    param([version] $Version)

    return (
        ($Version -ge [version] '20.19.0' -and $Version -lt [version] '21.0.0') -or
        $Version -ge [version] '22.12.0'
    )
}

function Read-AdministratorCredentials {
    do {
        $username = (Read-Host 'Nombre de usuario del administrador (3-30 caracteres: letras, numeros, punto, guion o guion bajo)').Trim()
        if ($username -notmatch '^[A-Za-z0-9._-]{3,30}$') {
            Write-Host 'El nombre no cumple el formato solicitado.' -ForegroundColor Yellow
        }
    } while ($username -notmatch '^[A-Za-z0-9._-]{3,30}$')

    while ($true) {
        $firstPassword = Read-Host 'Contrasena nueva del administrador (minimo 14 caracteres)' -AsSecureString
        $secondPassword = Read-Host 'Repite la contrasena' -AsSecureString
        $firstPlainText = ConvertFrom-SecureStringToPlainText -SecureString $firstPassword
        $secondPlainText = ConvertFrom-SecureStringToPlainText -SecureString $secondPassword

        if ($firstPlainText.Length -ge 14 -and $firstPlainText -ceq $secondPlainText) {
            return @{
                Username = $username
                Password = $firstPlainText
            }
        }

        Write-Host 'Las contrasenas deben coincidir y tener al menos 14 caracteres.' -ForegroundColor Yellow
    }
}

function ConvertFrom-SecureStringToPlainText {
    param([System.Security.SecureString] $SecureString)

    $pointer = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecureString)
    try {
        return [System.Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    } finally {
        [System.Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
}

function Install-Xampp {
    $existingPhpVersion = Get-PhpVersion -PhpExecutable $script:PhpPath
    if ($null -ne $existingPhpVersion) {
        if ($existingPhpVersion -lt [version] '8.3.0') {
            throw "Se encontro XAMPP con PHP $existingPhpVersion en $script:XamppRoot. No se sobrescribio; instala una version compatible de XAMPP manualmente."
        }

        Write-Host "XAMPP existente detectado (PHP $existingPhpVersion); se conservara."
        return
    }

    if (Test-Path -LiteralPath $script:XamppRoot) {
        throw "Ya existe $script:XamppRoot, pero no contiene un PHP compatible. El instalador no modificara esa carpeta."
    }

    if ([string]::IsNullOrWhiteSpace($XamppInstallerPath)) {
        Write-InstallerStep "Descargando XAMPP $script:XamppVersion con PHP y MariaDB"
        $installerPath = Join-Path $script:TemporaryRoot 'xampp-installer.exe'
        Invoke-WebRequest -UseBasicParsing -Uri $script:XamppInstallerUrl -OutFile $installerPath
    } else {
        $installerPath = [System.IO.Path]::GetFullPath($XamppInstallerPath)
        if (-not (Test-Path -LiteralPath $installerPath -PathType Leaf)) {
            throw "No se encontro el instalador local de XAMPP en $installerPath."
        }

        Write-InstallerStep "Usando el instalador local de XAMPP: $installerPath"
    }

    $signatureBytes = [System.IO.File]::ReadAllBytes($installerPath)
    if ($signatureBytes.Length -lt 2 -or $signatureBytes[0] -ne 0x4D -or $signatureBytes[1] -ne 0x5A) {
        throw 'La descarga de XAMPP no es un ejecutable valido. SourceForge devolvio una pagina HTML; descarga el instalador desde el navegador y colocalo junto al archivo instalar-sistema.bat.'
    }

    $actualHash = (Get-FileHash -LiteralPath $installerPath -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actualHash -ne $script:XamppInstallerSha256) {
        throw "La verificacion SHA-256 del instalador de XAMPP fallo. Se esperaba $script:XamppInstallerSha256 y se obtuvo $actualHash."
    }

    $process = Start-Process -FilePath $installerPath `
        -ArgumentList @('--mode', 'unattended', '--unattendedmodeui', 'none', '--prefix', $script:XamppRoot) `
        -Wait -PassThru

    if ($process.ExitCode -ne 0) {
        throw "El instalador de XAMPP termino con codigo $($process.ExitCode)."
    }

    $installedVersion = Get-PhpVersion -PhpExecutable $script:PhpPath
    if ($null -eq $installedVersion -or $installedVersion -lt [version] '8.3.0') {
        throw 'XAMPP termino, pero no se encontro PHP 8.3 o superior en C:\xampp\php\php.exe.'
    }
}

function Install-NodeLts {
    $existingNode = Get-Command 'node.exe' -ErrorAction SilentlyContinue
    if ($null -ne $existingNode) {
        $script:NodePath = $existingNode.Source
    } else {
        $standardNodePath = Join-Path $env:ProgramFiles 'nodejs\node.exe'
        if (Test-Path -LiteralPath $standardNodePath -PathType Leaf) {
            $script:NodePath = $standardNodePath
        }
    }

    if (-not [string]::IsNullOrWhiteSpace($script:NodePath)) {
        $nodeVersionText = & $script:NodePath --version
        $nodeVersion = [version] ($nodeVersionText.TrimStart('v'))
        if (-not (Test-NodeVersionSupported -Version $nodeVersion)) {
            throw "Node.js $nodeVersion ya esta instalado y no satisface Vite (se requiere 20.19 o posterior en la rama 20, o 22.12 o posterior). Actualizalo y vuelve a ejecutar; no se modifico la instalacion existente."
        }

        Write-Host "Node.js existente detectado ($nodeVersion); se conservara."
        return
    }

    Write-InstallerStep 'Descargando e instalando Node.js LTS desde nodejs.org'
    $releases = Invoke-RestMethod -Uri 'https://nodejs.org/dist/index.json'
    $release = $releases |
        Where-Object { $_.lts -and $_.version -match '^v\d+\.\d+\.\d+$' } |
        Select-Object -First 1

    if ($null -eq $release) {
        throw 'No se pudo identificar una version estable LTS de Node.js.'
    }

    $nodeFileName = 'node-' + $release.version + '-x64.msi'
    $nodeBaseUrl = 'https://nodejs.org/dist/' + $release.version
    $checksumFile = Join-Path $script:TemporaryRoot 'node-shasums.txt'
    $installerPath = Join-Path $script:TemporaryRoot $nodeFileName
    Invoke-WebRequest -UseBasicParsing -Uri ($nodeBaseUrl + '/SHASUMS256.txt') -OutFile $checksumFile

    $checksumLine = Select-String -LiteralPath $checksumFile -Pattern ('^([a-fA-F0-9]{64})\s+\*?' + [regex]::Escape($nodeFileName) + '$') |
        Select-Object -First 1
    if ($null -eq $checksumLine) {
        throw "Node.js no publico un SHA-256 para $nodeFileName."
    }

    Invoke-WebRequest -UseBasicParsing -Uri ($nodeBaseUrl + '/' + $nodeFileName) -OutFile $installerPath
    $expectedHash = $checksumLine.Matches[0].Groups[1].Value.ToLowerInvariant()
    $actualHash = (Get-FileHash -LiteralPath $installerPath -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actualHash -ne $expectedHash) {
        throw "La verificacion SHA-256 del instalador de Node.js fallo."
    }

    $process = Start-Process -FilePath 'msiexec.exe' `
        -ArgumentList @('/i', "`"$installerPath`"", '/qn', '/norestart') `
        -Wait -PassThru

    if ($process.ExitCode -notin @(0, 3010)) {
        throw "La instalacion de Node.js termino con codigo $($process.ExitCode)."
    }

    $script:NodePath = Join-Path $env:ProgramFiles 'nodejs\node.exe'
    if (-not (Test-Path -LiteralPath $script:NodePath -PathType Leaf)) {
        throw 'Node.js finalizo, pero no se encontro node.exe en la carpeta de instalacion esperada.'
    }
}

function Install-Composer {
    $existingComposer = Get-Command 'composer.bat', 'composer.exe', 'composer' -ErrorAction SilentlyContinue |
        Select-Object -First 1
    if ($null -ne $existingComposer) {
        $script:ComposerPath = $existingComposer.Source
        Write-Host "Composer existente detectado en $($script:ComposerPath); se conservara."
        Invoke-CheckedCommand -FilePath $script:ComposerPath -Arguments @('--version') -WorkingDirectory $script:XamppRoot
        return
    }

    if (Test-Path -LiteralPath (Join-Path $script:XamppRoot 'php\composer.phar')) {
        $composerBatch = Join-Path $script:XamppRoot 'php\composer.bat'
        if (-not (Test-Path -LiteralPath $composerBatch)) {
            [System.IO.File]::WriteAllLines(
                $composerBatch,
                @('@echo off', '"%~dp0php.exe" "%~dp0composer.phar" %*'),
                [System.Text.Encoding]::ASCII
            )
        }

        $script:ComposerPath = $composerBatch
        Invoke-CheckedCommand -FilePath $script:ComposerPath -Arguments @('--version') -WorkingDirectory $script:XamppRoot
        return
    }

    if (Test-Path -LiteralPath (Join-Path $script:XamppRoot 'php\composer.bat')) {
        throw 'Ya existe C:\xampp\php\composer.bat pero falta composer.phar. No se sobrescribio el lanzador existente.'
    }

    Write-InstallerStep 'Instalando Composer y validando su firma oficial'
    $setupPath = Join-Path $script:TemporaryRoot 'composer-setup.php'
    $signaturePath = Join-Path $script:TemporaryRoot 'composer-setup.sig'
    Invoke-WebRequest -UseBasicParsing -Uri 'https://getcomposer.org/installer' -OutFile $setupPath
    Invoke-WebRequest -UseBasicParsing -Uri 'https://composer.github.io/installer.sig' -OutFile $signaturePath
    $expectedSignature = (Get-Content -LiteralPath $signaturePath -Raw -Encoding ASCII).Trim().ToLowerInvariant()
    $actualSignature = (Get-FileHash -LiteralPath $setupPath -Algorithm SHA384).Hash.ToLowerInvariant()

    if ($actualSignature -ne $expectedSignature) {
        throw 'La firma del instalador de Composer no coincide con la publicada oficialmente.'
    }

    $composerInstallDirectory = Join-Path $script:XamppRoot 'php'
    if (-not (Test-Path -LiteralPath $composerInstallDirectory -PathType Container)) {
        throw "No se encontro la carpeta de PHP de XAMPP: $composerInstallDirectory."
    }

    Invoke-CheckedCommand -FilePath $script:PhpPath `
        -Arguments @($setupPath, '--install-dir', $composerInstallDirectory, '--filename', 'composer.phar') `
        -WorkingDirectory $script:TemporaryRoot

    $composerBatch = Join-Path $script:XamppRoot 'php\composer.bat'
    [System.IO.File]::WriteAllLines(
        $composerBatch,
        @('@echo off', '"%~dp0php.exe" "%~dp0composer.phar" %*'),
        [System.Text.Encoding]::ASCII
    )

    $script:ComposerPath = $composerBatch
}

function Install-Cloudflared {
    if (Test-Path -LiteralPath $script:CloudflaredPath -PathType Leaf) {
        $signatureBytes = [System.IO.File]::ReadAllBytes($script:CloudflaredPath)
        if ($signatureBytes.Length -lt 2 -or $signatureBytes[0] -ne 0x4D -or $signatureBytes[1] -ne 0x5A) {
            throw "El archivo $script:CloudflaredPath no es un ejecutable valido de Windows. Copia una version integra de cloudflared.exe."
        }

        try {
            & $script:CloudflaredPath --version 2>$null
            if ($LASTEXITCODE -ne 0) {
                throw 'codigo de salida distinto de cero'
            }
        } catch {
            throw "El ejecutable $script:CloudflaredPath esta danado, incompleto o no es compatible con esta PC. Reemplazalo por una copia integra de cloudflared.exe."
        }

        Write-Host "cloudflared existente detectado en $script:CloudflaredPath; se conservara."
        return
    }

    throw "No se encontro cloudflared.exe en $script:CloudflaredPath. Copia la carpeta C:\cloudflared antes de ejecutar el instalador."
}

function Enable-PhpExtensions {
    param([string[]] $Extensions)

    $phpIniPath = Join-Path $script:XamppRoot 'php\php.ini'
    if (-not (Test-Path -LiteralPath $phpIniPath -PathType Leaf)) {
        throw "No se encontro la configuracion de PHP: $phpIniPath."
    }

    $lines = [System.Collections.Generic.List[string]]::new()
    foreach ($line in [System.IO.File]::ReadAllLines($phpIniPath)) {
        $lines.Add($line)
    }

    for ($index = 0; $index -lt $lines.Count; $index++) {
        foreach ($extension in $Extensions) {
            $pattern = '^([ \t]*);[ \t]*(extension[ \t]*=[ \t]*' + [regex]::Escape($extension) + '[ \t]*)$'
            if ($lines[$index] -match $pattern) {
                $lines[$index] = [regex]::Replace($lines[$index], $pattern, '$1$2')
                break
            }
        }
    }

    [System.IO.File]::WriteAllLines($phpIniPath, $lines, [System.Text.UTF8Encoding]::new($false))
}

function Assert-MySqlListenerIsLocal {
    $listeners = @(Get-NetTCPConnection -LocalPort $script:DatabasePort -State Listen -ErrorAction SilentlyContinue)
    if ($listeners.Count -eq 0) {
        throw "El puerto $script:DatabasePort respondio, pero no se pudo identificar su proceso."
    }

    foreach ($listener in $listeners) {
        $process = Get-CimInstance Win32_Process -Filter "ProcessId = $($listener.OwningProcess)" -ErrorAction SilentlyContinue
        $expectedExecutable = Join-Path $script:XamppRoot 'mysql\bin\mysqld.exe'

        if ($null -eq $process -or $process.ExecutablePath -ine $expectedExecutable) {
            throw "El puerto $script:DatabasePort esta ocupado por un proceso que no es el MariaDB de XAMPP."
        }

        if ($listener.LocalAddress -notin @('127.0.0.1', '::1')) {
            throw "MariaDB esta escuchando en $($listener.LocalAddress). Detenla desde XAMPP y vuelve a ejecutar para restringirla a localhost."
        }
    }
}

function Set-MySqlBindLocalhost {
    $configurationPath = Join-Path $script:XamppRoot 'mysql\bin\my.ini'
    if (-not (Test-Path -LiteralPath $configurationPath -PathType Leaf)) {
        throw "No se encontro la configuracion MariaDB en $configurationPath."
    }

    $listeners = @(Get-NetTCPConnection -LocalPort $script:DatabasePort -State Listen -ErrorAction SilentlyContinue)
    if ($listeners.Count -gt 0) {
        Assert-MySqlListenerIsLocal
    }

    $lines = [System.Collections.Generic.List[string]]::new()
    foreach ($line in [System.IO.File]::ReadAllLines($configurationPath)) {
        $lines.Add($line)
    }

    $updatedLines = [System.Collections.Generic.List[string]]::new()
    $insideMysqld = $false
    $foundMysqld = $false
    $foundBindAddress = $false

    foreach ($line in $lines) {
        if ($line -match '^\s*\[([^\]]+)\]\s*$') {
            if ($insideMysqld -and -not $foundBindAddress) {
                $updatedLines.Add('bind-address=127.0.0.1')
                $foundBindAddress = $true
            }

            $insideMysqld = $Matches[1] -ieq 'mysqld'
            if ($insideMysqld) {
                $foundMysqld = $true
            }
            $updatedLines.Add($line)
        } elseif ($insideMysqld -and $line -match '^\s*bind-address\s*=') {
            $updatedLines.Add('bind-address=127.0.0.1')
            $foundBindAddress = $true
        } else {
            $updatedLines.Add($line)
        }
    }

    if ($insideMysqld -and -not $foundBindAddress) {
        $updatedLines.Add('bind-address=127.0.0.1')
        $foundBindAddress = $true
    }

    if (-not $foundMysqld) {
        throw 'No se encontro la seccion [mysqld] en la configuracion MariaDB; no se modifico la configuracion.'
    }

    $backupPath = $configurationPath + '.sistema-equipos.bak'
    if (-not (Test-Path -LiteralPath $backupPath)) {
        Copy-Item -LiteralPath $configurationPath -Destination $backupPath
    }

    [System.IO.File]::WriteAllLines(
        $configurationPath,
        $updatedLines,
        [System.Text.UTF8Encoding]::new($false)
    )
}

function Test-TcpPort {
    param(
        [string] $HostName,
        [int] $Port
    )

    $client = [System.Net.Sockets.TcpClient]::new()
    try {
        $task = $client.ConnectAsync($HostName, $Port)
        if (-not $task.Wait(500)) {
            return $false
        }

        return $client.Connected
    } catch {
        return $false
    } finally {
        $client.Dispose()
    }
}

function Start-MySqlForSetup {
    if (Test-TcpPort -HostName '127.0.0.1' -Port $script:DatabasePort) {
        Assert-MySqlListenerIsLocal
        Write-Host 'MariaDB ya estaba activa; no se detendra al finalizar la instalacion.'
        return
    }

    if (-not (Test-Path -LiteralPath $script:MySqlStartPath -PathType Leaf)) {
        throw "No se encontro $script:MySqlStartPath."
    }

    Start-Process -FilePath $env:ComSpec `
        -ArgumentList @('/c', "`"$script:MySqlStartPath`"") `
        -WindowStyle Hidden
    $script:StartedMySqlForSetup = $true

    $timer = [System.Diagnostics.Stopwatch]::StartNew()
    while ($timer.Elapsed.TotalSeconds -lt 90) {
        if (Test-TcpPort -HostName '127.0.0.1' -Port $script:DatabasePort) {
            Assert-MySqlListenerIsLocal
            return
        }
        Start-Sleep -Milliseconds 500
    }

    throw 'MariaDB no respondio en 90 segundos. Revisa C:\xampp\mysql\data\mysql_error.log.'
}

function Invoke-MySql {
    param([string] $Sql)

    $process = [System.Diagnostics.Process]::new()
    $process.StartInfo.FileName = $script:MySqlPath
    $process.StartInfo.Arguments = '--protocol=tcp --host=127.0.0.1 --port=3306 --user=root --batch --skip-column-names'
    $process.StartInfo.UseShellExecute = $false
    $process.StartInfo.RedirectStandardInput = $true
    $process.StartInfo.RedirectStandardOutput = $true
    $process.StartInfo.RedirectStandardError = $true
    $process.StartInfo.CreateNoWindow = $true

    if (-not $process.Start()) {
        throw 'No se pudo iniciar el cliente de MariaDB.'
    }

    $process.StandardInput.WriteLine($Sql)
    $process.StandardInput.Close()
    $standardOutput = $process.StandardOutput.ReadToEnd()
    $standardError = $process.StandardError.ReadToEnd()
    $process.WaitForExit()

    if ($process.ExitCode -ne 0) {
        throw "MariaDB rechazo la operacion. $standardError"
    }

    return $standardOutput.Trim()
}

function New-ApplicationDatabase {
    param([string] $DatabasePassword)

    Write-InstallerStep 'Creando una base nueva y un usuario local exclusivo'
    $databaseExists = Invoke-MySql -Sql "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = '$script:DatabaseName';"
    if ($databaseExists -ne '0') {
        throw "La base '$script:DatabaseName' ya existe. Para evitar perdida de datos, el instalador no la modificara."
    }

    $userExists = Invoke-MySql -Sql "SELECT COUNT(*) FROM mysql.user WHERE User = '$script:DatabaseUsername' AND Host = '127.0.0.1';"
    if ($userExists -ne '0') {
        throw "El usuario MariaDB '$script:DatabaseUsername' ya existe. No se modifico ninguna cuenta existente."
    }

    $sql = @"
CREATE DATABASE $($script:DatabaseName) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '$($script:DatabaseUsername)'@'127.0.0.1' IDENTIFIED BY '$DatabasePassword';
GRANT ALL PRIVILEGES ON $($script:DatabaseName).* TO '$($script:DatabaseUsername)'@'127.0.0.1';
FLUSH PRIVILEGES;
"@

    Invoke-MySql -Sql $sql | Out-Null
}

function Test-DatabaseBackup {
    if (-not (Test-Path -LiteralPath $script:DatabaseBackupPath -PathType Leaf)) {
        return $false
    }

    $backup = Get-Item -LiteralPath $script:DatabaseBackupPath
    if ($backup.Length -eq 0) {
        throw "El backup de base de datos esta vacio: $script:DatabaseBackupPath"
    }

    return $true
}

function Restore-ApplicationDatabase {
    Write-InstallerStep "Restaurando el backup SQL desde $script:DatabaseBackupPath"

    $process = [System.Diagnostics.Process]::new()
    $process.StartInfo.FileName = $script:MySqlPath
    $process.StartInfo.Arguments = '--protocol=tcp --host=127.0.0.1 --port=3306 --user=root --database=' + $script:DatabaseName + ' --binary-mode=1'
    $process.StartInfo.UseShellExecute = $false
    $process.StartInfo.RedirectStandardInput = $true
    $process.StartInfo.RedirectStandardOutput = $true
    $process.StartInfo.RedirectStandardError = $true
    $process.StartInfo.CreateNoWindow = $true

    if (-not $process.Start()) {
        throw 'No se pudo iniciar el cliente de MariaDB para restaurar el backup.'
    }

    $backupContent = [System.IO.File]::ReadAllText($script:DatabaseBackupPath)
    $process.StandardInput.Write($backupContent)
    $process.StandardInput.Close()
    $standardOutput = $process.StandardOutput.ReadToEnd()
    $standardError = $process.StandardError.ReadToEnd()
    $process.WaitForExit()
    $exitCode = $process.ExitCode
    $process.Dispose()

    if ($exitCode -ne 0) {
        throw "MariaDB rechazo la restauracion del backup. $standardError"
    }
}

function Stop-MySqlIfStartedByInstaller {
    if (-not $script:StartedMySqlForSetup -or $script:MySqlStopAttempted) {
        return
    }

    $script:MySqlStopAttempted = $true

    if (Test-Path -LiteralPath $script:MySqlAdminPath -PathType Leaf) {
        & $script:MySqlAdminPath --protocol=tcp --host=127.0.0.1 --port=3306 --user=root --connect-timeout=5 shutdown 2>$null
        $timer = [System.Diagnostics.Stopwatch]::StartNew()
        while ($timer.Elapsed.TotalSeconds -lt 30) {
            if (-not (Test-TcpPort -HostName '127.0.0.1' -Port $script:DatabasePort)) {
                $script:StartedMySqlForSetup = $false
                return
            }
            Start-Sleep -Milliseconds 500
        }
    }

    Write-Warning 'MariaDB sigue activa. Cierrala desde XAMPP Control Panel cuando termine la instalacion.'
}

function New-DesktopShortcut {
    $desktopPath = [Environment]::GetFolderPath('Desktop')
    $shortcutPath = Join-Path $desktopPath 'Iniciar Sistema de Equipos.lnk'
    $powershellPath = Join-Path $PSHOME 'powershell.exe'
    $splashPath = Join-Path $script:ProjectRoot 'iniciar-sistema-splash.ps1'

    if (-not (Test-Path -LiteralPath $splashPath -PathType Leaf)) {
        throw "No se encontro el iniciador visual esperado: $splashPath."
    }

    if (Test-Path -LiteralPath $shortcutPath) {
        throw "Ya existe el acceso directo $shortcutPath. No se sobrescribio."
    }

    $shell = New-Object -ComObject WScript.Shell
    $shortcut = $shell.CreateShortcut($shortcutPath)
    $shortcut.TargetPath = $powershellPath
    $shortcut.Arguments = '-NoProfile -STA -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $splashPath + '"'
    $shortcut.WorkingDirectory = $script:ProjectRoot
    $shortcut.Description = 'Inicia Sistema de Equipos ITIBB'
    $shortcut.IconLocation = Join-Path $script:ProjectRoot 'public\favicon.ico'
    $shortcut.Save()
}

function Add-ApplicationFirewallRule {
    $displayName = 'Sistema de Equipos - Servidor local TCP 8000'
    $existingRule = Get-NetFirewallRule -DisplayName $displayName -ErrorAction SilentlyContinue

    if ($null -eq $existingRule) {
        New-NetFirewallRule `
            -DisplayName $displayName `
            -Direction Inbound `
            -Action Allow `
            -Protocol TCP `
            -LocalPort $script:ApplicationPort `
            -RemoteAddress LocalSubnet `
            -Profile Private `
            -Description 'Permite el acceso al Sistema de Equipos unicamente desde la subred local privada.' |
            Out-Null
    }
}

function Test-Administrator {
    $identity = [System.Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [System.Security.Principal.WindowsPrincipal]::new($identity)
    return $principal.IsInRole([System.Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Start-ElevatedInstaller {
    $arguments = '-NoProfile -ExecutionPolicy Bypass -File "' + $PSCommandPath + '"'
    $process = Start-Process -FilePath 'powershell.exe' -ArgumentList $arguments -Verb RunAs -Wait -PassThru
    exit $process.ExitCode
}

if ($PlanOnly) {
    Write-Output 'Plan de instalacion del Sistema de Equipos'
    Write-Output "Repositorio: https://github.com/$script:ProjectRepository (rama $script:ProjectBranch)"
    Write-Output "Destino: $script:ProjectRoot"
    Write-Output "XAMPP: $script:XamppVersion (SHA-256 verificado), PHP incluido"
    Write-Output 'Microsoft Visual C++ Redistributable: firma digital oficial verificada'
    Write-Output 'Node.js: ultima version LTS oficial (SHA-256 verificado)'
    Write-Output 'Composer: instalador con firma SHA-384 oficial'
    Write-Output "Cloudflared: se valida la copia local en $script:CloudflaredPath"
    Write-Output "Base de datos: se restaura $script:DatabaseBackupPath si existe; si no, se crea limpia"
    Write-Output 'Firewall: TCP 8000 limitado a la subred local y perfil privado'
    exit 0
}

try {
    if (-not [Environment]::Is64BitOperatingSystem) {
        throw 'Se requiere Windows de 64 bits.'
    }

    $operatingSystem = Get-CimInstance Win32_OperatingSystem
    if ($operatingSystem.Caption -notmatch 'Windows 10|Windows 11') {
        throw "Este instalador esta preparado para Windows 10 y Windows 11; se detecto '$($operatingSystem.Caption)'."
    }

    if (-not (Test-Administrator)) {
        Start-ElevatedInstaller
    }

    if (Test-Path -LiteralPath $script:ProjectRoot) {
        throw "Ya existe $script:ProjectRoot. No se sobrescribio ningun archivo."
    }

    $desktopShortcut = Join-Path ([Environment]::GetFolderPath('Desktop')) 'Iniciar Sistema de Equipos.lnk'
    if (Test-Path -LiteralPath $desktopShortcut) {
        throw "Ya existe el acceso directo $desktopShortcut. Cambiale el nombre o retiralo manualmente antes de instalar."
    }

    if (Test-TcpPort -HostName '127.0.0.1' -Port $script:ApplicationPort) {
        throw "El puerto $script:ApplicationPort ya esta en uso. Libera el puerto y vuelve a ejecutar antes de instalar."
    }

    [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
    New-Item -ItemType Directory -Path $script:TemporaryRoot -Force | Out-Null
    $hasDatabaseBackup = Test-DatabaseBackup
    $administratorCredentials = $null
    if (-not $hasDatabaseBackup) {
        $administratorCredentials = Read-AdministratorCredentials
    }
    $databasePassword = Get-RandomDatabasePassword
    $appUrl = 'http://' + (Get-LanIPv4Address) + ':' + $script:ApplicationPort

    Write-InstallerStep 'Descargando el codigo publicado en GitHub'
    $archivePath = Join-Path $script:TemporaryRoot 'project.zip'
    $archiveUrl = 'https://codeload.github.com/' + $script:ProjectRepository + '/zip/refs/heads/' + $script:ProjectBranch
    try {
        Invoke-WebRequest -UseBasicParsing -Uri $archiveUrl -OutFile $archivePath
    } catch {
        throw "No se pudo descargar el codigo desde GitHub. Comprueba la conexion a Internet y el DNS; detalle: $($_.Exception.Message)"
    }
    Expand-Archive -LiteralPath $archivePath -DestinationPath $script:TemporaryRoot

    $extractedRoot = Get-ChildItem -LiteralPath $script:TemporaryRoot -Directory |
        Where-Object { Test-Path -LiteralPath (Join-Path $_.FullName 'artisan') } |
        Select-Object -First 1
    if ($null -eq $extractedRoot -or -not (Test-Path -LiteralPath (Join-Path $extractedRoot.FullName 'artisan'))) {
        throw 'El archivo descargado de GitHub no contiene el proyecto Laravel esperado.'
    }

    Install-VcRedist
    Install-Xampp
    Install-NodeLts
    Install-Composer
    Install-Cloudflared

    if (-not (Test-Path -LiteralPath $script:MySqlPath -PathType Leaf) -or
        -not (Test-Path -LiteralPath $script:MySqlStartPath -PathType Leaf)) {
        throw 'XAMPP no incluye los archivos esperados de MariaDB.'
    }

    $requiredExtensions = @('curl', 'fileinfo', 'gd', 'mbstring', 'openssl', 'pdo_mysql', 'zip')
    $loadedExtensions = @(& $script:PhpPath -m)
    $missingExtensions = @($requiredExtensions | Where-Object { $loadedExtensions -notcontains $_ })
    if ($missingExtensions.Count -gt 0) {
        Enable-PhpExtensions -Extensions $missingExtensions
        $loadedExtensions = @(& $script:PhpPath -m)
    }
    foreach ($extension in $requiredExtensions) {
        if ($loadedExtensions -notcontains $extension) {
            throw "La extension PHP '$extension' no esta habilitada en XAMPP."
        }
    }

    Move-Item -LiteralPath $extractedRoot.FullName -Destination $script:ProjectRoot
    $envPath = Join-Path $script:ProjectRoot '.env'
    Copy-Item -LiteralPath (Join-Path $script:ProjectRoot '.env.example') -Destination $envPath

    $environmentValues = [ordered]@{
        APP_ENV = 'production'
        APP_DEBUG = 'false'
        APP_URL = $appUrl
        DB_CONNECTION = 'mysql'
        DB_HOST = '127.0.0.1'
        DB_PORT = [string] $script:DatabasePort
        DB_DATABASE = $script:DatabaseName
        DB_USERNAME = $script:DatabaseUsername
        DB_PASSWORD = $databasePassword
        LOG_LEVEL = 'warning'
        CLOUDFLARED_BINARY = $script:CloudflaredPath.Replace('\', '/')
    }

    foreach ($entry in $environmentValues.GetEnumerator()) {
        Set-DotEnvValue -Path $envPath -Key $entry.Key -Value ([string] $entry.Value)
    }
    Protect-EnvironmentFile -Path $envPath

    $env:Path = (Join-Path $script:XamppRoot 'php') + ';' +
        (Split-Path -Parent $script:NodePath) + ';' + $env:Path

    Write-InstallerStep 'Instalando dependencias de PHP y JavaScript'
    $env:COMPOSER_MEMORY_LIMIT = '-1'
    try {
        Invoke-CheckedCommand -FilePath $script:ComposerPath `
            -Arguments @('install', '--no-dev', '--no-interaction', '--prefer-dist', '--optimize-autoloader')
    } finally {
        Remove-Item Env:\COMPOSER_MEMORY_LIMIT -ErrorAction SilentlyContinue
    }

    $npmPath = Join-Path (Split-Path -Parent $script:NodePath) 'npm.cmd'
    Invoke-CheckedCommand -FilePath $npmPath `
        -Arguments @('ci', '--ignore-scripts') `
        -WorkingDirectory $script:ProjectRoot
    Invoke-CheckedCommand -FilePath $npmPath `
        -Arguments @('run', 'build')

    Write-InstallerStep 'Preparando MariaDB solo para crear y migrar la base'
    Set-MySqlBindLocalhost
    Start-MySqlForSetup
    New-ApplicationDatabase -DatabasePassword $databasePassword

    if ($hasDatabaseBackup) {
        Restore-ApplicationDatabase
    }

    Invoke-CheckedCommand -FilePath $script:PhpPath -Arguments @('artisan', 'key:generate', '--force')
    Invoke-CheckedCommand -FilePath $script:PhpPath -Arguments @('artisan', 'config:clear')
    Invoke-CheckedCommand -FilePath $script:PhpPath -Arguments @('artisan', 'migrate', '--force')

    if (-not $hasDatabaseBackup) {
        $env:SEED_ADMIN_USERNAME = $administratorCredentials.Username
        $env:SEED_ADMIN_PASSWORD = $administratorCredentials.Password
        try {
            Invoke-CheckedCommand -FilePath $script:PhpPath `
                -Arguments @('artisan', 'db:seed', '--class=AdminUserSeeder', '--force')
        } finally {
            Remove-Item Env:\SEED_ADMIN_USERNAME -ErrorAction SilentlyContinue
            Remove-Item Env:\SEED_ADMIN_PASSWORD -ErrorAction SilentlyContinue
        }
    }

    Invoke-CheckedCommand -FilePath $script:PhpPath -Arguments @('artisan', 'config:clear')
    Add-ApplicationFirewallRule
    New-DesktopShortcut
    Stop-MySqlIfStartedByInstaller

    Write-Host "`nInstalacion completada." -ForegroundColor Green
    Write-Host "Proyecto: $script:ProjectRoot"
    if ($hasDatabaseBackup) {
        Write-Host 'Base de datos restaurada desde el backup proporcionado.'
    } else {
        Write-Host "Usuario administrador: $($administratorCredentials.Username)"
    }
    Write-Host "URL inicial: $appUrl"
    Write-Host 'Inicia el sistema desde el acceso directo del Escritorio. cloudflared quedo instalado, pero no se inicio.'
    Write-Host 'No se habilito acceso remoto a MariaDB; TCP 3306 permanece restringido a localhost.'
} catch {
    Write-Host "`nERROR: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host 'La instalacion se detuvo sin borrar carpetas ni bases existentes.' -ForegroundColor Yellow
    exit 1
} finally {
    Stop-MySqlIfStartedByInstaller
    Remove-Item Env:\SEED_ADMIN_USERNAME -ErrorAction SilentlyContinue
    Remove-Item Env:\SEED_ADMIN_PASSWORD -ErrorAction SilentlyContinue

    if (Test-Path -LiteralPath $script:TemporaryRoot) {
        Remove-Item -LiteralPath $script:TemporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
    }
}
