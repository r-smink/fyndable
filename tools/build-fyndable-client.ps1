#Requires -Version 5.1
<#
.SYNOPSIS
    Build a release zip + latest.json for fyndable-client.
.DESCRIPTION
    Reads the Version: header from ai-seo-client.php, packages the plugin
    into fyndable-client-<version>.zip and writes the latest.json used by
    updates.fyndable.ai and the customer portal.
#>
param(
    [string]$OutputDir = "$PSScriptRoot\dist",
    [string]$PluginDir = "$PSScriptRoot\..\wp-content\plugins\fyndable-client",
    [string]$UpdateBaseUrl = "https://updates.fyndable.ai"
)

$ErrorActionPreference = 'Stop'

$pluginFile = Join-Path $PluginDir 'ai-seo-client.php'
if (!(Test-Path $pluginFile)) {
    throw "Plugin file not found: $pluginFile"
}

$versionMatch = Select-String -Path $pluginFile -Pattern 'Version:\s*([\d.]+)' | Select-Object -First 1
if (!$versionMatch) {
    throw "Could not read Version: header from $pluginFile"
}
$version = $versionMatch.Matches.Groups[1].Value

Write-Host "Building fyndable-client v$version" -ForegroundColor Cyan

$buildDir = "$PSScriptRoot\.build\fyndable-client"
if (Test-Path $buildDir) {
    Remove-Item -Recurse -Force $buildDir
}
New-Item -ItemType Directory -Path $buildDir -Force | Out-Null

# Copy plugin files, excluding dev/test artifacts
Get-ChildItem -Path $PluginDir -Force | Where-Object {
    $_.Name -notin @('.git', '.github', 'node_modules', 'tests', '.devin', '.vscode', '.claude', '.cursor')
} | ForEach-Object {
    $dest = Join-Path $buildDir $_.Name
    if ($_.PSIsContainer) {
        Copy-Item -Path $_.FullName -Destination $dest -Recurse -Force
    } else {
        Copy-Item -Path $_.FullName -Destination $dest -Force
    }
}

if (!(Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
}

$zipName = "fyndable-client-$version.zip"
$zipPath = Join-Path $OutputDir $zipName

if (Test-Path $zipPath) {
    Remove-Item -Force $zipPath
}

Compress-Archive -Path $buildDir -DestinationPath $zipPath

# Generate latest.json in the format used by UpdateServer and CustomerPortal
$downloadUrl = "$UpdateBaseUrl/fyndable-client/$zipName"
$latest = [ordered]@{
    version          = $version
    download_url     = $downloadUrl
    changelog        = ""
    min_wp_version   = "6.0"
    tested_wp_version = (Get-Date -Format 'yyyy-MM-dd')
    last_updated     = (Get-Date -Format 'yyyy-MM-dd')
    requires_php     = "8.0"
}

$jsonPath = Join-Path $OutputDir 'latest.json'
($latest | ConvertTo-Json) | Set-Content -Path $jsonPath -Encoding UTF8

# SHA256 of latest.json for integrity checks
$hash = (Get-FileHash -Path $jsonPath -Algorithm SHA256).Hash
$hash | Set-Content -Path "$jsonPath.sha256" -Encoding UTF8

# Cleanup build temp dir
Remove-Item -Recurse -Force $buildDir

Write-Host "Built:" -ForegroundColor Green
Write-Host "  Zip: $zipPath"
Write-Host "  Meta: $jsonPath"
