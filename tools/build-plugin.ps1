$ErrorActionPreference = 'Stop'

# Monta dist/arins-login.zip com a pasta arins-login/ na raiz.
#
# Não usa Compress-Archive: no Windows PowerShell 5.1 ele grava os caminhos
# com barra invertida, e o WordPress num servidor Linux não reconhece as
# pastas ("O arquivo do plugin não existe"). Aqui cada entrada é gravada à
# mão com barra normal, qualquer que seja a versão do PowerShell.

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$repositoryRoot = Split-Path -Parent $PSScriptRoot
$distributionDirectory = Join-Path $repositoryRoot 'dist'
$archivePath = Join-Path $distributionDirectory 'arins-login.zip'
$pluginFolder = 'arins-login'
$contents = @('arins-login.php', 'includes', 'assets')

New-Item -ItemType Directory -Path $distributionDirectory -Force | Out-Null
if (Test-Path -LiteralPath $archivePath) {
    Remove-Item -LiteralPath $archivePath -Force
}

$archive = [System.IO.Compression.ZipFile]::Open($archivePath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($item in $contents) {
        $source = Join-Path $repositoryRoot $item
        $files = if (Test-Path -LiteralPath $source -PathType Container) {
            Get-ChildItem -LiteralPath $source -Recurse -File
        } else {
            Get-Item -LiteralPath $source
        }
        foreach ($file in $files) {
            $relative = $file.FullName.Substring($repositoryRoot.Length).TrimStart('\', '/') -replace '\\', '/'
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $archive, $file.FullName, "$pluginFolder/$relative",
                [System.IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
    }
} finally {
    $archive.Dispose()
}

Write-Output $archivePath
