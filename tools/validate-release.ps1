#Requires -Version 5.1
<#
.SYNOPSIS
    Validate a published release on updates.fyndable.ai.
.DESCRIPTION
    Fetches latest.json, downloads the linked zip, extracts it and verifies
    that the version in the zip matches the published metadata.
#>
param(
    [string]$BaseUrl = "https://updates.fyndable.ai"
)

$ErrorActionPreference = 'Stop'

$jsonUrl = "$BaseUrl/fyndable-client/latest.json"
$meta = Invoke-RestMethod -Uri $jsonUrl -UseBasicParsing

Write-Host "Published version: $($meta.version)" -ForegroundColor Cyan
Write-Host "Download URL:      $($meta.download_url)"

$tempDir = "$PSScriptRoot\.validate"
if (Test-Path $tempDir) {
    Remove-Item -Recurse -Force $tempDir
}
New-Item -ItemType Directory -Path $tempDir -Force | Out-Null

try {
    $zipFile = Join-Path $tempDir 'fyndable-client.zip'
    Invoke-WebRequest -Uri $meta.download_url -OutFile $zipFile -UseBasicParsing

    Expand-Archive -Path $zipFile -DestinationPath $tempDir

    $pluginFile = Get-ChildItem -Path $tempDir -Recurse -Filter 'ai-seo-client.php' | Select-Object -First 1
    if (!$pluginFile) {
        throw "ai-seo-client.php not found in downloaded zip"
    }

    $versionMatch = Select-String -Path $pluginFile.FullName -Pattern 'Version:\s*([\d.]+)' | Select-Object -First 1
    $zipVersion = $versionMatch.Matches.Groups[1].Value

    if ($zipVersion -ne $meta.version) {
        throw "Version mismatch: zip=$zipVersion, latest.json=$($meta.version)"
    }

    Write-Host "Validation OK: zip version $zipVersion matches latest.json" -ForegroundColor Green
}
finally {
    if (Test-Path $tempDir) {
        Remove-Item -Recurse -Force $tempDir
    }
}
